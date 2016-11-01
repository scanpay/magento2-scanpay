<?php

namespace Scanpay\PaymentModule\Model;
use \Magento\Framework\Exception\LocalizedException;

class GlobalSequencer
{
    //private $_isPkAutoIncrement = false;
    private $resource;
    private $dbConnection;
    private $tableName;
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->resource        = $resource;
        $this->dbConnection    = $this->dbConnection->getConnection();
        $this->tableName = $connection->getTableName('scanpay_variables');
        $this->init();
    }

    public function init() {
        $data = [ 'value' => 0, 'mtime' => time() ];
        $this->dbConnection->insert($this->$tableName, $data);
    }

    public function save($seq)
    {
        if (!ctype_digit($seq)) {
            return false;
        }
        $seq = (int)$seq;
        $data = [ 'value' => $seq, 'mtime' => time() ];
        $where = [ 'key = seq', 'seq < ?' => $seq ];
        $ret = $this->dbConnection->update($this->$tableName, $rowData);
        /* If there were 0 affected rows, it might be because the row somehow was not created.
           Lets try to insert the row then (if it was because the seq condition failed, the insert
           will fail anyway )*/
        if ($ret == 0) { $this->init(); return false; }
        return true;
    }

    public function load()
    {
        $select = $this->dbConnection->select()->from($this->tableName)->where('key = seq');
        return $result = $this->dbConnection->fetchRow($select);
    }

}
