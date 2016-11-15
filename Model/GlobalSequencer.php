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
        $this->tableName = $this->dbConnection->getTableName('scanpay_seq');
    }

    public function updateMtime($shopId)
    {
        $data = [ 'mtime' => time() ];
        $where = [ 'shopid = ?' => $shopId ];
        $this->dbConnection->update($this->tableName, $data, $where);
    }

    public function insert($shopId)
    {
        if (!is_int($shopId) || $shopId <= 0) {
            $this->logger->error('ShopId argument is not an unsigned int');
            return false;
        }

        $data = [ 'shopid' => $shopId, 'seq' => 0, 'mtime' => 0 ];
        $this->dbConnection->insert($this->tableName, $data);      
    }

    public function save($shopId, $seq)
    {
        if (!is_int($shopId) || $shopId <= 0) {
            $this->logger->error('ShopId argument is not an unsigned int');
            return false;
        }

        if (!is_int($seq) || $seq < 0) {
            $this->logger->error('Sequence argument is not an unsigned int');
            return false;
        }

        $data = [ 'seq' => $seq, 'mtime' => time() ];
        $where = [ 'shopid = ?' => $shopId, 'seq < ?' => $seq ];
        $ret = $this->dbConnection->update($this->tableName, $data, $where);
        if ($ret === 0) {
            $this->updateMtime($shopId);
        }
        return !!$ret;
    }

    public function load($shopId)
    {
        $select = $this->dbConnection->select()->from($this->tableName)->where('shopid = ?', $shopId);
        $row = $this->dbConnection->fetchRow($select);
        if (!$row) {
            return false;
        }

        return [ 'shopid' => $row['shopid'], 'seq' => $row['seq'], 'mtime' => $row['mtime'] ];
    }

}
