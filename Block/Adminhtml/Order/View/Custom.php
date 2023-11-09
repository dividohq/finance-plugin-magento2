<?php
namespace Divido\DividoFinancing\Block\Adminhtml\Order\View;

use Divido\DividoFinancing\Exceptions\MessageValidationException;
use Divido\DividoFinancing\Helper\Data;

class Custom extends \Magento\Backend\Block\Template
{
    private $helper;
    private $coreRegistry;
    private $logger;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Divido\DividoFinancing\Helper\Data $helper,
        \Divido\DividoFinancing\Logger\Logger $logger,
        array $data = []
    ) {
    
        $this->helper = $helper;
        $this->coreRegistry = $registry;
        $this->logger = $logger;
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
                if(array_key_exists($application->lender->app_name, Data::REFUND_CANCEL_REASONS)){
                    $cancellation['reasons'] = Data::REFUND_CANCEL_REASONS[$application->lender->app_name];
                }
            } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $e){
                $this->logger->error(sprintf("Merchant API bad response exception: %s", $e->getMessage()));
                $cancellation['notification'] = __("It appears the cancellation was created with a different API key to the one currently in use. Please revert to that API Key if you wish to notify the lender");
            } catch(MessageValidationException $e){
                $this->logger->error(sprintf("Refund Validation error: %s", $e->getMessage()));
                $cancellation['notification'] = "Error validating application information";
            } catch(\Exception $e){
                $this->logger->error(sprintf("Unexpected error: %s", $e->getMessage()));
                $cancellation['notification'] = "An unknown error has occurred";
            }

        }
        return $cancellation;
    }
}
