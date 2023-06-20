<?php
namespace Divido\DividoFinancing\Observer;

use Divido\DividoFinancing\Exceptions\RefundException;
use Divido\DividoFinancing\Helper\Data;
use Divido\DividoFinancing\Model\RefundItem;
use Divido\DividoFinancing\Model\RefundItems;
use Magento\Framework\Event\ObserverInterface;

class RefundObserver implements ObserverInterface
{
    public $helper;

    public $_request;

    public $logger;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Divido\DividoFinancing\Helper\Data $helper,
        \Divido\DividoFinancing\Logger\Logger $logger
    ) {
        $this->_request = $request;
        $this->helper = $helper;
        $this->logger = $logger;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getCreditmemo()->getOrder();
        $code  = $order->getPayment()->getMethodInstance()->getCode();
        $params = $this->_request->getParams();

        $autoRefund = $this->helper->getAutoRefund();
            
        if ($code == 'divido_financing' && $autoRefund) {
            $application = $this->helper->getApplicationFromOrder($order);
            $refundable = $application['amounts']['refundable_amount'];

            $refundItems = $this->generateRefundItems($params['creditmemo'], $order->getItems());
            
            $total = $order->getBaseGrandTotal();

            $amount = $this->helper->getRefundAmount($refundItems);
            if($amount > $refundable){
                $amount = $refundable;
            }

            $partialRefund = ($amount < $total);
            $partiallyRefundable = (in_array($application['lender']['app_name'], Data::NON_PARTIAL_LENDERS))
                ? 0
                : $application['amounts']['refundable_amount'] - $application['finance_plan']['credit_amount']['minimum_amount'];
            if($partialRefund && $amount > $partiallyRefundable){
                throw new RefundException(__("Refund amount exceeds partial refund limit"));
            }

            $this->logger->info('PBD Refund Triggered', ['Quote' => $order->getQuoteId(), 'request' => $params]);

            if(array_key_exists($application['lender']['app_name'], Data::REFUND_CANCEL_REASONS) && !isset($params['pbd_refund_reason'])){
                throw new RefundException(__("You must specify a refund reason"));
            }
            $reason = $params['pbd_refund_reason'] ?? null;
            $this->helper->autoRefund($order, $amount, $refundItems, $reason);
        }
    }

    private function generateRefundItems($creditmemo, $orderItems){
        $strippedItemsArr = [];
        $refundItems = new RefundItems();
        foreach($orderItems as $orderItem){
            $strippedItemsArr[$orderItem->getItemId()] = [
                'name' => $orderItem->getName(),
                'price' => $orderItem->getPriceInclTax() - ($orderItem->getDiscountAmount()/$orderItem->getQtyOrdered())
            ];
        }
        foreach($creditmemo['items'] as $id=>$creditItem){
            if($creditItem['qty'] > 0){
                if(!$strippedItemsArr[$id]){
                    throw new RefundException("Could not retrieve refund item information");
                }
                $refundItems->addRefundItem(
                    new RefundItem(
                        $strippedItemsArr[$id]['name'],
                        ($strippedItemsArr[$id]['price']*100),
                        $creditItem['qty']
                    )
                );
            }
        }

        if(isset($creditmemo['shipping_amount']) && $creditmemo['shipping_amount'] > 0){
            $refundItems->addRefundItem(
                new RefundItem(
                    'Shipping',
                    ($creditmemo['shipping_amount']*100),
                    1
                )
            );
        }

        if(isset($creditmemo['adjustment_positive']) && $creditmemo['adjustment_positive'] > 0){
            $refundItems->addRefundItem(
                new RefundItem(
                    'Positive Adjustment',
                    ($creditmemo['adjustment_positive']*100),
                    1
                )
            );
        }

        if(isset($creditmemo['adjustment_negative']) && $creditmemo['adjustment_negative'] > 0){
            $refundItems->addRefundItem(
                new RefundItem(
                    'Negative Adjustment',
                    0-($creditmemo['adjustment_negative']*100),
                    1
                )
            );
        }
        
        return $refundItems;
    }
}
