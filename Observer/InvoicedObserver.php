<?php
namespace Divido\DividoFinancing\Observer;

use Divido\DividoFinancing\Helper\Data;
use Magento\Framework\Event\ObserverInterface;

class InvoicedObserver implements ObserverInterface
{
    public $helper;
    public function __construct(
        Data $helper
    ) {
    
        $this->helper = $helper;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getInvoice()->getOrder();
        //TODO - Double test
        $code  = $order->getPayment()->getMethodInstance()->getCode();
        if ($code == Data::PAYMENT_METHOD) {
            return $this->helper->updateInvoiceStatus($order);
        }
    }
}
