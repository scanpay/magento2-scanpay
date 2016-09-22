<?php

namespace Scanpay\PaymentModule\Controller\Index;
use Scanpay\PaymentModule\Model\ScanpayClient;
use Scanpay\PaymentModule\Model\Money;

class GetPaymentURL extends \Magento\Framework\App\Action\Action
{
    protected $request;
    protected $order;
    protected $quote;
    protected $checkoutSession;
    protected $countryInformation;
    protected $scopeConfig;
    protected $crypt;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformation,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->order = $order;
        $this->quote = $quote;
        $this->checkoutSession = $checkoutSession;
        $this->countryInformation = $countryInformation;
        $this->scopeConfig = $scopeConfig;
        $this->crypt = $crypt;
    }

    public function execute() {
        $order = $this->order->load($this->request->getParam('orderid'));
        if (!$order->getId()) {
            echo json_encode(['error' => 'order not found'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $orderid = $order->getIncrementId();

        /* Restore shopping cart (i.e. the quote) */
        $quote = $this->quote->loadByIdWithoutStore($order->getQuoteId());
        $quote->setIsActive(1)->setReservedOrderId(null)->save();
        $this->checkoutSession->replaceQuote($quote);

        $billaddr = $order->getBillingAddress();
        $shipaddr = $order->getShippingAddress();

        $data = [
            'orderid'  => $orderid,
            'language' => $this->scopeConfig->getValue('payment/scanpaypaymentmodule/language'),
            'items'    => [],
        ];
        if (!empty($billaddr)) {
            $data['billing'] = array_filter([
                'name'    => $billaddr->getName(),
                'email'   => $billaddr->getEmail(),
                'phone'   => preg_replace('/\s+/', '', $billaddr->getTelephone()),
                'address' => $billaddr->getStreet(),
                'city'    => $billaddr->getCity(),
                'zip'     => $billaddr->getPostcode(),
                'country' => $this->countryInformation->getCountryInfo($billaddr->getCountryId())->getFullNameLocale(),
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
                'phone'   => preg_replace('/\s+/', '', $shipaddr->getTelephone()),
                'address' => $shipaddr->getStreet(),
                'city'    => $shipaddr->getCity(),
                'zip'     => $shipaddr->getPostcode(),
                'country' => $this->countryInformation->getCountryInfo($shipaddr->getCountryId())->getFullNameLocale(),
                'state'   => $shipaddr->getRegion(),
                'company' => $shipaddr->getCompany(),
            ]);
        }

        /* Add ordered items to data */
        $cur = $order->getOrderCurrencyCode();
        $orderItems = $this->order->getAllItems();

        foreach ($orderItems as $item) {
            $itemprice = $item->getPrice() + ($item->getTaxAmount() - $item->getDiscountAmount()) / $item->getQtyOrdered();
            if ($itemprice < 0) {
                error_log('Cannot handle negative price for item');
                echo json_encode(['error' => 'internal server error']);
                return;
            }
            $data['items'][] = [
                'name' => $item->getName(),
                'quantity' => intval($item->getQtyOrdered()),
                'price' => (new Money($itemprice, $cur))->print(),
                'sku' => $item->getSku(),
            ];
        }
        $shippingcost = $order->getShippingAmount() + $order->getShippingTaxAmount() - $order->getShippingDiscountAmount();
        if ($shippingcost > 0) {
            $method = $order->getShippingDescription();
            $data['items'][] = [
                'name' => isset($method) ? $method : 'Shipping',
                'quantity' => 1,
                'price' => (new Money($shippingcost, $cur))->print(),
            ];
        }
        $apikey = trim($this->crypt->decrypt($this->scopeConfig->getValue('payment/scanpaypaymentmodule/apikey')));
        if (empty($apikey)) {
            error_log('Missing API key in scanpay payment method configuration');
            echo json_encode(['error' => 'internal server error']);
            return;
        }
        $client = new ScanpayClient([
            'host' => 'api.scanpay.dk',
            'apikey' => $apikey,
        ]);
        $paymenturl = '';
        try {
            $paymenturl = $client->GetPaymentURL(array_filter($data), ['cardholderIP' => $_SERVER['REMOTE_ADDR']]);
        } catch (\Exception $e) {
            error_log('scanpay client exception: ' . $e->getMessage());
            echo json_encode(['error' => 'internal server error']);
            return;
        }
        /* Empty quote now */
        $quoteItems = $quote->getItemsCollection();
        foreach ($quoteItems as $quoteItem) {
            $quote->removeItem($quoteItem->getId());
        }
        $quote->save();

        $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $order->save();
        echo json_encode(['url' => $paymenturl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

}