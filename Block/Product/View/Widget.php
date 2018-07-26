<?php

namespace Divido\DividoFinancing\Block\Product\View;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\AwareInterface as ProductAwareInterface;

class Widget extends AbstractProduct implements ProductAwareInterface
{
    private $helper;
    private $catHelper;
    private $product;

    public function __construct(
        \Divido\DividoFinancing\Helper\Data $helper,
        \Magento\Catalog\Block\Product\Context $context,
        array $data = []
    ) {

        $this->helper    = $helper;
        $this->catHelper = $context->getCatalogHelper();

        parent::__construct($context, $data);
    }

    public function setProduct(ProductInterface $product)
    {
        $this->product = $product;
        return $this;
    }

    public function getProduct()
    {
        return $this->product;
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

    public function getAmount()
    {
        $product = $this->getProduct();
        $price = $product->getFinalPrice();
        $priceIncVat = $this->catHelper->getTaxPrice($product, $price, true);

        return $priceIncVat;
    }
}
