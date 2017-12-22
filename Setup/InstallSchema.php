<?php

namespace Divido\DividoFinancing\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Db\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $tableName = $installer->getTable('divido_lookup');
        $tableExists = $installer->getConnection()->isTableExists($tableName);

        if (! $tableExists) {
            $this->createDividoTable($installer, $tableName);
        }

        $installer->endSetup();
    }

    public function createDividoTable(SchemaSetupInterface $installer, $tableName)
    {

        $table = $installer->getConnection()
            ->newTable($tableName)
            ->addColumn(
                'id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary'  => true,
                ],
                'ID'
            )
            ->addColumn(
                'salt',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Salt'
            )
            ->addColumn(
                'quote_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'nullable' => false,
                    'unsigned' => true,
                ],
                'Quote ID'
            )
            ->addColumn(
                'order_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'nullable' => true,
                    'unsigned' => true,
                ],
                'Order ID'
            )
            ->addColumn(
                'deposit_value',
                Table::TYPE_NUMERIC,
                null,
                [
                    'nullable' => true,
                    'precision' => 10,
                    'scale' => 2,
                ],
                'Deposit Value'
            )
            ->addColumn(
                'proposal_id',
                Table::TYPE_TEXT,
                null,
                [
                    'nullable' => true,
                    'unsigned' => true,
                ],
                'Proposal ID'
            )
            ->addColumn(
                'application_id',
                Table::TYPE_TEXT,
                null,
                [
                    'nullable' => true,
                    'unsigned' => true,
                ],
                'Application ID'
            )
            ->addColumn(
                'canceled',
                Table::TYPE_BOOLEAN,
                null,
                [
                    'nullable' => true,
                ],
                'Application was canceled'
            )
            ->addColumn(
                'declined',
                Table::TYPE_BOOLEAN,
                null,
                [
                    'nullable' => true,
                ],
                'Application was declined'
            )
            ->addColumn(
                'initial_cart_value',
                Table::TYPE_NUMERIC,
                null,
                [
                    'nullable' => true,
                    'precision' => 10,
                    'scale' => 2,                ],
                'Initial Cart Value'
            )
            ->setComment('Divido lookup table')
            ->setOption('type', 'InnoDB')
            ->setOption('charset', 'utf8');

        $installer->getConnection()->createTable($table);
    }
}
