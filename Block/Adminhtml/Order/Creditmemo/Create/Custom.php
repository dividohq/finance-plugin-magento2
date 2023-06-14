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
        $returnApp = [];
        $autoRefund = $this->helper->getAutoRefund();

        if ($code == 'divido_financing' && !empty($this->helper->getApiKey()) && $autoRefund) {
            try{
                $application = $this->helper->getApplicationFromOrder($order);
                $returnApp['refundable'] = $application['amounts']['refundable_amount'];
                $returnApp['notification'] = sprintf(
                    "Please note you can only be refunded %s currently. You must contact your lender in order to obtain a refund for any deposit, etc.", 
                    $order->formatPrice($returnApp['refundable']/100)
                );

                if(array_key_exists($application['lender']['app_name'], Data::REFUND_CANCEL_REASONS)){
                    $returnApp['reasons'] = Data::REFUND_CANCEL_REASONS[$application['lender']['app_name']];
                }
            } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $_){
                $returnApp['notification'] = "It appears you are using a different API Key to the one used to create this application.&nbsp;
                Please revert to that API key if you wish to automatically request this amount is refunded.";
            }
        }
        
        return $returnApp;
    }


}