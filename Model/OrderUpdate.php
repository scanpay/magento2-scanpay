<?php

namespace Scanpay\PaymentModule\Model;

use \Magento\Framework\Exception\LocalizedException;

class OrderUpdater
{
    private $order;
    private $orderSender;
    private $trnBuilder;

    public function __construct(
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $trnBuilder
    ) {
        $this->order = $order;
        $this->$orderSender = $orderSender;
        $this->$trnBuilder = $trnBuilder;
    }

    public function loadById($orderId) {
        $this->order->load($orderId);
        if (!$order->getId()) {
            return FALSE;
        }
        return $this;
    }

    public function update($id, $data)
    {
        if (!$order->getEmailSent()) {
            $this->orderSender->send($order);
        }
        $payment = $order->getPayment();
        $payment->setLastTransId($id);
        $payment->setTransactionId($id);

        $transaction = $this->$trnBuilder->setPayment($payment)->setOrder($order)
            ->setTransactionId($paymentData['id'])->setFailSafe(true)
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);

        $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1.', $data['totals']['authorized']));
        $payment->setParentTransactionId(null);
        $payment->save();
        $order->save();
        
    }
}
