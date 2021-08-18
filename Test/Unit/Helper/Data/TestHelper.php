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

    protected function setUp(): void
    {
        parent::setUp();

        $this->scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->logger = $this->createMock(\Divido\DividoFinancing\Logger\Logger::class);

        $this->dataInstance = new Data(
            $this->scopeConfig,
            $this->logger,
            $this->createMock(\Magento\Framework\App\CacheInterface::class),
            $this->createMock(\Magento\Checkout\Model\Cart::class),
            $this->createMock(\Magento\Store\Model\StoreManagerInterface::class),
            $this->createMock(\Magento\Framework\App\ResourceConnection::class),
            $this->createMock(LookupFactory::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(ProductFactory::class),
            $this->createMock(\Magento\Framework\Locale\Resolver::class)
        );
    }
}
