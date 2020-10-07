<?php

namespace Divido\DividoFinancing\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
	public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
	{
		$installer = $setup;
		$installer->startSetup();

		if (version_compare($context->getVersion(), '1.0.8') < 0) {
			$installer->getConnection()->addColumn(
                $installer->getTable('divido_lookup'),
                'referred',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                    'nullable' => true,
                    'comment' => 'Application was referred',
                ]
            );

            $setup->endSetup();
		}
	}
}
