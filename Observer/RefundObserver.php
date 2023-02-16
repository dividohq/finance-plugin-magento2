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

        $layout  = $observer->getData('layout');
        
        throw new \Exception("Nope");
        return false;
        /*
        if ($code == 'divido_financing') {
            // check refund amount falls within the bounds
            // if not, update the layout to show a warning
            try{
                $this->helper->autoRefund($order);
            } catch (\Exception $e) {

                return false;
            }
        }*/
    }
}
