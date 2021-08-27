<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use Divido\MerchantSDK\Client;
use Divido\MerchantSDK\Handlers\Health\Handler;

class HealthCheckTest extends TestHelper
{
    public function test_shouldBeAbleToCallGetEndpointHealthCheckResult(): void
    {
        $data = $this->dataInstance;

        $mockedSdk = $this->createMock(Client::class);

        $handler = $this->createMock(Handler::class);

        $handler->expects($this->once())
            ->method('checkHealth')
            ->willReturn(
                [
                    'healthy' => true
                ]
            );

        $mockedSdk->expects($this->once())
            ->method('health')
            ->willReturn(
                $handler
            );

        self::assertTrue(
            $data->getEndpointHealthCheckResult($mockedSdk)
        );
    }

    public function test_shouldBeAbleToCallGetFailedEndpointHealthCheckResult(): void
    {
        $data = $this->dataInstance;

        $mockedSdk = $this->createMock(Client::class);

        $handler = $this->createMock(Handler::class);

        $handler->expects($this->once())
            ->method('checkHealth')
            ->willReturn(
                [
                    'healthy' => false
                ]
            );

        $mockedSdk->expects($this->once())
            ->method('health')
            ->willReturn(
                $handler
            );

        self::assertFalse(
            $data->getEndpointHealthCheckResult($mockedSdk)
        );
    }
}
