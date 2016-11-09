<?php

namespace Scanpay\PaymentModule\Model;

use \Magento\Framework\Exception\LocalizedException;
use Scanpay\PaymentModule\Model\Money;

class OrderUpdater
{
    const ORDER_DATA_SHOPID = 'scanpay_shopid';
    const ORDER_DATA_SEQ = 'scanpay_seq';

    private $logger;
    private $order;
    private $orderSender;
    private $trnBuilder;
    
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\OrderNotifier $orderNotifier,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $trnBuilder
    ) {
        $this->logger = $logger;
        $this->order = $order;
        $this->orderNotifier = $orderNotifier;
        $this->trnBuilder = $trnBuilder;
    }

    public function dataIsValid($data)
    {
        return isset($data['id']) && isset($data['totals'])
            && isset($data['totals']['authorized']);
    }

    public function notifyCustomer($order) {
        if (!$order->getEmailSent()) {
            try {
                $this->orderNotifier->notify($order);
            } catch (LocalizedException $e) {
                $this->logger->error('Unable to send order confirmation email for order' .
                    $order->getIncrementId() . ', Exception message: ' . $e->getMessage());
            }
        }

    }

    public function update($shopId, $seq, $data)
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

        $trnId = $data['id'];
        /* Ignore transactions without order ids */
        if (!isset($data['orderid'])) {
            $this->logger->error('Received transaction #' . $trnId . ' without orderid');
            return true;
        }

        $order = $this->order->load($data['orderid']);
        /* If order is not in system, ignore it */
        if (!$order->getId()) {
            $this->logger->error('Order #' . $data['orderid'] . ' not in system');
            return true;
        }

        $orderShopId = (int)$order->getData(self::ORDER_DATA_SHOPID);
        $oldSeq = (int)$order->getData(self::ORDER_DATA_SEQ);
        /*
        $extAttr = $this->order->getExtensionAttributes();
        if ($extAttr === null) {
            $this->logger->error('Missing Scanpay extension attributes');
            return true;
        }

        $orderShopId = $extAttr->getScanpayShopid();
        */
        if ($shopId !== $orderShopId) {
            $this->logger->error('type' . gettype($shopId) . ' ' . gettype($orderShopId));
            $this->logger->error('Order #' . $data['orderid'] . ' shopid (' .
                $orderShopId . ') does not match current shopid (' .
                $shopId . '()');
            return true;
        }
        /*
        $oldSeq = $extAttr->getScanpaySeq();*/
        if ($oldSeq >= $seq) {
            return true;
        }

        $state = \Magento\Sales\Model\Order::STATE_PROCESSING;

        $payment = $order->getPayment();
        $auth = $data['totals']['authorized'];

        /* Avoid exceptions if the transaction id somehow already is created */
        if ($payment->getTransactionId() !== null) {
            $transaction = $this->trnBuilder->setPayment($payment)->setOrder($order)
                ->setTransactionId($trnId)->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);
            $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1.', $auth));
            $transaction->save();
        } else {
            $order->addStatusHistoryComment(__('The authorized amount is %1.', $auth));
        }

        $payment->setAmountAuthorized((new Money($auth))->number());
        $payment->setParentTransactionId(null);

        $payment->setLastTransId($trnId);
        $payment->setTransactionId($trnId);

        $order->setState($state);
        $order->setStatus($order->getConfig()->getStateDefaultStatus($state));
        $order->setData(self::ORDER_DATA_SEQ, $seq);

        $payment->save();
        $order->save();

        /* Send email AFTER payment has been set */
        $this->notifyCustomer($order);
        return true;
    }

    public function updateAll($shopId, $seq, $dataArr)
    {
        foreach ($dataArr as $data) {
            if (!$this->update($shopId, $seq, $data)) {
                return false;
            }
        }
        return true;
    }
}
