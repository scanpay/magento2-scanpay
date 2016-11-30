<?php

namespace Scanpay\PaymentModule\Controller\Index;

use Scanpay\PaymentModule\Model\ScanpayClient;

class Ping extends \Magento\Framework\App\Action\Action
{
    private $order;
    private $logger;
    private $scopeConfig;
    private $crypt;
    private $sequencer;
    private $orderUpdater;
    private $clientFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt,
        \Scanpay\PaymentModule\Model\GlobalSequencer $sequencer,
        \Scanpay\PaymentModule\Model\OrderUpdater $orderUpdater,
        \Scanpay\PaymentModule\Model\ScanpayClientFactory $clientFactory
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->crypt = $crypt;
        $this->sequencer = $sequencer;
        $this->orderUpdater = $orderUpdater;
        $this->clientFactory = $clientFactory;
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
            $this->logger->error('Received invalid json from Scanpay ping');
            return;
        }

        if (!isset($jsonreq['seq']) || !is_int($jsonreq['seq']) ||
            !isset($jsonreq['shopid']) || !is_int($jsonreq['shopid'])) {
            $this->logger->error('Missing json fields from Scanpay ping');
            return;
        }

        $remoteSeq = $jsonreq['seq'];
        $shopId = $jsonreq['shopid'];

        $localSeqObj = $this->sequencer->load($shopId);
        if (!$localSeqObj) {
            $this->sequencer->insert($shopId);
            $localSeqObj = $this->sequencer->load($shopId);
            if (!$localSeqObj) {
                $this->logger->error('unable to load scanpay sequence number');
                return;
            }
        }

        $localSeq = $localSeqObj['seq'];
        if ($localSeq === $remoteSeq) {
            $this->sequencer->updateMtime($shopId);
        }

        $client = $this->clientFactory->create(['apikey' => $apikey]);

        while ($localSeq < $remoteSeq) {
            try {
                $resobj = $client->getUpdatedTransactions($localSeq);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->logger->error('scanpay client exception: ' . $e->getMessage());
                return;
            }

            $localSeq = $resobj['seq'];
            if (!$this->orderUpdater->updateAll($shopId, $resobj['changes'])) {
                $this->logger->error('error updating orders with Scanpay changes');
                return;
            }

            if (!$this->sequencer->save($shopId, $localSeq)) {
                return;
            }
        }
    }
}
