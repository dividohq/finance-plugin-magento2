<?php
namespace Divido\DividoFinancing\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

class PopupWidget extends Template implements BlockInterface
{
    
    private $helper;
    protected $_template = "widget/popupwidget.phtml";

    public function __construct(
        \Divido\DividoFinancing\Helper\Data $helper,
        \Magento\Catalog\Block\Product\Context $context,
        array $data = []
    ) {
        $this->helper    = $helper;
        parent::__construct($context, $data);
    }

    public function showable()
    {
        if (!empty($this->helper->getApiKey())) {
            return true;
        } else {
            return false;
        }
    }

    public function getFootnote()
    {
        return $this->helper->getWidgetFootnote();
    }

    public function getButtonText()
    {
        return $this->helper->getWidgetButtonText();
    }

    public function getDividoKey()
    {
        return $this->helper->getDividoKey();
    }

}
