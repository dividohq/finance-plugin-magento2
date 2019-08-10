<?php

namespace Divido\DividoFinancing\Block;

class Head extends \Magento\Framework\View\Element\Template
{
    private $helper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Divido\DividoFinancing\Helper\Data $helper
    ) {
        $this->helper = $helper;
        parent::__construct($context);
    }

    public function getScriptUrl()
    {
        return $this->helper->getScriptUrl();
    }

    public function getDividoKey()
    {
        return $this->helper->getDividoKey();
    }

    public function getPlatformEnv()
    {
        return $this->helper->getPlatformEnv();
    }
}
