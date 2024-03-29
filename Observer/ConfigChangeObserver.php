<?php
namespace Divido\DividoFinancing\Observer;

use Divido\MerchantSDK\Exceptions\InvalidApiKeyFormatException;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class that listens for changes in the configuration, checks what config values has changed and then runs different
 * validations etc
 *
 * This class is registered in etc/events.xml
 *
 * Class ConfigChangeObserver
 * @package Divido\DividoFinancing\Observer
 */
class ConfigChangeObserver implements ObserverInterface
{
    public const CONFIG_XPATH_ENVIRONMENT_URL = 'payment/divido_financing/environment_url';
    public const CONFIG_XPATH_API_KEY = 'payment/divido_financing/api_key';

    private $dataHelper;
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
     *
     * @return bool
     */
    private function environmentHealthCheck(): bool
    {
        // Get result of health check
        $health = $this->dataHelper->getMerchantApiProxy()->getHealth();

        // If not ok, show an error message
        if($health === false){
            $this->messageManager->addErrorMessage('Error, could not validate the health of endpoint, please check the "environment_url" setting');
            return false;
        }

        // Health check passed
        return true;
    }

    /**
     * Validates format of API Key
     */
    private function validateApiKeyFormat()
    {
        try{
            $apiKeyIsValid = $this->dataHelper->validateApiKeyFormat();
        }catch (InvalidApiKeyFormatException $e){
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $eventData = $observer->getEvent()->getData();

        if(!array_key_exists('changed_paths', $eventData)){
            return;
        }

        $changedPaths = $eventData['changed_paths'];

        // If API key or Environment URL has changed
        if(
            in_array(self::CONFIG_XPATH_ENVIRONMENT_URL, $changedPaths) ||
            in_array(self::CONFIG_XPATH_API_KEY, $changedPaths)
        ){
            // Do health check and add messages to messageWriter
            $this->environmentHealthCheck();
        }

        // Check if API key has changed
        if(
            in_array(self::CONFIG_XPATH_API_KEY, $changedPaths)
        ){
            // Validate the API key format
            $this->validateApiKeyFormat();
        }
    }
}
