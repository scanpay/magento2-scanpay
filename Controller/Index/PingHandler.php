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
    private $client;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt,
        \Scanpay\PaymentModule\Model\GlobalSequencer $sequencer,
        \Scanpay\PaymentModule\Model\OrderUpdater $orderUpdater,
        \Scanpay\PaymentModule\Model\ScanpayClient $client
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->crypt = $crypt;
        $this->sequencer = $sequencer;
        $this->client = $client;
    }

    public function execute()
    {
        $req = $this->getRequest();
        $reqBody = $req->getContent();

        $apikey = trim($this->crypt->decrypt($this->scopeConfig->getValue('payment/scanpaypaymentmodule/apikey')));
        if (empty($apikey)) {
            $this->logger->error('Missing API key in scanpay payment method configuration');
            return;
        }

        $localSig = base64_encode(hash_hmac('sha256', $reqBody, $apikey, true));
        if ($localSig !== $req->getHeader('X-Signature')) { 
            return;
        }

        $jsonreq = @json_decode($reqBody, true);
        if ($jsonreq === null) {
            $this->logger->error('Received invalid json from Scanpay server');
            return;
        }

        if (!isset($jsonreq['seq'])) { return; }
        $remoteSeq = $jsonreq['seq'];
        $localSeq = $this->sequencer->load();
        if (!$localSeq) {
            $this->logger->error('unable to load scanpay sequence number');
            return;
        }

        return;
        while ($localSeq < $remoteSeq) {
            /* Do req */
            $resobj = $client->getUpdatedTransactions($localSeq);
            $localSeq = $resobj['seq'];
            if (!$orderUpdater->updateAll($jsonreq['seq'], $resobj['changes'])) {
                $this->logger->error('error updating orders with Scanpay changes');
                return;
            }
            if (!$this->sequencer->save($localSeq)) {
                return;
            }
        }

    }
}
