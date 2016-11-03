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
        $this->resource = $resource;
        $this->dbConnection = $this->resource->getConnection();
        $this->tableName = $this->dbConnection->getTableName('scanpay_variables');
    }

    public function init() {
        $data = [ 'var' => 'seq', 'value' => 0, 'mtime' => time() ];
        $this->dbConnection->insert($this->tableName, $data);
    }

    public function save($seq, $opts = [])
    {
        if (!ctype_digit($seq)) {
            return false;
        }
        $seq = (int)$seq;
        $data = [ 'value' => $seq, 'mtime' => time() ];
        $where = [ 'var = seq', 'seq < ?' => $seq ];
        $ret = $this->dbConnection->update($this->tableName, $rowData);
        /* If there were 0 affected rows, it might be because the row somehow was not created.
           Lets try to insert the row then (if it was because the seq condition failed, the insert
           will fail anyway )*/
        if (!$ret) {
            if (isset($opts['noinit'])) { return false; } 
            $this->init();
            return $this->save($seq, [ 'noinit' => true ]);
        }
        return true;
    }

    public function load($opts = [])
    {
        $select = $this->dbConnection->select()->from($this->tableName)->where('var = ?', 'seq');
        $row = $this->dbConnection->fetchRow($select);
        if (!$row) {
            if (isset($opts['noinit'])) { return false; } 
            $this->init();
            return $this->load([ 'noinit' => true ]);
        }
        if (!isset($row['value']) || !isset($row['mtime'])) {
            return false;
        }
        return [ 'seq' => $row['value'], 'mtime' => $row['mtime'] ];
    }

}
