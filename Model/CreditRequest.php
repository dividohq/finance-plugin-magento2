<?php

namespace Divido\DividoFinancing\Model;

use Divido\DividoFinancing\Api\CreditRequestInterface;

class CreditRequest implements CreditRequestInterface
{
    const
        VERSION              = 'M2-1.1.0',
        NEW_ORDER_STATUS     = 'processing',
        STATUS_ACCEPTED      = 'ACCEPTED',
        STATUS_ACTION_LENDER = 'ACTION-LENDER',
        STATUS_CANCELED      = 'CANCELED',
        STATUS_COMPLETED     = 'COMPLETED',
        STATUS_DECLINED      = 'DECLINED',
        STATUS_DEPOSIT_PAID  = 'DEPOSIT-PAID',
        STATUS_FULFILLED     = 'FULFILLED',
        STATUS_REFERRED      = 'REFERRED',
        STATUS_SIGNED        = 'SIGNED';

    private $historyMessages = [
        self::STATUS_ACCEPTED      => 'Credit request accepted',
        self::STATUS_ACTION_LENDER => 'Lender notified',
        self::STATUS_CANCELED      => 'Application canceled',
        self::STATUS_COMPLETED     => 'Application completed',
        self::STATUS_DECLINED      => 'Applicaiton declined by Underwriter',
        self::STATUS_DEPOSIT_PAID  => 'Deposit paid by customer',
        self::STATUS_FULFILLED     => 'Credit request fulfilled',
        self::STATUS_REFERRED      => 'Credit request referred by Underwriter, waiting for new status',
        self::STATUS_SIGNED        => 'Customer have signed all contracts',
    ];

    private $noGo = [
        self::STATUS_CANCELED,
        self::STATUS_DECLINED,
    ];

    private $req;
    private $quote;
    private $order;
    private $helper;
    private $logger;
    private $config;
    private $lookupFactory;
    private $quoteManagement;
    private $resourceInterface;
    private $resultJsonFactory;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Framework\Module\ResourceInterface $resourceInterface,
        \Divido\DividoFinancing\Helper\Data $helper,
        \Divido\DividoFinancing\Model\LookupFactory $lookupFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->req    = $request;
        $this->quote = $quote;
        $this->order = $order;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->config = $scopeConfig;
        $this->lookupFactory = $lookupFactory;
        $this->quoteManagement = $quoteManagement;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resourceInterface = $resourceInterface;
    }

    /**
     * Create a credit request as Divido, return a URL to complete the credit
     * request.
     *
     * @api
     * @param string Quote ID
     * @return string Credit request URL
     */
    public function create()
    {
        $response = [];

        $planId  = $this->req->getQuery('plan', null);
        $deposit = $this->req->getQuery('deposit', null);
        $email   = $this->req->getQuery('email', null);
        $cartValue   = $this->req->getQuery('initial_cart_value', null);
        $quoteId = $this->req->getQuery('quote_id', null);
        
        try {
            $creditRequestUrl = $this->helper->creditRequest($planId, $deposit, $email, $quoteId);
            $response['url']  = $creditRequestUrl;
        } catch (\Exception $e) {
            $this->logger->addError($e);
            $response['error'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * Update an order with results from credit request
     *
     * @api
     * @return \Magento\Framework\Controller\ResultJson
     */
    public function update()
    {
        $debug = $this->config->getValue(
            'payment/divido_financing/debug',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $content = $this->req->getContent();
        if ($debug) {
            $this->logger->debug('Divido: Request: ' . $content);
        }

        $data = json_decode($content);
        if ($data === null) {
            $this->logger->error('Divido: Bad request, could not parse body: ' . $content);
            return $this->webhookResponse(false, 'Invalid json');
        }

        $quoteId = $data->metadata->quote_id;

        $lookup = $this->lookupFactory->create()->load($quoteId, 'quote_id');
        if (! $lookup->getId()) {
            $this->logger->error('Divido: Bad request, could not find lookup. Req: ' . $content);
            return $this->webhookResponse(false, 'No lookup');
        }

        $secret = $this->config->getValue(
            'payment/divido_financing/secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($secret) {
            $reqSign = $this->req->getHeader('X-DIVIDO-HMAC-SHA256');
            $sign = $this->helper->createSignature($content, $secret);

            if ($reqSign !== $sign) {
                $this->logger->addError('Divido: Bad request, invalid signature. Req: ' . $content);
                return $this->webhookResponse(false, 'Invalid signature');
            }
        }

        $salt = $lookup->getSalt();
        $hash = $this->helper->hashQuote($salt, $data->metadata->quote_id);
        if ($hash !== $data->metadata->quote_hash) {
            $this->logger->addError('Divido: Bad request, mismatch in hash. Req: ' . $content);
            return $this->webhookResponse(false, 'Invalid hash');
        }

        if (! isset($data->event) || $data->event != 'application-status-update') {
            return $this->webhookResponse();
        }

        if (isset($data->application)) {
            if ($debug) {
                $this->logger->addDebug('Divido: update application id');
            }
            $lookup->setData('application_id', $data->application);
            $lookup->save();
        }

        $order = $this->order->loadByAttribute('quote_id', $quoteId);

        if (in_array($data->status, $this->noGo)) {
            if ($debug) {
                $this->logger->addDebug('Divido: No go: ' . $data->status);
            }

            if ($data->status == self::STATUS_DECLINED) {
                $lookup->setData('declined', 1);
                $lookup->save();
            }

            return $this->webhookResponse();
        }

        $creationStatus = $this->config->getValue(
            'payment/divido_financing/creation_status',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (! $order->getId() && $data->status != $creationStatus) {
            if ($debug) {
                $this->logger->debug('Divido: No order, not creation status: ' . $data->status);
            }
            return $this->webhookResponse();
        }
        
        if (! $order->getId() && $data->status == $creationStatus) {
            if ($debug) {
                $this->logger->debug('Divido: Create order');
            }

            $quote = $this->quote->loadActive($quoteId);
            if (! $quote->getCustomerId()) {
                $quote->setCheckoutMethod(\Magento\Quote\Model\QuoteManagement::METHOD_GUEST);
                $quote->save();
            }

            //If cart value is different do not place order
            $totals = $quote->getTotals();
            $grandTotal = (string) $totals['grand_total']->getValue();
            $iv=(string ) $lookup->getData('initial_cart_value');

            if ($debug) {
                $this->logger->warning('Current Cart Value : ' . $grandTotal);
                $this->logger->warning('Divido Inital Value: ' . $iv);
            }

            $orderId = $this->quoteManagement->placeOrder($quoteId);
            $order = $this->order->load($orderId);

            if ($grandTotal != $iv) {
                if ($debug) {
                    $this->logger->warning('HOLD Order - Cart value changed: ');
                }
                //Highlight order for review
                $lookup->setData('canceled', 1);
                $lookup->save();
                $appId = $lookup->getProposalId();
                $order->setActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_HOLD, true);

                if ($order->canHold()) {
                    if ($debug) {
                        $this->logger->warning('HOLDING:');
                    }
                    $order->hold();
                    $order->addStatusHistoryComment(__('Value of cart changed before completion - order on hold'));
                    $state = \Magento\Sales\Model\Order::STATE_HOLDED;
                    $status = \Magento\Sales\Model\Order::STATE_HOLDED;
                    $comment = 'Value of cart changed before completion - Order on hold';
                    $notify = false;
                    $order->setHoldBeforeState($order->getState());
                    $order->setHoldBeforeStatus($order->getStatus());
                    $order->setState($state, $status, $comment, $notify);
                    $order->save();
                    $lookup->setData('order_id', $order->getId());
                    $lookup->save();
                    return $this->webhookResponse();
                } else {
                    if ($debug) {
                        $this->logger->addDebug('Divido: Cannot Hold Order');
                    };
                    $order->addStatusHistoryComment(__('Value of cart changed before completion - cannot hold order'));
                }
                
                if ($debug) {
                    $this->logger->warning('HOLD Order - Cart value changed: '.(string)$appId);
                }
            }
        }
        
        $lookup->setData('order_id', $order->getId());
        $lookup->save();

        if ($data->status == self::STATUS_SIGNED) {
            if ($debug) {
                $this->logger->addDebug('Divido: Escalate order');
            }

            $status = self::NEW_ORDER_STATUS;
            $status_override = $this->config->getValue(
                'payment/divido_financing/order_status',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
            if ($status_override) {
                $status = $status_override;
            }
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
            $order->setStatus($status);
        }

        $comment = 'Divido: ' . $data->status;
        if (array_key_exists($data->status, $this->historyMessages)) {
            $comment = 'Divido: ' . $this->historyMessages[$data->status];
        }

        $order->addStatusHistoryComment($comment);
        $order->save();

        return $this->webhookResponse();
    }

    private function webhookResponse($ok = true, $message = '')
    {
        $pluginVersion = $this->resourceInterface->getDbVersion('Divido_DividoFinancing');
        $status = $ok ? 'ok' : 'error';
        $response = [
            'status'           => $status,
            'message'          => $message,
            'platform'         => 'Magento',
            'plugin_version'   => $pluginVersion,
        ];

        return json_encode($response);
    }
}
