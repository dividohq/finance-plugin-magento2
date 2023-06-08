<?php
namespace Divido\DividoFinancing\Block\Adminhtml\Order\Creditmemo\Create;

class Custom extends \Magento\Backend\Block\Template
{
    private $helper;
    private $_coreRegistry;

    const REFUND_REASONS = [
        "ALTERNATIVE_PAYMENT_METHOD_USED" => "Alternative Payment Method Used",
        "GOODS_FAULTY" => "Goods Faulty",
        "GOODS_NOT_RECEIVED" => "Goods Not Received",
        "GOODS_RETURNED" => "Goods Returned",
        "LOAN_AMENDED" => "Loan Amended",
        "NOT_GOING_AHEAD" => "Not Going Ahead",
        "NO_CUSTOMER_INFORMATION" => "No Customer Information"
    ];

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
        $application = [];
        $autoRefund = $this->helper->getAutoRefund();

        if ($code == 'divido_financing' && !empty($this->helper->getApiKey()) && $autoRefund) {
            try{
                $application['refundable']['int'] = $this->helper->getRefundableAmount($order);
                $application['refundable']['float'] = ($application['refundable']['int']/100);
                $application['notification'] = sprintf(
                    "Please note you can only be refunded &pound;%s currently. You must contact your lender in order to obtain a refund for any deposit, etc.", 
                    $application['refundable']['float']
                );

                if($this->helper->getPlatformEnv() === 'novuna'){
                    $application['reasons'] = self::REFUND_REASONS;
                }
            } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $_){
                $application['notification'] = "It appears you are using a different API Key to the one used to create this application.&nbsp;
                Please revert to that API key if you wish to automatically request this amount is refunded.";
            }
        }
        
        return $application;
    }


}