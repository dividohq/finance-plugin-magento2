<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use Magento\Framework\Exception\RuntimeException;

class EnvironmentUrlTest extends TestHelper
{
    public function getEnvironmentUrlShouldReturnUrlBasedOnApiKeyDataProvider(): \Generator
    {
        foreach (array_keys(\Divido\MerchantSDK\Environment::CONFIGURATION) as $environmentName) {
            yield 'environment_' . $environmentName => [
                uniqid($environmentName . '_'),
                \Divido\MerchantSDK\Environment::CONFIGURATION[$environmentName]['base_uri']
            ];
        }
    }

    /**
     * Check that the EnvironmentUrl we get form the 'getEnvironmentUrl' method is calculated from the API key stored
     * in settings
     *
     * @dataProvider getEnvironmentUrlShouldReturnUrlBasedOnApiKeyDataProvider
     * @param $apiKey
     * @param $expectedUrl
     */
    public function test_getEnvironmentUrlShouldReturnUrlBasedOnApiKey(
        String $apiKey,
        string $expectedUrl
    ): void {
        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->withConsecutive(
                [
                    'payment/divido_financing/environment_url',
                ],
                [
                    'payment/divido_financing/api_key',
                ],
                [
                    'payment/divido_financing/debug',
                ]
            )->willReturnOnConsecutiveCalls(
                // Environment URL is empty.
                '',

                // The mocked (fake) API key.
                $apiKey,

                // Debug set to false.
                false
            );

        // Check that the URL is what we expect
        self::assertSame(
            $this->dataInstance->getEnvironmentUrl(),
            $expectedUrl
        );
    }

    // Check that the data stored in
    public function test_getEnvironmentUrlShouldReturnDataStoredInConfig(): void
    {
        $environmentUrlConfigValue = uniqid('http://environment_url.example.com/');

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'payment/divido_financing/environment_url'
            )->willReturn(
                $environmentUrlConfigValue
            );

        $url = $this->dataInstance->getEnvironmentUrl();

        self::assertSame(
            $url,
            $environmentUrlConfigValue
        );
    }

    public function test_ReturnEmptyStringIfAPIKeyIsWeird(): void
    {
        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->withConsecutive(
                [
                    'payment/divido_financing/environment_url',
                ],
                [
                    'payment/divido_financing/api_key',
                ],
                [
                    'payment/divido_financing/debug',
                ]
            )->willReturnOnConsecutiveCalls(
            // Environment URL is empty.
                '',

                // The mocked (fake) API key.
                uniqid('jibberjabbberdoesnotexistaskey_'),

                // Debug set to false.
                false
            );

        self::assertSame(
            '',
            $this->dataInstance->getEnvironmentUrl()
        );
    }
}
