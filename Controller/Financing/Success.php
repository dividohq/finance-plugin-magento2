<?php

namespace Divido\DividoFinancing\Controller\Financing;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Success extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
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
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Divido\DividoFinancing\Logger\Logger $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->order           = $order;
        $this->quoteRepository = $quoteRepository;
        $this->config          = $scopeConfig;
        $this->logger          = $logger;

        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
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
        $this->logger->info('SuccessController Start');

        $quoteId = $this->getRequest()->getParam('quote_id');
        $order   = $this->order->loadByAttribute('quote_id', $quoteId);

        if ($order->getId()) {
            $this->logger->info('Order Found found with quote:'.$quoteId);
        } else if(!empty($this->getTimeout())){
            for($x = 0; $x < (int)$this->getTimeout() ; $x++ ){
                $this->logger->info('Order not found with quote:'.$quoteId);
                sleep(1);
                $order   = $this->order->loadByAttribute('quote_id', $quoteId);
                if($order->getId()){
                    $this->logger->info('Order Found found with quote:'.$quoteId);
                    break;
                }
            }
        }

        $this->logger->info('SuccessController');
        $this->logger->info('quoteId:'.$quoteId);
        $this->logger->info('orderId:'.$order->getId());
        $this->logger->info('Sleep Timeout:'.$this->getTimeout());

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
        $this->logger->info('SuccessController End');
        $this->_redirect('checkout/onepage/success');
    }
}
