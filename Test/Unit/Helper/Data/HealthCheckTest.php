<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use Divido\MerchantSDK\Client;
use Magento\Framework\Exception\RuntimeException;

class HealthCheckTest extends TestHelper
{
    public function test_shouldBeAbleToCallGetEndpointHealthCheckResult(): void
    {
        $data = $this->dataInstance;

        $mockedSdk = $this->createMock(Client::class);

        $mockedSdk->expects($this->once())
            ->method('checkHealth')
            ->willReturn(true);

        self::assertTrue(
            $data->getEndpointHealthCheckResult($mockedSdk),
        );
    }

    public function test_shouldBeAbleToCallGetFailedEndpointHealthCheckResult(): void
    {
        $data = $this->dataInstance;

        $mockedSdk = $this->createMock(Client::class);

        $mockedSdk->expects($this->once())
            ->method('checkHealth')
            ->willReturn(false);

        self::assertTrue(
            $data->getEndpointHealthCheckResult($mockedSdk),
        );
    }
}
