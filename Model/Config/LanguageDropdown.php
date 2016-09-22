<?php

namespace Scanpay\PaymentModule\Model\Config;

class LanguageDropdown implements \Magento\Framework\Option\ArrayInterface
{ 
    public function toOptionArray()
    {
        return [
            ''   => 'Automatic',
            'da' => 'Danish',
            'en' => 'English',
        ];
    }
}