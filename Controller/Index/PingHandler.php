<?php

namespace Scanpay\PaymentModule\Controller\Index;

use Scanpay\PaymentModule\Model\ScanpayClient;

class PingHandler extends \Magento\Framework\App\Action\Action
{
    private $order;
    private $logger;
    private $scopeConfig;
    private $crypt;
    private $sequencer;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt,
        \Scanpay\PaymentModule\Model\GlobalSequencer $sequencer,
        \Scanpay\PaymentModule\Model\OrderUpdater $orderUpdater
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->crypt = $crypt;
        $this->$seqHandler = $seqHandler;
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

        $jsonreq = @json_decode($reqBody);
        if ($jsonreq === null) {
            $this->getResponse()->setContent(json_encode(['error' => 'invalid json']));
            return;
        }
        if (!isset($jsonres->seq)) { return; }
        $oldSeq = $sequencer->load($jsonreq->seq);
        /* Do stuff if... */
        
        $sequencer->save($jsonreq->seq);
    }
}
