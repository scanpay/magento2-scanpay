<?php

namespace Scanpay\PaymentModule\Model;
use \Magento\Framework\Exception\LocalizedException;

class GlobalSequencer
{
    private $logger;
    private $resource;
    private $dbConnection;
    private $tableName;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->logger = $logger;
        $this->resource = $resource;
        $this->dbConnection = $this->resource->getConnection();
        $this->tableName = $this->dbConnection->getTableName('scanpay_variables');
        /* Create table entry for seq if not exists */
        if (!$this->load()) {
            $data = [ 'var' => 'seq', 'value' => 0, 'mtime' => 0 ];
            $this->dbConnection->insert($this->tableName, $data);
        }
    }

    public function save($seq)
    {
        if (!is_int($seq)) {
            $this->logger->error('Sequence argument is not an int');
            return false;
        }
        $data = [ 'value' => $seq, 'mtime' => time() ];
        $where = [ 'var = ?' => 'seq', 'value < ?' => $seq ];
        $ret = $this->dbConnection->update($this->tableName, $data, $where);
        return !!$ret;
    }

    public function load()
    {
        $select = $this->dbConnection->select()->from($this->tableName)->where('var = ?', 'seq');
        $row = $this->dbConnection->fetchRow($select);
        if (!$row) {
            return false;
        }
        if (!isset($row['value']) || !isset($row['mtime'])) {
            return false;
        }
        return [ 'seq' => (int)$row['value'], 'mtime' => $row['mtime'] ];
    }

}
