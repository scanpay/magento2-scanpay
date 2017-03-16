<?php
namespace Scanpay\PaymentModule\Block\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
class PingUrl extends \Magento\Config\Block\System\Config\Form\Field
{
    const PINGURL_TEMPLATE = 'config/pingurl.phtml';
    private $scopeConfig;
    private $crypt;
    private $sequencer;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Encryption\Encryptor $crypt,
        \Scanpay\PaymentModule\Model\GlobalSequencer $sequencer,
        array $data = []
    ) {
        parent::__construct($context,$data);
        $this->crypt = $crypt;
        $this->sequencer = $sequencer;
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(static::PINGURL_TEMPLATE);
        }
        return $this;
    }

    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function fmtDeltaTime($dt)
    {
        $minute = 60;
        $hour = $minute * 60;
        $day = $hour * 24;
        if ($dt <= 1) {
            return '1 second ago';
        } else if ($dt < $minute) {
            return (string)$dt . ' seconds ago';
        } else if ($dt < $minute + 30) {
            return '1 minute ago';
        } else if ($dt < $hour) {
            return (string)round((float)$dt / $minute) . ' minutes ago';
        } else if ($dt < $hour + 30 * $minute) {
            return '1 hour ago';
        } else if ($dt < $day){
            return (string)round((float)$dt / $hour) . ' hours ago';
        } else if ($dt < $day + 12 * $hour) {
            return '1 day ago';
        } else {
            return (string)round((float)$dt / $day) . ' days ago';
        }
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $t = time();

        $this->setData([
            'html_id' => $element->getHtmlId(),
            'ping_url' => $this->_urlBuilder->getBaseUrl(['_secure' => true]) . 'scanpay/index/ping',
            'status_class' => 'scanpay--pingurl--never--pinged',
            'ping_dt' => $this->fmtDeltaTime($t),
        ]);

        $apikey = trim($this->crypt->decrypt($this->_scopeConfig->getValue('payment/scanpaypaymentmodule/apikey')));
        if (empty($apikey)) {
            $this->_logger->error('Missing API key in scanpay payment method configuration');
            return $this->_toHtml();
        }

        $shopId = explode(':', $apikey)[0];
        if (!ctype_digit($shopId)) {
            $this->_logger->error('Invalid Scanpay API-key format');
            return $this->_toHtml();
        }

        $originalData = $element->getOriginalData();
        $localSeqObj = $this->sequencer->load($shopId);
        if (!$localSeqObj) {
            $this->_logger->error('unable to load scanpay sequence number');
            return $this->_toHtml();
        }
        $mtime = $localSeqObj['mtime'];
        if ($mtime > $t) {
            $this->_logger->error('last modified time is in the future');
            return $this->_toHtml();
        }

        $status = '';
        if ($t < $mtime + 900) {
            $status = 'ok';
        } else if ($t < $mtime + 3600) {
            $status = 'warning';
        } else if ($mtime > 0) {
            $status = 'error';
        } else {
            $status = 'never--pinged';
        }

        $className = 'scanpay--pingurl--' . $status;
        $this->addData([
            'status_class' => $className,
            'ping_dt' => $this->fmtDeltaTime($t - $mtime),
        ]);
        return $this->_toHtml();
    }
}
