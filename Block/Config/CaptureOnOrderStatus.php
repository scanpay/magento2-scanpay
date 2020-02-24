<?php
namespace Scanpay\PaymentModule\Block\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
class CaptureOnOrderStatus extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    protected $_block;
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context,$data);
    }

    protected function _prepareToRender()
    {
        $this->addColumn('status', ['label' => __('Status'), 'class' => 'required-entry', 'renderer' => $this->_getGroupRenderer()]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add status');
    }

    protected function _getGroupRenderer()
    {
        if (!$this->_block) {
            $this->_block = $this->getLayout()->createBlock(
                \Scanpay\PaymentModule\Block\Config\CaptureOnOrderStatusSelect::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->_block->setClass('attributname_group_select');
        }
        return $this->_block;
    }

     protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
     {
         $optionExtraAttr = [];
         $optionExtraAttr['option_' . $this->_getGroupRenderer()->calcOptionHash($row->getData('status'))] =
             'selected="selected"';
         $row->setData(
             'option_extra_attrs',
             $optionExtraAttr
         );
     }

}
