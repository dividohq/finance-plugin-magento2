<?php
namespace Divido\DividoFinancing\Observer;

use Magento\Framework\Event\ObserverInterface;

class RefundObserver implements ObserverInterface
{
    public $helper;
    public function __construct(
        \Divido\DividoFinancing\Helper\Data $helper
    ) {
    
        $this->helper = $helper;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getCreditmemo()->getOrder();
        
        $code  = $order->getPayment()->getMethodInstance()->getCode();
        if ($code == 'divido_financing') {
            return $this->helper->autoRefund($order);
        }
    }
}
