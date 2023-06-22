<?php
namespace Divido\DividoFinancing\Observer;

use Magento\Framework\Event\ObserverInterface;

class CancelObserver implements ObserverInterface
{
    public $_request;
    public $helper;
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Divido\DividoFinancing\Helper\Data $helper
    ) {
        $this->_request = $request;
        $this->helper = $helper;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $order = $observer->getEvent()->getOrder();

        $params = $this->_request->getParams();

        $reason = (isset($params['pbd_reason'])) ? $params['pbd_reason'] : null;
        
        return $this->helper->autoCancel($order, $reason);
    }
}
