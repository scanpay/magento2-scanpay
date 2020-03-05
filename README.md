# magento2-scanpay

Scanpay module for Magento 2. This module is developed and maintained by Scanpay ApS in Copenhagen. You can always e-mail us at [help@scanpay.dk](mailto:help@scanpay.dk) or chat with us on `irc.scanpay.dk:6697` or `#scanpay` at Freenode ([webchat](https://webchat.freenode.net?randomnick=1&channels=scanpay&prompt=1)).

## Installation

You need PHP version >= 5.6 with php-curl enabled. The package is published at [Packagist](https://packagist.org/packages/scanpay/magento2). You can install the library via [Composer](http://getcomposer.org/). Please follow the [installation guide](https://docs.scanpay.dk/modules/magento-2).

## Folder hierarchy

### /view
The `/view` folder defines the layout and javascript of the module.
The actual HTML form presented for the customer can be found at `/view/frontend/web/template/payment/form.html`.

`/view/frontend/web/js/view/payment/method-renderer/scanpaypaymentmodule.js` defines the javascript which is run when the customer places an order.

This javascript creates the order and makes an AJAX request to the `/Controller/Payment/GetPaymentURL.php` containing the order id.

### /Controller
The `/Controller` folder defines the publically served PHP pages.
It's served at an URL defined by `/etc/frontend/routes.xml`.
`/Controller/Payment/GetPaymentURL.php` gathers order parameters from Magento and uses `/Model/ScanpayClient.php` to send an request to the Scanpay API.
Upon success it will return a payment url which the webshop customer should be redirected to.

### /Model
The `/Model` folder defines utility classes used by other PHP files.
For instance it containts `ScanpayClient.php` which does the actual http request to the Scanpay API using PHP-curl.

### /etc
The `/etc` folder defines module dependencies, module configuration, module naming and the layout of the admin panel module configuration.
