<?php
namespace Divido\DividoFinancing\Block\Adminhtml\Order\View;

use Divido\DividoFinancing\Helper\Data;

class Custom extends \Magento\Backend\Block\Template
{
    private $helper;
    private $coreRegistry;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Divido\DividoFinancing\Helper\Data $helper,
        array $data = []
    ) {
    
        $this->helper = $helper;
        $this->coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    public function getOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }

    public function getDividoInfo()
    {
        $info = null;
        $order = $this->getOrder();

        if ($lookup = $this->helper->getLookupForOrder($order)) {
            $info = $lookup;
        }

        return $info;
    }

    public function getCancellationReasons(){
        $order = $this->getOrder();

        $code  = $order->getPayment()->getMethodInstance()->getCode();
        $autoCancel = $this->helper->getConfigValue('auto_cancellation');

        $cancellation = false;

        if ($code == Data::PAYMENT_METHOD && !empty($this->helper->getApiKey()) && $autoCancel) {
            $cancellation = [
                'reasons' => false,
                'notification' => __("Are you sure you want to cancel this order?")
            ];
            try{
                $application = $this->helper->getApplicationFromOrder($order);
                if(array_key_exists($application['lender']['app_name'], Data::REFUND_CANCEL_REASONS)){
                    $cancellation['reasons'] = Data::REFUND_CANCEL_REASONS[$application['lender']['app_name']];
                }
            } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $_){
                $cancellation['notification'] = __("It appears you are using a different API Key to the one used to create this application.&nbsp;
                Please revert to that API key if you wish to automatically cancel this order.");
            }

        }
        return $cancellation;
    }
}
