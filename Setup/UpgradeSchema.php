<?php

namespace Scanpay\PaymentModule\Setup;
 
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
 
class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
 
        // Get tutorial_simplenews table
        $tableName = $setup->getTable('scanpay_variables');
        // Check if the table already exists
        if ($setup->getConnection()->isTableExists($tableName) != true) {
            // Create tutorial_simplenews table
            $table = $setup->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'key',
                    Table::TYPE_TEXT,
                    255,
                    [
                        'auto_increment' => false,
                        'nullable'       => false,
                        'primary'        => true
                    ],
                    'Key'
                )
                ->addColumn(
                    'value',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Value'
                )
                ->addColumn(
                    'mtime',
                    Table::TYPE_BIGINT,
                    null,
                    ['nullable' => false, 'default' => 0],
                    'Modification Time'
                )
                ->setComment('Scanpay Variables Key-Value Store')
                ->setOption('type', 'InnoDB')
                ->setOption('charset', 'utf8');
            $setup->getConnection()->createTable($table);
        }
 
        $setup->endSetup();
    }
}
