# Scanpay for Magento

We have developed a payment module for [Magento](https://github.com/magento/magento2), that allows you to accept payments in your Magento store via our [API](https://docs.scanpay.dk/). Magento is an open-source e-commerce platform written in PHP and owned by Adobe.

You can always e-mail us at [help@scanpay.dk](mailto:help@scanpay.dk) or chat with us on IRC at libera.chat #scanpay ([webchat](https://web.libera.chat/#scanpay)).

## Installation

You need PHP version >= 5.6 with php-curl enabled. The module is available at [Packagist](https://packagist.org/packages/scanpay/magento2). You can install the module with Composer or by manually uploading the files. Magento recommends that you use Composer. You can find a guide on how to install Composer [here](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos).

1. Navigate to your Magento folder and download the module with Composer:\
`composer require scanpay/magento2`

2. Enable the module and clear the static view files:\
`php bin/magento module:enable Scanpay_PaymentModule --clear-static-content`

3. Register the extension:\
`php bin/magento setup:upgrade`

4. Recompile your Magento store with the new module:\
`php bin/magento setup:di:compile`

5. Verify that the extension is enabled:\
`php bin/magento module:status`


### Configuration

Before you begin, you need to generate an API key in our dashboard ([here](https://dashboard.scanpay.dk/settings/api)). Always keep your API key private and secure.

1. Enter your Magento admin and navigate to `Stores > Configuration > Sales > Payment Methods`.
2. Find the payment method called *"Scanpay"* and enable it.
3. Insert your API key in the *"API-key"* field.
4. Copy the contents of the *"Ping URL"* field, and insert into the *"Ping URL"* field in our dashboard ([here](https://dashboard.scanpay.dk/settings/api/setup?module=magento)).

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
