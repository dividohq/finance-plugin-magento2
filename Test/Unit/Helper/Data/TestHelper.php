<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use Divido\DividoFinancing\Helper\Data;
use PHPUnit\Framework\TestCase;

use \Divido\DividoFinancing\Model\LookupFactory;
use Magento\Framework\UrlInterface;
use Magento\Catalog\Model\ProductFactory;

class TestHelper extends TestCase
{
    protected $dataInstance;

    protected $scopeConfig;
    protected $logger;
    protected $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->logger = $this->createMock(\Divido\DividoFinancing\Logger\Logger::class);
        $this->cache = $this->createMock(\Magento\Framework\App\CacheInterface::class);

        $this->dataInstance = new Data(
            $this->scopeConfig,
            $this->logger,
            $this->cache,
            $this->createMock(\Magento\Checkout\Model\Cart::class),
            $this->createMock(\Magento\Store\Model\StoreManagerInterface::class),
            $this->createMock(\Magento\Framework\App\ResourceConnection::class),
            $this->createMock(LookupFactory::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(ProductFactory::class),
            $this->createMock(\Magento\Framework\Locale\Resolver::class),
            new \Laminas\Diactoros\RequestFactory(),
            $this->createMock(\Magento\Quote\Model\QuoteRepository::class)
        );
    }
}
