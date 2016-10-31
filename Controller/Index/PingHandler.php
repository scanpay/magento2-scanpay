<?php

namespace Scanpay\PaymentModule\Controller\Index;

use Scanpay\PaymentModule\Model\ScanpayClient;

class PingHandler extends \Magento\Framework\App\Action\Action
{
    private $order;
    private $logger;
    private $scopeConfig;
    private $crypt;
    private $urlHelper;
    private $remoteAddress;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->crypt = $crypt;
    }

    public function execute()
    {
        $req = $this->getRequest();
        $reqBody = $req->getContent();

        $apikey = trim($this->crypt->decrypt($this->scopeConfig->getValue('payment/scanpaypaymentmodule/apikey')));
        if (empty($apikey)) {
            $this->logger->error('Missing API key in scanpay payment method configuration');
            $this->getResponse()->setContent(json_encode(['error' => 'internal server error']));
            return;
        }
        $localSig = base64_encode(hash_hmac('sha256', $reqBody, $apikey, true));
        if ($localSig !== $req->getHeader('X-Signature')) { 
            $this->getResponse()->setContent(json_encode(['error' => 'invalid signature']));
            return;
        }

        $jsonres = @json_decode($reqBody);
        if ($jsonres === null) {
            $this->getResponse()->setContent(json_encode(['error' => 'invalid json']));
            return;
//            throw new LocalizedException(__('unable to json-decode response'));
        }

    }
}
