<?php

namespace Divido\DividoFinancing\Block\Product\View;

class Widget extends \Magento\Catalog\Block\Product\AbstractProduct
{
    private $helper;
    private $catHelper;

    const ALL_PRODUCTS = 'products_all';
    const SELECTED_PRODUCTS = 'products_selected';
    const THRESHOLD_PRODUCTS = 'products_price_threshold';

    public function __construct(
        \Divido\DividoFinancing\Helper\Data $helper,
        \Magento\Catalog\Block\Product\Context $context,
        array $data = []
    ) {

        $this->helper    = $helper;
        $this->catHelper = $context->getCatalogHelper();

        parent::__construct($context, $data);
    }

    public function getFinancePlatform()
    {
        $env = $this->helper->getPlatformEnv();
        return $env;
    }

    public function getProductPlans()
    {
        $plans = $this->helper->getLocalPlans($this->getProduct()->getId());

        $plans = array_map(function ($plan) {
            return $plan->id;
        }, $plans);

        $plans = implode(',', $plans);

        return $plans;
    }

    public function getProductId()
    {
        return $this->getProduct()->getId();
    }

    public function getLanguageOverride()
    {
        return (is_null($this->helper->getWidgetLanguage()))
            ? ""
            : 'data-language="'.$this->helper->getWidgetLanguage().'"';
    }

    public function getProductAmount()
    {
        $product = $this->getProduct();
        $price = $product->getFinalPrice();
        $priceIncVat = $this->catHelper->getTaxPrice($product, $price, true);

        return $priceIncVat * 100;
    }

    public function loadWidget()
    {
        return $this->helper->getActive();
    }

    public function showWidget()
    {
        $threshold = $this->getThreshold();
        if ($threshold === false || $this->getProductAmount() < $threshold) {
            return false;
        } else {
            return true;
        }
    }

    public function getThreshold()
    {
        $selection = $this->helper->getProductSelection();

        switch ($selection) {
            case self::ALL_PRODUCTS:
                $threshold = 0;
                break;
            case self::SELECTED_PRODUCTS:
                $product = $this->getProduct();
                $plans = $this->helper->getLocalPlans($product->getId());
                $threshold = (count($plans)>0) ? true : false;
                break;
            case self::THRESHOLD_PRODUCTS:
                $threshold = (empty($this->helper->getPriceThreshold())) ? 0 : (int)($this->helper->getPriceThreshold() * 100);
                break;
        }
        return $threshold;

    }

    public function getButtonText()
    {
        return $this->helper->getWidgetButtonText();
    }

    public function getFootnote()
    {
        return $this->helper->getWidgetFootnote();
    }

    public function getMode()
    {
        return $this->helper->getWidgetMode();
    }
}
