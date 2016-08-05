<?php

namespace Scanpay\PaymentModule\Model;

class ScanpayClient {
    private $apikey;
    private $host;
    public function __construct($arg) {
        $this->apikey = $arg['apikey'];
        $this->host = $arg['host'];
    }

    public function GetPaymentURL($data) {
        /* Create a curl request towards the api endpoint */
        $ch = curl_init('https://' . $this->{'host'} . '/v1/new');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_USERPWD, $this->{'apikey'});
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

        $result = curl_exec($ch);
        if ($result === FALSE) {
            $errstr = 'unknown error';
            if($errno = curl_errno($ch)) {
                $errstr = curl_strerror($errno);
            }
            curl_close($ch);
            throw new \Exception('curl_exec - ' . $errstr);
        }
        /* Retrieve the http status code */
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200) {
            throw new \Exception('non-200 response code: ' . $httpcode);
        }
        /* Attempt to decode the json response */
        $jsonres = @json_decode($result);
        if ($jsonres === null) {
            throw new \Exception('unable to json-decode response');
        }
        /* Extract the expected json fields */
        $url = $jsonres->{'url'};
        $jsonerr = $jsonres->{'error'};
        /* Check if error field is present */
        if (isset($jsonerr)) {
            throw new \Exception('server returned error: ' . $jsonerr);
        }
        /* Check the existence of the server and the payid field */
        if(!isset($url)) {     
            throw new \Exception('missing json fields in server response');
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            throw new \Exception('invalid url in server response');
        }
        /* Generate the payment URL link from the server and payid */
        return $url;
    }
}