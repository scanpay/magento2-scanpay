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

    protected function request($url, $opts = [], $data=null)
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
            throw new LocalizedException(__(explode("\n", $res->getBody())[0]));
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

    public function newURL($data, $opts = [])
    {
        $o = $this->request('/v1/new', $opts, $data);
        if (isset($o['url']) && filter_var($o['url'], FILTER_VALIDATE_URL)) {
            return $o['url'];
        }
        throw new LocalizedException(__('Invalid response from server'));
    }

    public function seq($seqnum, $opts = [])
    {
        if (!is_numeric($seqnum)) {
            throw new LocalizedException(__('seq argument must be an integer'));
        }
        $o = $this->request('/v1/seq/' . $seqnum, $opts);
        if (isset($o['seq']) && is_int($o['seq'])
                && isset($o['changes']) && is_array($o['changes'])) {
            return $o;
        }
        throw new LocalizedException(__('Invalid response from server'));
    }
}
