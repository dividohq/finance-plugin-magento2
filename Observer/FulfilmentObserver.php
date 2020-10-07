<?php

namespace Divido\DividoFinancing\Observer;

use Magento\Framework\Event\ObserverInterface;

class FulfilmentObserver implements ObserverInterface
{
    public $helper;

    public function __construct(
        \Divido\DividoFinancing\Helper\Data $helper
    ) {

        $this->helper = $helper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getOrder();

        return $this->helper->autoFulfill($order);
    }
}
