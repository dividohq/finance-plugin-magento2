<?php

namespace Divido\DividoFinancing\Setup;

use Divido\DividoFinancing\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    private $configWriter;
    private $scopeConfig;
    private $dataHelper;

    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        Data $dataHelper
    )
    {
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->dataHelper = $dataHelper;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        // Only on version 2.5.0 we need do to do this.
        if (version_compare($context->getVersion(), '2.5.0', '==')) {
            // Save information to the environment_url property.a
            $environmentUrl = $this->scopeConfig->getValue(
                'payment/divido_financing/environment_url',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );

            if(empty($environmentUrl)){
                $this->configWriter->save(
                    'payment/divido_financing/environment_url',
                    $this->dataHelper->getEnvironmentUrl(),
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
            }
        }
    }
}
