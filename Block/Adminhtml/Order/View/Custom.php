<?php

namespace Divido\DividoFinancing\Block\Adminhtml\Order\View;

class Custom extends \Magento\Backend\Block\Template
{
    private $helper;

    private $coreRegistry;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Divido\DividoFinancing\Helper\Data $helper,
        array $data = []
    ) {

        $this->helper = $helper;
        $this->coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    public function getOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }

    public function getDividoInfo()
    {
        $info = null;
        $order = $this->getOrder();

        if ($lookup = $this->helper->getLookupForOrder($order)) {
            $info = $lookup;
        }

        return $info;
    }
}
