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
    private $orderUpdater;
    private $client;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt,
        \Scanpay\PaymentModule\Model\GlobalSequencer $sequencer,
        \Scanpay\PaymentModule\Model\OrderUpdater $orderUpdater,
        \Scanpay\PaymentModule\Model\ScanpayClient $client
    ) {
        parent::__construct($context);
        $this->logger = $logger;
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

        $remoteSeq = $jsonreq['seq'];
        if (!isset($remoteSeq) || !is_int($remoteSeq)) { return; }
        $localSeqObj = $this->sequencer->load();
        if (!$localSeqObj) {
            $this->logger->error('unable to load scanpay sequence number');
            return;
        }
    
        $localSeq = $localSeqObj['seq'];

        while ($localSeq < $remoteSeq) {
            $this->getResponse()->setContent('doreq');
            /* Do req */
            try {
                $resobj = $this->client->getUpdatedTransactions($localSeq);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->logger->error('scanpay client exception: ' . $e->getMessage());
                return;
            }
            $localSeq = $resobj['seq'];
            if (!$orderUpdater->updateAll($remoteSeq, $resobj['changes'])) {
                $this->logger->error('error updating orders with Scanpay changes');
                return;
            }
            if (!$this->sequencer->save($localSeq)) {
                return;
            }
        }

    }
}
