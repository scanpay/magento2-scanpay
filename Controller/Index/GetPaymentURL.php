<?php

namespace Scanpay\PaymentModule\Controller\Index;

use Scanpay\PaymentModule\Model\Money;
use Scanpay\PaymentModule\Model\OrderUpdater;

class GetPaymentURL extends \Magento\Framework\App\Action\Action
{
    private $order;
    private $logger;
    private $scopeConfig;
    private $crypt;
    private $urlHelper;
    private $remoteAddress;
    private $clientFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt,
        \Magento\Framework\Url $urlHelper,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Scanpay\PaymentModule\Model\ScanpayClientFactory $clientFactory
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->order = $order;
        $this->scopeConfig = $scopeConfig;
        $this->crypt = $crypt;
        $this->urlHelper = $urlHelper;
        $this->remoteAddress = $remoteAddress;
        $this->clientFactory = $clientFactory;
    }

    public function execute()
    {
        $order = $this->order->load($this->getRequest()->getParam('orderid'));
        if (!$order->getId()) {
            $this->getResponse()->setContent(json_encode(['error' => 'order not found']));
            return;
        }

        $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        $order->setState($state);
        $order->setStatus($order->getConfig()->getStateDefaultStatus($state));        
        $order->save();

        $apikey = trim($this->crypt->decrypt($this->scopeConfig->getValue('payment/scanpaypaymentmodule/apikey')));
        if (empty($apikey)) {
            $this->getResponse()->setContent(json_encode(['error' => 'missing api-key']));
            return;
        }

        $shopId = explode(':', $apikey)[0];
        if (!ctype_digit($shopId)) {
            $this->getResponse()->setContent(json_encode(['error' => 'invalid api-key']));
            return;
        }

        $shopId = (int)$shopId;

        $orderid = $order->getIncrementId();
        $baseUrl = rtrim($this->urlHelper->getBaseUrl(), '/');
        $data = [
            'orderid'    => $orderid,
            'language'   => $this->scopeConfig->getValue('payment/scanpaypaymentmodule/language'),
            'successurl' => $baseUrl . '/checkout/onepage/success',
            'items'      => [],
        ];
        $billaddr = $order->getBillingAddress();
        $shipaddr = $order->getShippingAddress();
        if (!empty($billaddr)) {
            $data['billing'] = array_filter([
                'name'    => $billaddr->getName(),
                'email'   => $billaddr->getEmail(),
                'phone'   => preg_replace('/\s+/', ' ', $billaddr->getTelephone()),
                'address' => $billaddr->getStreet(),
                'city'    => $billaddr->getCity(),
                'zip'     => $billaddr->getPostcode(),
                'country' => $billaddr->getCountryId(),
                'state'   => $billaddr->getRegion(),
                'company' => $billaddr->getCompany(),
                'vatin'   => $billaddr->getVatId(),
                'gln'     => '',
            ]);
        }

        if (!empty($shipaddr)) {
            $data['shipping'] = array_filter([
                'name'    => $shipaddr->getName(),
                'email'   => $shipaddr->getEmail(),
                'phone'   => preg_replace('/\s+/', ' ', $shipaddr->getTelephone()),
                'address' => $shipaddr->getStreet(),
                'city'    => $shipaddr->getCity(),
                'zip'     => $shipaddr->getPostcode(),
                'country' => $shipaddr->getCountryId(),
                'state'   => $shipaddr->getRegion(),
                'company' => $shipaddr->getCompany(),
            ]);
        }

        /* Add ordered items to data */
        $cur = $order->getOrderCurrencyCode();
        $orderItems = $this->order->getAllItems();

        foreach ($orderItems as $item) {
            $itemprice = $item->getPrice() + ($item->getTaxAmount() -
                $item->getDiscountAmount()) / $item->getQtyOrdered();

            if ($itemprice < 0) {
                $this->logger->error('Cannot handle negative price for item');
                $this->getResponse()->setContent(json_encode(['error' => 'internal server error']));
                return;
            }

            $data['items'][] = [
                'name' => $item->getName(),
                'quantity' => (int)$item->getQtyOrdered(),
                'price' => (new Money($itemprice, $cur))->print(),
                'sku' => $item->getSku(),
            ];
        }

        $shippingcost = $order->getShippingAmount() + $order->getShippingTaxAmount() -
            $order->getShippingDiscountAmount();

        if ($shippingcost > 0) {
            $method = $order->getShippingDescription();
            $data['items'][] = [
                'name' => isset($method) ? $method : 'Shipping',
                'quantity' => 1,
                'price' => (new Money($shippingcost, $cur))->print(),
            ];
        }

        $client = $this->clientFactory->create(['apikey' => $apikey]);
        try {
            $opts = ['cardholderIP' => $this->remoteAddress->getRemoteAddress()];
            $paymenturl = $client->getPaymentURL(array_filter($data), $opts);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error('scanpay client exception: ' . $e->getMessage());
            $this->getResponse()->setContent(json_encode(['error' => 'internal server error']));
            return;
        }

        $order->setData(OrderUpdater::ORDER_DATA_SHOPID, $shopId);
        $order->save();

        $res = json_encode(['url' => $paymenturl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->getResponse()->setContent($res);
    }
}
