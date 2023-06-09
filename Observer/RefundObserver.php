<?php
namespace Divido\DividoFinancing\Observer;

use Divido\DividoFinancing\Exceptions\RefundException;
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
            
        if ($code == 'divido_financing') {
            $refundItems = $this->generateRefundItems($params['creditmemo'], $order->getItems());

            $amount = $this->helper->getRefundAmount($refundItems);
            if(isset($params['pbd_refund_limit']) && $amount > $params['pbd_refund_limit']){
                $amount = $params['pbd_refund_limit'];
            }

            $this->logger->info('PBD Refund Triggered', ['Quote' => $order->getQuoteId(), 'request' => $params]);

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
                'price' => $orderItem->getPriceInclTax()
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
