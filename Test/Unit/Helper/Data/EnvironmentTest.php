<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use Magento\Framework\Exception\RuntimeException;

class EnvironmentTest extends TestHelper
{
    public function getEnvironmentShouldReturnUrlBasedOnApiKeyDataProvider(): \Generator
    {
        $apiKeys = [
            [uniqid("testing_pk_"), "testing"],
            [uniqid("staging_pk_"), "staging"],
            [uniqid("sandbox_"), "sandbox"],
            [uniqid("production_"), "production"],
            [uniqid("live_pk_"), "production"],
        ];
        $i = 0;
        foreach ($apiKeys as $keyEnv) {
            $i += 1;

            $environmentName = $keyEnv[1];

            yield 'environment_' . $environmentName . '_' . $i => [
                $keyEnv[0],
                $environmentName,
            ];
        }
    }

    /**
     * Check that the EnvironmentUrl we get form the 'getEnvironmentUrl' method is calculated from the API key stored
     * in settings
     *
     * @dataProvider getEnvironmentShouldReturnUrlBasedOnApiKeyDataProvider
     * @param String $apiKey
     * @param string $environmentName
     */
    public function test_getEnvironmentShouldReturnEnvironmentBasedOnApiKey(
        String $apiKey,
        string $environmentName
    ): void {
        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->with('payment/divido_financing/api_key')
            ->willReturn(
                // The mocked (fake) API key.
                $apiKey
            );

        // Check that the URL is what we expect
        self::assertSame(
            $this->dataInstance->getEnvironment(),
            $environmentName
        );
    }

    public function test_returnFalseIfApiKeyDoesNotContainValidEnvironmentName(): void
    {
        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->with('payment/divido_financing/api_key')
            ->willReturn(
                // The mocked (fake) API key.
                uniqid('jibberjabbberdoesnotexistaskey_')
            );

        self::assertFalse($this->dataInstance->getEnvironment());
    }
}
