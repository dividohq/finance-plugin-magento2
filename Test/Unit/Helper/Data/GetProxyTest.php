<?php


namespace Divido\DividoFinancing\Test\Unit\Helper\Data;


class GetProxyTest extends TestHelper
{
    public function test_getMerchantApiProxyFunctionShouldReturnProxyInstanceWithExpectedProperties(): void
    {
        $environmentName = \Divido\MerchantSDK\Environment::TESTING;

        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->withConsecutive(
                ['payment/divido_financing/api_key'],
                ['payment/divido_financing/environment_url']
            )->willReturnOnConsecutiveCalls(
                // The mocked (fake) API key.
                uniqid($environmentName . '_'),
                // Environment URL is empty.
                'https://environment.url'
            );

        $proxy = $this->dataInstance->getMerchantApiProxy();

        self::assertSame(
            $proxy->getEnvironmentUrl(),
            'https://environment.url'
        );
    }
}
