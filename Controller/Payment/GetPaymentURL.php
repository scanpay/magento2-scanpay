<?php

namespace Scanpay\PaymentModule\Controller\Payment;

use Scanpay\PaymentModule\Model\OrderUpdater;

class GetPaymentURL extends \Magento\Framework\App\Action\Action
{
    private $logger;
    private $checkoutSession;
    private $scopeConfig;
    private $crypt;
    private $urlHelper;
    private $remoteAddress;
    private $clientFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt,
        \Magento\Framework\Url $urlHelper,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Scanpay\PaymentModule\Model\ScanpayClientFactory $clientFactory
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->crypt = $crypt;
        $this->urlHelper = $urlHelper;
        $this->remoteAddress = $remoteAddress;
        $this->clientFactory = $clientFactory;
    }

    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order->getId()) {
            $this->getResponse()->setContent(json_encode(['error' => 'order not found']));
            return;
        }
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
            'orderid'     => $orderid,
            'language'    => $this->scopeConfig->getValue('payment/scanpaypaymentmodule/language'),
            'successurl'  => $baseUrl . '/checkout/onepage/success',
            'autocapture' => (bool)($this->scopeConfig->getValue('payment/scanpaypaymentmodule/autocapture')),
            'items'       => [],
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
        $orderItems = $order->getAllItems();

        foreach ($orderItems as $item) {
            $linetotal = $item->getRowTotalInclTax() - $item->getDiscountAmount();

            if ($linetotal < 0) {
                $this->logger->error('Cannot handle negative price for item');
                $this->getResponse()->setContent(json_encode(['error' => 'internal server error']));
                return;
            }

            $data['items'][] = [
                'name' => $item->getName(),
                'quantity' => (int)$item->getQtyOrdered(),
                'total' => $linetotal . ' ' . $cur,
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
                'total' => $shippingcost . ' ' . $cur,
            ];
        }

        $client = $this->clientFactory->create($apikey);
        try {
            $opts = ['headers' => [ 'X-Cardholder-IP' => $this->remoteAddress->getRemoteAddress() ]];
            $paymenturl = $client->newURL(array_filter($data), $opts);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error('scanpay client exception: ' . $e->getMessage());
            $this->getResponse()->setContent(json_encode(['error' => 'internal server error']));
            return;
        }
        $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        $order->setState($state);
        $order->setStatus($order->getConfig()->getStateDefaultStatus($state));
        $order->setData(OrderUpdater::ORDER_DATA_SHOPID, $shopId);
        $order->save();

        $res = json_encode(['url' => $paymenturl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->getResponse()->setContent($res);
    }
}
