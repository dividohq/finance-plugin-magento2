<?php

namespace Divido\DividoFinancing\Block;

class Footer extends \Magento\Framework\View\Element\Template
{
    private $helper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Divido\DividoFinancing\Helper\Data $helper
    ) {
        $this->helper = $helper;
        parent::__construct($context);
    }

    public function getCalcConfApiUrl()
    {
        return $this->helper->getCalcConfApiUrl();
    }
}
