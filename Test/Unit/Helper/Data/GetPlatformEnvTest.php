<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use Divido\DividoFinancing\Helper\Data;
use Divido\MerchantSDK\Environment;

class GetPlatformEnvTest extends TestHelper
{
    public function test_shouldUseURLAsCacheKeyWhenGettingPlatformEnvNameFromCache(): void
    {
        $url = uniqid('http://api.example.com');

        $hash = md5($url);

        $returnedFromCache = uniqid('env_name');

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'payment/divido_financing/environment_url'
            )
            ->willReturn(
                $url
            );

        $this->cache->expects($this->once())
            ->method('load')
            ->with(
                sprintf('%s_%s', Data::CACHE_PLATFORM_KEY, $hash)
            )->willReturn(
                $returnedFromCache
            );

        self::assertSame(
            $this->dataInstance->getPlatformEnv(),
            $returnedFromCache
        );
    }
}
