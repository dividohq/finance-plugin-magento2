<?php
namespace Divido\DividoFinancing\Observer;

use Magento\Framework\Event\ObserverInterface;

class RefundObserver implements ObserverInterface
{
    public $helper;

    public $_request;

    public $logger;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Divido\DividoFinancing\Helper\Data $helper,
        \Divido\DividoFinancing\Logger\Logger $logger
    ) {
        $this->_request = $request;
        $this->helper = $helper;
        $this->logger = $logger;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getCreditmemo()->getOrder();
        $code  = $order->getPayment()->getMethodInstance()->getCode();
        $params = $this->_request->getParams();
            
        if ($code == 'divido_financing' && isset($params['pbd_refund']) && $params['pbd_refund'] > 0) {
            $this->logger->info('PBD Refund Triggered', ['Quote' => $order->getQuoteId(), 'request' => $params]);

            $reason = $params['refund_reason'] ?? null;
            $this->helper->autoRefund($order, $params['pbd_refund'], $reason);
        }
    }
}
