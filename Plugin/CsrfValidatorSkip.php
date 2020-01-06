<?php
namespace Scanpay\PaymentModule\Plugin;
class CsrfValidatorSkip
{
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        $request,
        $action
    ) {
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        if ($path === '/index.php/scanpay/payment/ping' || $path === '/scanpay/payment/ping') {
            return;
        }
        $proceed($request, $action);
    }
}
