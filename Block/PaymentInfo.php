<?php
namespace Scanpay\PaymentModule\Block;
class PaymentInfo extends \Magento\Payment\Block\Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        $transport = parent::_prepareSpecificInformation($transport);
        $payment = $this->getInfo();
        $data = [];
        if ($payment->getLastTransId()) {
            $data[(string) __('Transaction Id')] = $payment->getLastTransId();
        }
        if ($payment->getAmountAuthorized()) {
            $auth = $payment->getAmountAuthorized();
            $tot = $payment->getOrder()->getGrandTotal();
            $fmtAuth = $payment->formatPrice($auth);
            $fmtTot = $payment->formatPrice($tot);
            if ($auth < $tot) {
                $data[(string) __('Partially Authorized')] = __('%1 of %2', $fmtAuth, $fmtTot);
            } else {
                $data[(string) __('Authorized')] = $fmtAuth;
            }

            $paid = $payment->getAmountPaid();
            if (!$paid) { $paid = 0; }
            $data[(string) __('Captured')] = $payment->formatPrice($paid);

            $ref = $payment->getAmountRefunded();
            if ($ref && $ref > 0) {
                $data[(string) __('Refunded')] = $payment->formatPrice($ref);
            }
        }
        return $transport->addData(array_merge($data, $transport->getData()));
    }
}
