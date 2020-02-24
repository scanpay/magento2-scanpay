<?php

namespace Scanpay\PaymentModule\Model;

class ScanpayClientFactory
{
    protected $_objectManager;
    protected $moduleResource;
    protected $productMetadata;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
)
    {
        $this->_objectManager = $objectManager;
        $this->moduleResource = $moduleResource;
        $this->productMetadata = $productMetadata;
    }

    public function create($apikey = '')
    {
        $magentoVersion = $this->productMetadata->getVersion();
        $scanpayVersion = $this->moduleResource->getDbVersion('Scanpay_PaymentModule');
        $opts = [
            'headers' => [
                'X-Shop-Plugin'       => 'magento2/' . $magentoVersion . '/' . $scanpayVersion,
            ],
        ];
        return $this->_objectManager->create('\Scanpay\PaymentModule\Model\ScanpayClient', ['apikey' => $apikey, 'opts' => $opts]);
    }
}
