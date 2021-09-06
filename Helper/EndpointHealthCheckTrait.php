<?php


namespace Divido\DividoFinancing\Helper;


use PHPUnit\Util\Exception;

trait EndpointHealthCheckTrait
{
    /**
     * Uses health endpoint result from SDK
     *
     * @param \Divido\MerchantSDK\Client $sdkClient
     * @return bool
     */
    public function getEndpointHealthCheckResult(\Divido\MerchantSDK\Client $sdkClient): bool
    {
        $result = $sdkClient->health()->checkHealth();

        if (!array_key_exists('healthy', $result)){
            return false;
        }

        return $result['healthy'] === true;
    }
}
