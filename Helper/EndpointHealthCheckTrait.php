<?php


namespace Divido\DividoFinancing\Helper;


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
        // Todo: Do actual health check with SDK
        return true;
    }
}
