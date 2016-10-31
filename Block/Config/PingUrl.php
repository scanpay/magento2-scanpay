<?php
namespace Scanpay\PaymentModule\Block\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
class PingUrl extends \Magento\Config\Block\System\Config\Form\Field
{
    const PINGURL_TEMPLATE = 'config/pingurl.phtml';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context,$data);
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

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $originalData = $element->getOriginalData();
        $this->addData([
            'html_id' => $element->getHtmlId(),
            'ping_url' => $this->_urlBuilder->getBaseUrl(['_secure' => true]) . 'scanpay/index/pingHandler',
            'status' => 'OK',
        ]);
        return $this->_toHtml();
    }
}
 