<?php
namespace Divido\DividoFinancing\Block\Adminhtml\Order\View;

class Creditmemo extends \Magento\Backend\Block\Template
{
    private $helper;
    private $coreRegistry;
    private $context;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Divido\DividoFinancing\Helper\Data $helper,
        array $data = []
    ) {
        $this->context = $context;
        $this->helper = $helper;
        $this->coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    public function getOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }

    public function getBounds()
    {
        $bounds = null;
        $order = $this->getOrder();
        var_dump($order);
        if($order !== null){
            $bounds = $this->helper->getBounds($order);
        }

        return $bounds;
    }
}
