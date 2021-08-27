<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use Divido\DividoFinancing\Helper\Data;
use Divido\MerchantSDK\Environment;

class ScriptUrlTest extends TestHelper
{
    public function test_shouldUseDefaultUrlIfApiKeyIsEmpty(): void
    {
        $dataInstance = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(
                [
                    'getScriptUrl'
                ]
            )->getMock();

        $dataInstance->expects($this->once())
            ->method('getApiKey')
            ->willReturn('');

        $dataInstance->expects($this->never())
            ->method('getPlatformEnv');

        self::assertSame(
            '//cdn.divido.com/widget/v3/divido.calculator.js',
            $dataInstance->getScriptUrl()
        );
    }

    public function data_provider_shouldGenerateUrlDependingOnEnvironmentAndTenant(): \Generator
    {
        $apiKey = uniqid('api_key_');
        $tenant = uniqid('tenant_');
        $environment = uniqid('environment_');
        yield 'random_strings' => [
            $apiKey,
            $tenant,
            $environment,
            sprintf(
                '//cdn.divido.com/widget/v3/%s.%s.calculator.js',
                $tenant,
                $environment
            )
        ];

        $tenant = uniqid('tenant_');

        // Production is exempt from adding the environment name to it
        yield 'production_be_without_environment_name' => [
            $apiKey,
            $tenant,
            Environment::PRODUCTION,
            sprintf(
                '//cdn.divido.com/widget/v3/%s.calculator.js',
                $tenant
            )
        ];

        // Check the other applicable environments
        foreach (
            [
                Environment::TESTING,
                Environment::SANDBOX,
                Environment::USER_ACCEPTANCE_TESTING
            ]
            as
            $environment
        ) {
            $tenant = uniqid('tenant_');

            yield $environment . '_' . $tenant => [
                $apiKey,
                $tenant,
                Environment::PRODUCTION,
                sprintf(
                    '//cdn.divido.com/widget/v3/%s.calculator.js',
                    $tenant
                )
            ];
        }
    }

    /**
     * @dataProvider data_provider_shouldGenerateUrlDependingOnEnvironmentAndTenant
     * @param $apiKey
     * @param $tenantName
     * @param $environment
     * @param $expectedScriptUrl
     */
    public function test_shouldGenerateUrlDependingOnEnvironmentAndTenant(
        $apiKey,
        $tenantName,
        $environment,
        $expectedScriptUrl
    ): void {
        $dataInstance = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(
                [
                    'getScriptUrl'
                ]
            )->getMock();

        $dataInstance->expects($this->once())
            ->method('getApiKey')
            ->willReturn($apiKey);

        $dataInstance->expects($this->once())
            ->method('getPlatformEnv')
            ->willReturn($tenantName);

        $dataInstance->expects($this->once())
            ->method('getEnvironment')
            ->with()
            ->willReturn($environment);

        self::assertSame(
            $expectedScriptUrl,
            $dataInstance->getScriptUrl()
        );
    }
}
