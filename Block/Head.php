<?php

namespace Divido\DividoFinancing\Block;

use Exception;

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

    public function getPlatformEnv()
    {
        try{
            return $this->helper->getPlatformEnv();
        }catch (Exception $e){
            // In case the plugin is not configured properly we should fail a bit more graciously
            return '[unknown]';
        }
    }

    public function getBrandingJson()
    {
        return $this->helper->getBranding();
    }
    
    public function getCalcConfApiUrl()
    {
        return $this->helper->getCalcConfApiUrl();
    }

    public function getDividoKey()
    {
        return $this->helper->getDividoKey();
    }

    public function getScriptUrl()
    {
        return $this->helper->getScriptUrl();
    }

    public function getCalcLinkText()
    {
        return $this->helper->getWidgetButtonText();
    }
}
