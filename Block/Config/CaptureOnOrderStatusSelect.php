<?php

namespace Scanpay\PaymentModule\Block\Config;

class CaptureOnOrderStatusSelect extends \Magento\Framework\View\Element\Html\Select
{
    protected $statusCollection;
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\Status\Collection $statusCollection,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->statusCollection = $statusCollection;
    }

    public function setInputName($value)
    {
        return $this->setName($value);
    }

    public function getOptions()
    {
        return $this->statusCollection->toOptionArray();
    }
}
