<?php

namespace Scanpay\PaymentModule\Controller\Payment;

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

    private function report_error($msg, $code, $logmsg='_msg') {
        if (!empty($logmsg)) {
            if ($logmsg === '_msg') {
                $logmsg = $msg;
            }
            $this->logger->error($logmsg);
        }
        $this->getResponse()
            ->setStatusCode($code)
            ->setContent(json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function execute()
    {
        $req = $this->getRequest();
        $reqBody = $req->getContent();
        $apikey = trim($this->crypt->decrypt($this->scopeConfig->getValue('payment/scanpaypaymentmodule/apikey')));
        if (empty($apikey)) {
            $this->report_error('Missing API key in payment method configuration', \Magento\Framework\App\Response\Http::STATUS_CODE_500);
            return;
        }

        $localSig = base64_encode(hash_hmac('sha256', $reqBody, $apikey, true));
        $reqSig = $req->getHeader('X-Signature');
        if (empty($reqSig) || !hash_equals($localSig, $reqSig)) {
            $this->report_error('invalid signature', \Magento\Framework\App\Response\Http::STATUS_CODE_403, '');
            return;
        }

        $jsonreq = @json_decode($reqBody, true);
        if ($jsonreq === null) {
            $this->report_error('received invalid json from ping', \Magento\Framework\App\Response\Http::STATUS_CODE_400);
            return;
        }

        if (!isset($jsonreq['seq']) || !is_int($jsonreq['seq']) ||
            !isset($jsonreq['shopid']) || !is_int($jsonreq['shopid'])) {
            $this->report_error('missing or invalid json fields in ping', \Magento\Framework\App\Response\Http::STATUS_CODE_400);
            return;
        }

        $remoteSeq = $jsonreq['seq'];
        $shopId = $jsonreq['shopid'];

        $localSeqObj = $this->sequencer->load($shopId);
        if (!$localSeqObj) {
            $this->sequencer->insert($shopId);
            $localSeqObj = $this->sequencer->load($shopId);
            if (!$localSeqObj) {
                $this->report_error('unable to load scanpay seq', \Magento\Framework\App\Response\Http::STATUS_CODE_500);
                return;
            }
        }

        $localSeq = $localSeqObj['seq'];
        if ($localSeq === $remoteSeq) {
            $this->sequencer->updateMtime($shopId);
        }

        $client = $this->clientFactory->create(['apikey' => $apikey]);

        while (1) {
            try {
                $resobj = $client->seq($localSeq);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->report_error('scanpay client exception: ' . $e->getMessage(), \Magento\Framework\App\Response\Http::STATUS_CODE_500);
                return;
            }
            if (count($resobj['changes']) == 0) {
                break;
            }
            if (!$this->orderUpdater->updateAll($shopId, $resobj['changes'])) {
                $this->report_error('error updating orders with changes', \Magento\Framework\App\Response\Http::STATUS_CODE_500);
                return;
            }
            if (!$this->sequencer->save($shopId, $resobj['seq'])) {
                if ($resobj['seq']!== $localSeq) {
                    $this->report_error('did not save seq', \Magento\Framework\App\Response\Http::STATUS_CODE_500);
                    return;
                }
                break;
            }
            $localSeq = $resobj['seq'];
        }
        $this->getResponse()->setContent(json_encode(['success' => true]));
    }
}
