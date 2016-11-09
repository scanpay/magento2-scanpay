<?php

namespace Scanpay\PaymentModule\Model;

class ScanpayClientFactory
{
    protected $_objectManager;

    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager)
    {
        $this->_objectManager = $objectManager;
    }

    public function create($data = [])
    {
        return $this->_objectManager->create('\Scanpay\PaymentModule\Model\ScanpayClient', ['data' => $data]);
    }
}
