<?php

namespace Divido\DividoFinancing\Model;

use Divido\DividoFinancing\Api\CreditRequestInterface;
use Divido\DividoFinancing\Exceptions\LookupNotFoundException;
use Divido\DividoFinancing\Exceptions\MessageValidationException;
use Divido\DividoFinancing\Exceptions\OrderNotFoundException;
use Divido\DividoFinancing\Exceptions\UnusedWebhookException;
use Divido\DividoFinancing\Traits\ValidationTrait;
use Divido\DividoFinancing\Model\Lookup;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Http\Header\HeaderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class CreditRequest implements CreditRequestInterface
{
    use ValidationTrait;

    const
        STATUS_ACCEPTED = 'ACCEPTED',
        STATUS_ACTION_LENDER = 'ACTION-LENDER',
        STATUS_CANCELED = 'CANCELED',
        STATUS_COMPLETED = 'COMPLETED',
        STATUS_DECLINED = 'DECLINED',
        STATUS_DEPOSIT_PAID = 'DEPOSIT-PAID',
        STATUS_AWAITING_ACTIVATION = 'AWAITING-ACTIVATION',
        STATUS_FULFILLED = 'FULFILLED',
        STATUS_REFERRED = 'REFERRED',
        STATUS_SIGNED = 'SIGNED',
        STATUS_READY = 'READY';

    /**
     * The expected event value in the webhook body
     */
    const STATUS_UPDATE_EVENT = 'application-status-update';

    /**
     * Pertains to the config "New order status on Completion"
     * option. When we receive a webhook with this status, we
     * change the order state to the state specified by the
     * config (if used)
     */
    const COMPLETION_STATUS = self::STATUS_READY;

    /**
     * Webhook statuses that trigger an order to be created
     * from a quote referenced by the webhook
     */
    const CREATION_STATUSES = [
        self::STATUS_READY,
        self::STATUS_REFERRED
    ];

    /**
     * Webhook statuses that trigger a request to the
     * Merchant API Pub to update the application 
     * merchant reference to the Order ID
     */
    const MERCHANT_REFERENCE_STATUSES = [
        self::STATUS_READY,
        self::STATUS_REFERRED
    ];

    /**
     * Webhook statuses that trigger a change in order status
     */
    const STATUS_TRANSITIONS = [
        self::STATUS_REFERRED => Order::STATE_HOLDED,
        self::STATUS_DECLINED => Order::STATE_CANCELED,
        self::STATUS_ACCEPTED => Order::STATE_PENDING_PAYMENT
    ];

    /**
     * Webhook statuses that unhold the order
     */
    const RELEASE_STATUSES = [
        self::STATUS_ACCEPTED
    ];

    private $historyMessages = [
        self::STATUS_ACCEPTED => 'Credit request accepted',
        self::STATUS_ACTION_LENDER => 'Lender notified',
        self::STATUS_CANCELED => 'Application canceled',
        self::STATUS_COMPLETED => 'Application completed',
        self::STATUS_DECLINED => 'Applicaiton declined by Underwriter',
        self::STATUS_DEPOSIT_PAID => 'Deposit paid by customer',
        self::STATUS_FULFILLED => 'Credit request fulfilled',
        self::STATUS_REFERRED => 'Credit request referred by Underwriter, waiting for new status',
        self::STATUS_SIGNED => 'Customer have signed all contracts',
        self::STATUS_READY => 'Application ready'
    ];

    private $req;
    private $res;
    private $helper;
    private $logger;
    private $config;
    private $lookupFactory;
    private $quoteManagement;
    private $resourceInterface;
    private $orderCollection;
    private $resourceConnection;
    private $quoteRepository;
    private $orderRepository;
    private $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Request\Http $request,
        Response $response,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Framework\Module\ResourceInterface $resourceInterface,
        \Divido\DividoFinancing\Helper\Data $helper,
        \Divido\DividoFinancing\Model\LookupFactory $lookupFactory,
        \Divido\DividoFinancing\Logger\Logger $logger,
        \Magento\Sales\Model\ResourceModel\Order\Collection $orderCollection,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        RequestFactory $requestFactory,
        StreamFactory $streamFactory
    ) {
        $this->req = $request;
        $this->res = $response;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->config = $scopeConfig;
        $this->lookupFactory = $lookupFactory;
        $this->quoteManagement = $quoteManagement;
        $this->resourceInterface = $resourceInterface;
        $this->orderCollection = $orderCollection;
        $this->resourceConnection = $resourceConnection;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
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

        $planId    = $this->req->getQuery('plan', null);
        $deposit   = $this->req->getQuery('deposit', null);
        $email     = $this->req->getQuery('email', null);
        $quoteId   = $this->req->getQuery('quote_id', null);


        try {
            $creditRequestUrl = $this->helper->creditRequest($planId, $deposit, $email, $quoteId);
            $response['url']  = $creditRequestUrl;
            $this->logger->info($creditRequestUrl);

        } catch (\Exception $e) {
            $this->logger->info($e);
            $response['error'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * Handles Webhook requests and updates the quote/order appropriately
     *
     */
    public function update() {
        $psrRequest = $this->convertMagentoRequestToPsrRequest(
            $this->req,
            $this->requestFactory,
            $this->streamFactory
        );
        
        try{
            $requestObj = $this->validateWebhookRequest($psrRequest);
        } catch (MessageValidationException $e) {
            $this->logger->error("Could not validate webhook", ["error" => $e->getMessage()]);
            return $this->sendWebhookResponse(400, sprintf("Could not validate webhook: %s", $e->getMessage()));
        } catch (UnusedWebhookException $e){
            return $this->sendWebhookResponse(200, $e->getMessage());
        } catch (\Exception $e){
            $this->logger->error("An unexpected error occured validating the webhook", ['error'=> $e->getMessage()]);
            return $this->sendWebhookResponse(500, "Unexpected error: ".$e->getMessage());
        }
        
        try{
            $quote = $this->quoteRepository->get($requestObj->metadata->quote_id);
        } catch(NoSuchEntityException $e){
            $this->logger->error("Could not find quote in DB", ['error'=> $e->getMessage()]);
            return $this->sendWebhookResponse(404, "Could not find quote in database");
        } 

        try{
            $lookup = $this->retrieveWebhookLookup($requestObj);
        } catch(MessageValidationException $e){
            $this->logger->error("Could not validate webhook", ["error" => $e->getMessage()]);
            return $this->sendWebhookResponse(400, sprintf("Could not validate webhook: %s", $e->getMessage()));
        } catch (LookupNotFoundException $e){
            $this->logger->error("Could not retrieve lookup", ["error" => $e->getMessage()]);
            return $this->sendWebhookResponse(404, sprintf("Could not retrieve application from db: %s", $e->getMessage()));
        }

        try{
            $this->updateLookupByStatus($lookup, $requestObj->status);
        } catch(\Exception $e){
            $this->logger->error("An error occured updating the Divido Lookup", [
                "error"=> $e->getMessage()
            ]);
        }

        try{
            $order = $this->retrieveDividoOrderByQuoteId($requestObj->metadata->quote_id);
        } catch(OrderNotFoundException $e){
            $order = null;
        } catch (\Exception $e){
            $this->logger->error("Unexpected error retrieving Order", ["error" => $e->getMessage()]);
            return $this->sendWebhookResponse(500, sprintf("Unexpected error retrieving order: %s", $e->getMessage()));
        }

        // if webhook status triggers order creation and order hasn't been created already
        if(in_array($requestObj->status, self::CREATION_STATUSES)  && $order === null){
            try{
                $this->validateQuotePriceFromLookup($quote, $lookup);
            } catch(MessageValidationException $e){
                $this->logger->error("Could not validate quote price", ["error" => $e->getMessage()]);
                return $this->sendWebhookResponse(400, sprintf("Could not validate quote price: %s", $e->getMessage()));
            }

            try{
                $order = $this->createOrder($quote, $lookup, $requestObj->application);
            } catch (\Exception $e){
                $this->logger->error("Unexpected error creating Order", ["error" => $e->getMessage()]);
                return $this->sendWebhookResponse(500, sprintf("Unexpected error creating order: %s", $e->getMessage()));
            }
        }

        //if webhook status precedes order creation leave early
        if($order === null){
            return $this->sendWebhookResponse(200, "Webhook Handled");
        }

        $this->setOrderStatus($order, $requestObj->status);

        if(in_array($requestObj->status, self::MERCHANT_REFERENCE_STATUSES)){
            $this->helper->updateMerchantReference($requestObj->application, $order->getId());
        }

        if(array_key_exists($requestObj->status, $this->historyMessages)){
            $order->addCommentToStatusHistory(
                'Divido: ' . $this->historyMessages[$requestObj->status]
            );
        }

        if(in_array($requestObj->status, self::RELEASE_STATUSES)){
            try {
                $order->unhold();
                $order->addCommentToStatusHistory('Divido: Order Unheld');
            } catch(\Exception $e){
                $this->logger->warning(
                    "Could not unhold the order",
                    ["reason" => $e->getMessage()]
                );
            }
        }
        
        $this->orderRepository->save($order);

        return $this->sendWebhookResponse(200, "Order Updated");
        
    }

    /**
     * Implements the Validation trait to the webhook and ensures the schema,
     * contents and signature (if required) are correct
     *
     * @param RequestInterface $webhookRequest
     * @return object
     */
    public function validateWebhookRequest(RequestInterface $webhookRequest) :object {

        $secret = $this->config->getValue(
            'payment/divido_financing/secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $configHmac = null;
        if(!empty($secret)){
            $configHmac = $this->helper->create_signature(
                $webhookRequest->getBody()->getContents(),
                $secret
            );
        }

        $requestObj = $this->validateRequest($webhookRequest, 'webhook', $configHmac);

        // if webhook event isn't a status update event, we don't care
        if($requestObj->event !== self::STATUS_UPDATE_EVENT){
            throw new UnusedWebhookException(
                sprintf(
                    "Event '%s' not a status update event (%s)", 
                    $requestObj->event, 
                    self::STATUS_UPDATE_EVENT
                )
            );
        }
        // if webhook status does not instigate an actionable event, ignore it
        if(!in_array(
            $requestObj->status, 
            array_merge(
                self::CREATION_STATUSES, 
                self::MERCHANT_REFERENCE_STATUSES, 
                self::RELEASE_STATUSES,
                self::STATUS_TRANSITIONS,
                array_keys($this->historyMessages)
            )
        )) {
            throw new UnusedWebhookException(
                sprintf("Status '%s' not used by plugin", $requestObj->status)
            );
        }
        
        return $requestObj;
    }

    /**
     * Retrieve the database entry related to the webhook
     *
     * @param object $webhookObj
     * @return Lookup
     */
    public function retrieveWebhookLookup(object $webhookObj) :Lookup {

        $lookup = $this->lookupFactory->create()->load($webhookObj->metadata->quote_id, 'quote_id');
        if (! $lookup->getId()) {
            throw new LookupNotFoundException("Could not retrieve application information from the database");
        }

        $salt = $lookup->getData('salt');
        $hash = $this->helper->hashQuote($salt, $webhookObj->metadata->quote_id);

        if ($hash !== $webhookObj->metadata->quote_hash) {
            throw new MessageValidationException('Invalid Quote hash in Webhook payload');
        }

        return $lookup;
    }

    /**
     * Fetch the order based on a DB query against the Quote ID and payment method
     *
     * @param string $quoteId
     * @return Order
     */
    public function retrieveDividoOrderByQuoteId(string $quoteId) :Order {
        $salesOrderPaymentTableNameWithPrefix = $this->resourceConnection->getTableName('sales_order_payment');

        //Fetch latest Divido order for quote ID
        $this->orderCollection->addAttributeToFilter('quote_id', $quoteId);
        $this->orderCollection->getSelect()
            ->join(
                ["sop" => $salesOrderPaymentTableNameWithPrefix],
                'main_table.entity_id = sop.parent_id',
                array('method')
            )
            ->where('sop.method = ?', 'divido_financing');
        $this->orderCollection->setOrder(
            'created_at',
            'desc'
        );
        $orderId = $this->orderCollection->getFirstItem()->getId();

        if (empty($orderId)) {
            throw new OrderNotFoundException(sprintf('Could not retrieve Divido order with quote ID %s', $quoteId));
        }

        return $this->orderRepository->get($orderId);
    }

    /**
     * Converts the MagentoRequest format received into a more malleable
     * Psr Request format
     *
     * @param \Magento\Framework\App\Request\Http $magentoRequest
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface $streamFactory
     * @return Request
     */
    public function convertMagentoRequestToPsrRequest(
        \Magento\Framework\App\Request\Http $magentoRequest, 
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) :Request {
        $psrRequest = $requestFactory->createRequest(
            $magentoRequest->getMethod(),
            $magentoRequest->getRequestUri()
        );

        $headers = $magentoRequest->getHeaders();
        if(gettype($headers) === 'object' && get_class($headers) === HeaderInterface::class){
            /** @var HeaderInterface $headers */
            $psrRequest = $psrRequest->withHeader($headers->getFieldName(), $headers->getFieldValue());
        } elseif(is_iterable($headers)){
            foreach($headers as $key => $value){
                if(gettype($value) === 'object' && $value instanceof HeaderInterface){
                    /** @var HeaderInterface $value */
                    $psrRequest = $psrRequest->withHeader($value->getFieldName(), $value->getFieldValue());
                } else {
                    $psrRequest = $psrRequest->withHeader($key, $value); 
                }
            }
        }

        $stream = $streamFactory->createStream($magentoRequest->getContent());
        $psrRequest = $psrRequest->withBody($stream);

        return $psrRequest;
    }

    /**
     * Ensures the current price of the quote is the same price as 
     *
     * @param Quote $quote
     * @param Lookup $lookup
     * @return void
     */
    public function validateQuotePriceFromLookup(Quote $quote, Lookup $lookup) :void {
        $quoteTotal = (float) $quote->getGrandTotal();
        $lookupTotal = (float) $lookup->getData('initial_cart_value');

        if($quoteTotal !== $lookupTotal){
            throw new MessageValidationException(
                'Value of cart changed before completion'
            );
        }
    }

    /**
     * Change the state of the order if prompted by the change in status
     * Will also hold the order if order state changed to HOLDED
     *
     * @param Order $order
     * @param string $newStatus
     * @return void
     */
    public function setOrderStatus(Order $order, string $newStatus) :void {
        $statusTransitions = $this->getAllStatusTransitions();

        if(!isset($statusTransitions[$newStatus])){
            return;
        }
        
        $newOrderStatus = $statusTransitions[$newStatus];
        switch($newOrderStatus){
            case Order::STATE_HOLDED:
                $order->hold();
                break;
            default:
                $order->setStatus($newOrderStatus);
                break;
        }
    }

    /**
     * Updates the database based on the changes to the status
     *
     * @param Lookup $lookup
     * @param string $newStatus
     * @return void
     */
    public function updateLookupByStatus(
        Lookup $lookup, 
        string $newStatus
    ) :void {    
        switch($newStatus){
            case self::STATUS_CANCELED:
                $lookup->setData('canceled', 1);
                $lookup->save();
                break;
            case self::STATUS_DECLINED:
                $lookup->setData('declined',1);
                $lookup->save();
                break;
            case self::STATUS_REFERRED:
                $lookup->setData('referred',1);
                $lookup->save();
                break;
        }
    }

    /**
     * Obtains all transition statuses and extends array if
     * configuration settings change status on completion
     *
     * @return array
     */
    public function getAllStatusTransitions() :array{
        $transitionStatuses = self::STATUS_TRANSITIONS;
        $configCreateStatus = $this->config->getValue(
            'payment/divido_financing/order_status',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if(!empty($configCreateStatus)) {
            $transitionStatuses[self::COMPLETION_STATUS] = $configCreateStatus;
        }
        return $transitionStatuses;
    }

    /**
     * Creates an order and updates the Lookup
     *
     * @param object $webhook
     * @param Lookup $lookup
     * @return Order
     */
    public function createOrder(Quote $quote, Lookup $lookup, string $application) :Order {
        $quote->setIsActive(true);
        $this->quoteRepository->save($quote);
        $orderId = $this->quoteManagement->placeOrder($quote->getId());
        $lookup->setData('order_id', $orderId);
        $lookup->setData('application_id', $application);
        $lookup->save();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderRepository->get($orderId);
        return $order;
    }

    /**
     * Sends a json response in the format the webhook expects to receive
     *
     * @param integer $status
     * @param string $message
     * @return void
     */
    public function sendWebhookResponse(int $status=200, string $message = '') :void
    {
        $response = [
            'message'               => $message,
            'ecom_platform'         => 'Magento_2',
            'plugin_version'        => $this->helper->getVersion(),
            'ecom_platform_version' => $this->helper->getMagentoVersion()
        ];
        
        $this->res
            ->setHeader('Content-Type', 'application/json', true)
            ->setStatusHeader($status)
            ->setBody(json_encode($response))
            ->sendResponse();
        
    }

    /**
     * Returns version information to the request
     *
     * @return void
     */
    public function version() :void
    {
        $response = [
            'ecom_platform'         => 'Magento_2',
            'plugin_version'        => $this->helper->getVersion(),
            'ecom_platform_version' => $this->helper->getMagentoVersion()
        ];

        $this->res
            ->setHeader('Content-Type', 'application/json', true)
            ->setStatusHeader(200)
            ->setBody(json_encode($response))
            ->sendResponse();
    }
}
