<?php

namespace Divido\DividoFinancing\Observer;

use Magento\Framework\Event\ObserverInterface;

class InvoicedObserver implements ObserverInterface
{
    public $helper;

    public function __construct(
        \Divido\DividoFinancing\Helper\Data $helper
    ) {

        $this->helper = $helper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getInvoice()->getOrder();
        //TODO - Double test
        $code  = $order->getPayment()->getMethodInstance()->getCode();
        if ($code == 'divido_financing') {
            return $this->helper->updateInvoiceStatus($order);
        }
    }
}
