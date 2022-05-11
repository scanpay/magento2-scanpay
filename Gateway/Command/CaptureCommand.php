<?php
namespace Scanpay\PaymentModule\Gateway\Command;

/**
 * Class CaptureStrategyCommand
 * @SuppressWarnings(PHPMD)
 */
class CaptureCommand implements \Magento\Payment\Gateway\CommandInterface
{
    protected $orderRepository;
    protected $_scopeConfig;
    protected $crypt;
    protected $logger;
    protected $clientFactory;
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\Encryptor $crypt,
        \Psr\Log\LoggerInterface $logger,
        \Scanpay\PaymentModule\Model\ScanpayClientFactory $clientFactory
    )
    {
        $this->orderRepository = $orderRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->crypt = $crypt;
        $this->logger = $logger;
        $this->clientFactory = $clientFactory;
    }

    public function execute(array $commandSubject)
    {
        $paymentObj = $commandSubject['payment'];
        $order = $this->orderRepository->get($paymentObj->getOrder()->getId());
        $payment = $paymentObj->getPayment();
        /* Get transaction id */
        $trn = $payment->getAuthorizationTransaction();
        $trnId = $trn->getTxnId();
        $apikey = trim($this->crypt->decrypt($this->_scopeConfig->getValue('payment/scanpaypaymentmodule/apikey')));
        $client = $this->clientFactory->create($apikey);
        $data = [
            'total' => "{$commandSubject['amount']} {$order->getOrderCurrencyCode()}",
            'index' => (int)$order->getData(\Scanpay\PaymentModule\Model\OrderUpdater::ORDER_DATA_NACTS),
        ];
        try {
            $client->capture($trnId, $data);
        } catch (\Exception $e) {
            $this->logger->error('capture failed: ' . $e->getMessage());
            throw new \Magento\Payment\Gateway\Command\CommandException( __('Capture failed: ' . $e->getMessage()));
        }
    }
}
