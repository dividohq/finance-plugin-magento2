<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use Divido\MerchantSDK\Client;
use Divido\MerchantSDK\Handlers\Health\Handler;

class HealthCheckTest extends TestHelper
{
    public function data_provider_resultOfDataReceivedInSDKShouldReflectResultOfDataHelperHealthCheckMethod(): array
    {
        return [
            'True' => [
                true,
                true,
            ],
            'False' => [
                false,
                false,
            ]
        ];
    }

    /**
     * @dataProvider data_provider_resultOfDataReceivedInSDKShouldReflectResultOfDataHelperHealthCheckMethod
     * @param $merchantSdkHealthyValue
     * @param $expectedResult
     */
    public function test_resultOfDataReceivedInSDKShouldReflectResultOfDataHelperHealthCheckMethod(
        $merchantSdkHealthyValue,
        $expectedResult
    ): void
    {
        $data = $this->dataInstance;

        $mockedSdk = $this->createMock(Client::class);

        $handler = $this->createMock(Handler::class);

        $handler->expects($this->once())
            ->method('checkHealth')
            ->willReturn(
                [
                    'healthy' => $merchantSdkHealthyValue
                ]
            );

        $mockedSdk->expects($this->once())
            ->method('health')
            ->willReturn(
                $handler
            );

        self::assertSame(
            $expectedResult,
            $data->getEndpointHealthCheckResult($mockedSdk)
        );
    }
}
