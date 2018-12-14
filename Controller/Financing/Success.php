<?php

namespace Divido\DividoFinancing\Controller\Financing;

class Success extends \Magento\Framework\App\Action\Action
{
    private $checkoutSession;
    private $config;
    private $order;
    private $quoteRepository;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig

    ) {
    
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->quoteRepository = $quoteRepository;
        $this->config        = $scopeConfig;

        parent::__construct($context);
    }

    public function getTimeout()
    {
        $timeout = $this->config->getValue(
            'payment/divido_financing/timeout_delay',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    
        return $timeout;
    }



    public function execute()
    {

        $quoteId = $this->getRequest()->getParam('quote_id');
        $order   = $this->order->loadByAttribute('quote_id', $quoteId);

        if ($order->getId() == null) {
            //get sleep value
            sleep($this->getTimeout());
            $order   = $this->order->loadByAttribute('quote_id', $quoteId);
        }

        $this->checkoutSession->setLastQuoteId($quoteId);
        $this->checkoutSession->setLastSuccessQuoteId($quoteId);
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());

        //Addition to kill cart quote;
        $quote = $this->checkoutSession->getQuote();
        $this->checkoutSession->setQuoteId(null);
        $quote->setIsActive(false);
        $this->quoteRepository->save($quote);

        $this->_redirect('checkout/onepage/success');
    }
}
