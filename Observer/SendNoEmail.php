<?php

namespace Divido\DividoFinancing\Observer;

use \Magento\Framework\Event\Observer as EventObserver;
use \Magento\Framework\Event\ObserverInterface;
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
        if ($code == 'divido_financing') {
            $order->setCanSendNewEmailFlag(false);
            $order->setCustomerNoteNotify(false);
            $order->save();
        }

        return true;
    }
}
