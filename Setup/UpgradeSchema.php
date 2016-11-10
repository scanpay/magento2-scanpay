<?php

namespace Scanpay\PaymentModule\Setup;
 
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

use Scanpay\PaymentModule\Model\OrderUpdater;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
 
        // Get tutorial_simplenews table
        $tableName = $setup->getTable('scanpay_seq');
        // Check if the table already exists
        if ($setup->getConnection()->isTableExists($tableName) != true) {
            // Create tutorial_simplenews table
            $table = $setup->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'shopid',
                    Table::TYPE_BIGINT,
                    null,
                    [
                        'unsigned'       => true,
                        'auto_increment' => false,
                        'nullable'       => false,
                        'primary'        => true,
                    ],
                    'Shop Id'
                )
                ->addColumn(
                    'seq',
                    Table::TYPE_BIGINT,
                    null,
                    [
                        'unsigned' => true,
                        'nullable' => false,
                        'default'  => 0,
                    ],
                    'Scanpay Events Sequence Number'
                )
                ->addColumn(
                    'mtime',
                    Table::TYPE_BIGINT,
                    null,
                    [
                        'nullable' => false,
                        'default' => 0,
                    ],
                    'Modification Time'
                )
                ->setComment('Scanpay Events Sequence Store')
                ->setOption('type', 'InnoDB')
                ->setOption('charset', 'utf8');
            $setup->getConnection()->createTable($table);
        }

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            OrderUpdater::ORDER_DATA_SHOPID,
            [
                'type'     => Table::TYPE_BIGINT,
                'unsigned' => true,
                'nullable' => false,
                'default'  => 0,
                'comment'  => 'Shop ID'
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            OrderUpdater::ORDER_DATA_SEQ,
            [
                'type'     => Table::TYPE_BIGINT,
                'unsigned' => true,
                'nullable' => false,
                'default'  => 0,
                'comment'  => 'Scanpay Events Sequence Number'
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            OrderUpdater::ORDER_DATA_NACTS,
            [
                'type'     => Table::TYPE_BIGINT,
                'unsigned' => true,
                'nullable' => false,
                'default'  => 0,
                'comment'  => 'Scanpay Number of Acts'
            ]
        );

        $setup->endSetup();
    }
}
