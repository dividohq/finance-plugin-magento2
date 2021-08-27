<?php
namespace Divido\DividoFinancing\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Message\ManagerInterface;

class ConfigChangeObserver implements ObserverInterface
{
    public const CONFIG_XPATH_ENVIRONMENT_URL = 'payment/divido_financing/environment_url';
    public const CONFIG_XPATH_API_KEY = 'payment/divido_financing/api_key';

    public $dataHelper;
    private $messageManager;

    public function __construct(
        \Divido\DividoFinancing\Helper\Data $dataHelper,
        ManagerInterface $messageManager
    ) {
        $this->messageManager = $messageManager;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Does a health check against the merchant-api-pub endpoint
     */
    private function environmentHealthCheck()
    {
        try {
            $sdkClient = $this->dataHelper->getSdk();
        } catch (RuntimeException $e) {
            $this->messageManager->addErrorMessage('Error while getting client to check health of endpoint');
            return;
        }

        // Get result of health check
        $healthCheckResult =$this->dataHelper->getEndpointHealthCheckResult(
            $sdkClient
        );

        // If not ok, show an error message
        if($healthCheckResult !== true){
            $this->messageManager->addErrorMessage('Error, could not validate the health of endpoint, please check the "environment_url" setting');
            return;
        }

        // Health check passed
        $this->messageManager->addSuccessMessage('Environment URL health check passed');
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $eventData = $observer->getEvent()->getData();

        if(!array_key_exists('changed_paths', $eventData)){
            return;
        }

        $changedPaths = $eventData['changed_paths'];
        // Check that the values we are interested in
        if(
            in_array(self::CONFIG_XPATH_ENVIRONMENT_URL, $changedPaths) ||
            in_array(self::CONFIG_XPATH_API_KEY, $changedPaths)
        ){
            $this->environmentHealthCheck();
        }
    }
}
