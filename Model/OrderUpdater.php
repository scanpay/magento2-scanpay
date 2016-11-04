<?php

namespace Scanpay\PaymentModule\Model;

use \Magento\Framework\Exception\LocalizedException;

class OrderUpdater
{
    private $logger;
    private $order;
    private $orderSender;
    private $trnBuilder;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $trnBuilder
    ) {
        $this->logger = $logger;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->trnBuilder = $trnBuilder;
    }

    public function dataIsValid($data)
    {
        return isset($data['trnid']) && isset($data['orderid']) &&
            isset($data['totals']) && isset($data['totals']['authorized']);
    }

    public function update($seq, $data)
    {
        /* Ignore errornous transactions */
        if (isset($data['error'])) {
            $this->logger->error('Received error entry in seq upater: ' . $data['error']);
            return true;
        }

        if (!$this->dataIsValid($data)) {
            $this->logger->error('Received invalid order data from Scanpay');
            return false;
        }
        $trnId = $data['trnid'];
        $order = $this->order->load($data['orderid']);
        /* If order is not in system, ignore it */
        if (!$order->getId()) {
            return true;
        }

        if (!$order->getEmailSent()) {
            $this->orderSender->send($order);
        }

        $oldSeq = $order->getScanpaySeq();
        if (isset($oldSeq) && $oldSeq >= $seq) { return; }

        $payment = $order->getPayment();
        $payment->setLastTransId($trnId);
        $payment->setTransactionId($trnId);

        $transaction = $this->trnBuilder->setPayment($payment)->setOrder($order)
            ->setTransactionId($paymentData['id'])->setFailSafe(true)
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);
        $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1.', $data['totals']['authorized']));
        $payment->setParentTransactionId(null);
        $order->setScanpaySeq($seq);
        $payment->save();
        $order->save();
        return true;
    }

    public function updateAll($seq, $dataArr)
    {
        foreach ($dataArr as $data) {
            if (!$this->update($seq, $data)) {
                return false;
            }
        }
        return true;
    }
}
