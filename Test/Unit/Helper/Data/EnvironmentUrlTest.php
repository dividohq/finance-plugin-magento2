<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

class EnvironmentUrlTest extends TestHelper
{
    public function getEnvironmentUrlShouldReturnUrlBasedOnApiKeyDataProvider(): \Generator
    {
        yield 'name' => [
            '',

        ];
    }

    /**
     * @dataProvider getEnvironmentUrlShouldReturnUrlBasedOnApiKeyDataProvider
     */
    public function test_getEnvironmentUrlShouldReturnUrlBasedOnApiKey(): void
    {
        self::markTestSkipped();
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
}
