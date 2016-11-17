<?php

namespace Scanpay\PaymentModule\Model;

use \Magento\Framework\Exception\LocalizedException;

class ScanpayClient
{
    const HOST = 'api.scanpay.dk';
    private $clientFactory;
    private $apikey;

    public function __construct(
        \Magento\Framework\HTTP\ZendClientFactory $clientFactory,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        $data
    ) {
        $this->clientFactory = $clientFactory;
        $this->moduleResource = $moduleResource;
        $this->apikey = $data['apikey'];
    }

    protected function req($url, $data, $opts = [])
    {
        $version = $this->moduleResource->getDbVersion('Scanpay_PaymentModule');

        $client = $this->clientFactory->create();
        $config = [
           'adapter'      => 'Zend\Http\Client\Adapter\Curl',
           'curloptions'  => [
                CURLOPT_RETURNTRANSFER => true,
            ],
           'maxredirects' => 0,
           'keepalive'    => true,
           'timeout'      => 30,
        ];
        $client->setConfig($config);
        $headers = [
            'Authorization'       => 'Basic ' . base64_encode($this->apikey),
            'X-Shop-Plugin'       => 'magento2/' . $version,
        ];
        if (isset($opts['cardholderIP'])) {
            $headers = array_merge($headers, [ 'X-Cardholder-Ip: ' . $opts['cardholderIP'] ]);
        }

        $client->setHeaders($headers);
        $client->setUri('https://' . SELF::HOST . $url);
        if (is_null($data)) {
            $client->setMethod(\Zend\Http\Request::METHOD_GET);
        } else {
            $client->setMethod(\Zend\Http\Request::METHOD_POST);
            $client->setRawData(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $client->setEncType('application/json');
        }

        $res = $client->request();
        $code = $res->getStatus();
        if ($code !== 200) {
            if ($code === 403) {
                throw new LocalizedException(__('Invalid API-key'));
            }
            throw new LocalizedException(__('Unexpected http code: %1 from %2', $code, $url));
        }

        /* Attempt to decode the json response */
        $resobj = @json_decode($res->getBody(), true);
        if ($resobj === null) {
            throw new LocalizedException(__('unable to json-decode response'));
        }

        /* Check if error field is present */
        if (isset($resobj['error'])) {
            throw new LocalizedException(__('server returned error: %1', $resobj['error']));
        }

        return $resobj;
    }

    public function getPaymentURL($data, $opts = [])
    {
        $resobj = $this->req('/v1/new', $data, $opts);
        /* Check the existence of the server and the payid field */
        if (!isset($resobj['url'])) {
            throw new LocalizedException(__('missing json fields in server response'));
        }

        if (filter_var($resobj['url'], FILTER_VALIDATE_URL) === false) {
            throw new LocalizedException(__('invalid url in server response'));
        }

        /* Generate the payment URL link from the server and payid */
        return $resobj['url'];
    }

    public function getUpdatedTransactions($seq)
    {
        $resobj = $this->req('/v1/seq/' . $seq, null, null);
        if (!isset($resobj['seq']) || !is_int($resobj['seq']) ||
            !isset($resobj['changes']) ||!is_array($resobj['changes'])) {
            throw new LocalizedException(__('missing json fields in server response'));
        }

        return $resobj;
    }
}
