<?php


namespace Divido\DividoFinancing\Test\Unit\Helper\Data;


class GetSDKTest extends TestHelper
{
    public function test_getSdkFunctionShouldReturnSdkInstanceWithExpectedProperties(): void
    {
        $environmentName = \Divido\MerchantSDK\Environment::TESTING;

        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->withConsecutive(
                [
                    'payment/divido_financing/api_key',
                ],
                [
                    'payment/divido_financing/debug',
                ],
                [
                    'payment/divido_financing/debug',
                ],
                [
                    'payment/divido_financing/debug',
                ],
                [
                    'payment/divido_financing/environment_url',
                ]
            )->willReturnOnConsecutiveCalls(
                // The mocked (fake) API key.
                uniqid($environmentName . '_'),

                // Debug set to false
                false, false, false,

                // Environment URL is empty.
                ''
            );

        $sdk = $this->dataInstance->getSdk();

        self::assertSame(
            $sdk->getEnvironment(),
            $environmentName
        );
    }
}
