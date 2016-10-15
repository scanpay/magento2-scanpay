<?php

namespace Scanpay\PaymentModule\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;

final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'scanpaypaymentmodule';
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper
    ) {
        $this->method = $paymentHelper->getMethodInstance(self::CODE);
    }
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'test' =>  'test123',
                ]
            ]
        ];
    }
}
