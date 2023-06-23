<?php
namespace Divido\DividoFinancing\Block\Adminhtml\Order\Creditmemo\Create;

use Divido\DividoFinancing\Helper\Data;

class Custom extends \Magento\Backend\Block\Template
{
    private $helper;
    private $_coreRegistry;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $_coreRegistry,
        \Divido\DividoFinancing\Helper\Data $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->_coreRegistry = $_coreRegistry;
        parent::__construct($context, $data);
    }

    public function getCreditMemo() {
        $creditMemo = $this->_coreRegistry->registry('current_creditmemo');
        return $creditMemo;
    }

    public function getApplication(){
        $creditMemo = $this->getCreditMemo();
        $order = $creditMemo->getOrder();
        
        $code  = $order->getPayment()->getMethodInstance()->getCode();
        $returnApp = [
            'title' => 'Please Note',
            'refundable' => false,
            'partial_refundable' => false,
            'notifications' => []
        ];
        $autoRefund = $this->helper->getAutoRefund();

        if ($code == Data::PAYMENT_METHOD && !empty($this->helper->getApiKey()) && $autoRefund) {
            try{
                $application = $this->helper->getApplicationFromOrder($order);
                $returnApp['refundable'] = $application['amounts']['refundable_amount'];
                $returnApp['notifications'][] = sprintf(
                    __("The de-facto amount refundable for this application is %s. Any refund attempt exceding this will be processed as a full refund for %s"),
                    $order->formatPrice($returnApp['refundable']/100),
                    $order->formatPrice($returnApp['refundable']/100)
                );

                if(in_array($application['lender']['app_name'], Data::NON_PARTIAL_LENDERS)){
                    $returnApp['notifications'][] = __("We are unable to request partial refunds from your lender");
                    $returnApp['partial_refundable'] = 0;
                }elseif($application['finance_plan']['credit_amount']['minimum_amount'] > 0){
                    $returnApp['partial_refundable'] = $application['amounts']['refundable_amount'] - $application['finance_plan']['credit_amount']['minimum_amount'];
                    $returnApp['notifications'][] = sprintf(
                        __("If you are making a partial refund, the maximum that can be refunded (before reaching this finance plan's minimum credit limit) is %s"), 
                        $order->formatPrice($returnApp['partial_refundable']/100)
                    );
                }
               
                $returnApp['notifications'][] = __("Please turn off automatic refunds in the Powered By Divido plugin if you wish to create refunds outside of the above scope");

                if(array_key_exists($application['lender']['app_name'], Data::REFUND_CANCEL_REASONS)){
                    $returnApp['reason_notification'] = __("You must specify one of the following reasons for the refund to be successfully processed");
                    $returnApp['reason_title'] = __("Refund Reason");
                    $returnApp['reasons'] = Data::REFUND_CANCEL_REASONS[$application['lender']['app_name']];
                }
            } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $_){
                $apiKeyError = _("It appears you are using a different API Key to the one used to create this application. Please revert to that API key if you wish to automatically request this amount is refunded");
                $returnApp['notifications'] = [$apiKeyError];
            }
        }
        
        return $returnApp;
    }


}