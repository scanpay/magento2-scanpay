<?php

namespace Scanpay\PaymentModule\Model;

class ScanpayPaymentModule extends \Magento\Payment\Model\Method\AbstractMethod {
	protected $_code = 'scanpaypaymentmodule';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = true;
}
?>