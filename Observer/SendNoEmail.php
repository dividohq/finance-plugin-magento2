<?php
namespace Divido\DividoFinancing\Observer;

use Divido\DividoFinancing\Helper\Data;
use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface;

class SendNoEmail implements ObserverInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    public function execute(EventObserver $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $code = $order->getPayment()->getMethodInstance()->getCode();
        if ($code == Data::PAYMENT_METHOD) {
            $order->setCanSendNewEmailFlag(false);
            $order->setCustomerNoteNotify(false);
            $order->save();
        }
        return true;
    }
}