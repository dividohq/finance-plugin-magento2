<?php

namespace Divido\DividoFinancing\Helper;

use \Divido\DividoFinancing\Model\LookupFactory;
use Magento\Framework\UrlInterface;
use Magento\Catalog\Model\ProductFactory;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    const CACHE_DIVIDO_TAG = 'divido_cache';
    const CACHE_PLANS_KEY  = 'divido_plans';
    const CACHE_PLANS_TTL  = 3600;
    const CACHE_PLATFORM_KEY  = 'platform_env';
    const CACHE_PLATFORM_TTL  = 3600;
    const CALLBACK_PATH    = 'rest/V1/divido/update/';
    const REDIRECT_PATH    = 'divido/financing/success/';
    const CHECKOUT_PATH    = 'checkout/';

    private $config;
    private $logger;
    private $cache;
    private $cart;
    private $storeManager;
    private $lookupFactory;
    private $productFactory;
    private $resource;
    private $connection;
    private $urlBuilder;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resource,
        LookupFactory $lookupFactory,
        UrlInterface $urlBuilder,
        ProductFactory $productFactory
    ) {
    
        $this->config        = $scopeConfig;
        $this->logger        = $logger;
        $this->cache         = $cache;
        $this->cart          = $cart;
        $this->storeManager  = $storeManager;
        $this->resource      = $resource;
        $this->lookupFactory = $lookupFactory;
        $this->urlBuilder    = $urlBuilder;
        $this->productFactory = $productFactory;
    }

       /**
     * Checks the SDK's Environment class for the given environment type
     *
     * @param string $apiKey The config API key
     *
     * @return void
     */
    public function getEnvironment($apiKey = false)
    {
        $apiKey = (false === $apiKey) ? $this->getApiKey() : $apiKey;

        if (empty($apiKey)) {
            $this->logger->debug('Empty API key');
            return false;
        } else {
            list($environment, $key) = explode("_", $apiKey);
            $environment = strtoupper($environment);
            $this->logger->debug('getEnv:'.$environment);

            if (!is_null(
                constant("\Divido\MerchantSDK\Environment::$environment")
            )) {
                $environment
                    = constant("\Divido\MerchantSDK\Environment::$environment");
                return $environment;
            } else {
                $this->logger->error('Environment does not exist in the SDK');
                return false;
            }
        }
    }

    /**
     * Get Finance Platform Environment function
     *
     *  @param [string] $api_key - The platform API key.
     */
    public function getPlatformEnv()
    {

        if ($env = $this->cache->load(self::CACHE_PLATFORM_KEY)) {
            return $env;
        } else {
            $sdk      = $this->getSdk();
            $response = $sdk->platformEnvironments()->getPlatformEnvironment();
            $finance_env = $response->getBody()->getContents();
            $decoded = json_decode($finance_env);
            $this->logger->debug('getPlatformEnv:'.serialize($decoded));

            $this->cache->save(
                $decoded->data->environment,
                self::CACHE_PLATFORM_KEY,
                [self::CACHE_DIVIDO_TAG],
                self::CACHE_PLATFORM_TTL
            );

            return $decoded->data->environment;
        }
    }

    public function getSdk()
    {
        $apiKey = $this->getApiKey();
        $this->logger->debug('Get SDK');

        $env = $this->getEnvironment($apiKey);
        $this->logger->debug('Get SDK'.$env);

        $client = new \GuzzleHttp\Client();
        $sdk = true;

        $httpClientWrapper = new \Divido\MerchantSDK\HttpClient\HttpClientWrapper(
            new \Divido\MerchantSDKGuzzle6\GuzzleAdapter($client),
            \Divido\MerchantSDK\Environment::CONFIGURATION[$env]['base_uri'],
            $apiKey
        );

        $sdk = new \Divido\MerchantSDK\Client($httpClientWrapper, $env);

        return $sdk;
    }
    /*
    public function getConnection()
    {
        if (! $this->connection) {
            $this->connection = $this->resource->getConnection('core_write');
        }

        return $this->connection;
    }
    */

    public function cleanCache()
    {
        $this->cache->clean('matchingTag', [self::CACHE_DIVIDO_TAG]);
    }
    
    public function getProductSelection()
    {
        $selection= $this->config->getValue(
            'payment/divido_financing/product_selection',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        return $selection;
    }

    public function getPriceThreshold()
    {
        $threshold = $this->config->getValue(
            'payment/divido_financing/price_threshold',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        return $threshold;
    }

    public function getActive()
    {
        $active = $this->config->getValue(
            'payment/divido_financing/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        return $active;
    }

    public function getWidgetMode()
    {
        $active = $this->config->getValue(
            'payment/divido_financing/widget_mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        return $active;
    }

    public function getWidgetButtonText()
    {
        $active = $this->config->getValue(
            'payment/divido_financing/widget_button_text',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        return $active;
    }

    public function getWidgetFootnote()
    {
        $active = $this->config->getValue(
            'payment/divido_financing/widget_footnote',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        return $active;
    }
    
    public function getAllPlans()
    {
        $apiKey = $this->config->getValue(
            'payment/divido_financing/api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if (empty($apiKey)) {
            $this->cleanCache();
            return [];
        }

        if ($plans = $this->cache->load(self::CACHE_PLANS_KEY)) {
            $this->logger->addDebug('Cached Plans' . $plans);
            $plans = unserialize($plans);
            return $plans;
        }

        $response = $this->getPlans();

        if (!isset($response[0]->id)) {
            $this->logger->addError('Could not get financing plans.');
            $this->cleanCache();
            return [];
        }

        $plans = $response;

        $this->cache->save(
            serialize($plans),
            self::CACHE_PLANS_KEY,
            [self::CACHE_DIVIDO_TAG],
            self::CACHE_PLANS_TTL
        );
        
        return $plans;
    }

    public function getGlobalSelectedPlans()
    {
        $plansDisplayed = $this->config->getValue(
            'payment/divido_financing/plans_displayed',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $plansDisplayed = $plansDisplayed ?: 'plans_all';

        $plansSelection = $this->config->getValue(
            'payment/divido_financing/plan_selection',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $plansSelection = $plansSelection ? explode(',', $plansSelection) : [];

        $plans = $this->getAllPlans();

        if ($plansDisplayed != 'plans_all') {
            foreach ($plans as $key => $plan) {
                if (! in_array($plan->id, $plansSelection)) {
                    unset($plans[$key]);
                }
            }
        }

        return $plans;
    }

    public function getQuotePlans($quote)
    {
        if (!$quote) {
            return false;
        }

        $totals = $quote->getTotals();
        $items  = $quote->getAllVisibleItems();

        $grandTotal = $totals['grand_total']->getValue();

        $plans = [];
        foreach ($items as $item) {
            $product    = $item->getProduct();
            $localPlans = $this->getLocalPlans($product->getId());
            $plans      = array_merge($plans, $localPlans);
        }

        foreach ($plans as $key => $plan) {
            $planMinTotal = $grandTotal - ($grandTotal * ($plan->deposit->minimum_percentage / 100));
            if ($planMinTotal < $plan->deposit->minimum_percentage) {
                unset($plans[$key]);
            }
        }

        return $plans;
    }

    public function getGrandTotal($quote)
    {
        if (!$quote) {
            return false;
        }

        $totals = $quote->getTotals();
        $grandTotal = $totals['grand_total']->getValue();

        return $grandTotal;
    }

    public function getLocalPlans($productId)
    {
        $isActive = $this->getActive();
        if (! $isActive) {
            return[];
        }

        $product = $this->productFactory->create()->load($productId);

        $display = null;
        $dispAttr = $product->getResource()->getAttribute('divido_plans_display');
        if ($dispAttr) {
            $dispAttrCode = $dispAttr->getAttributeCode();
            $display  = $product->getData($dispAttrCode);
        }

        $productPlans = null;
        $listAttr = $product->getResource()->getAttribute('divido_plans_list');
        if ($listAttr) {
            $listAttrCode = $listAttr->getAttributeCode();
            $productPlans = $product->getData($listAttrCode);
            $productPlans = explode(',', $productPlans);
        }

        $globalProdSelection = $this->config->getValue(
            'payment/divido_financing/product_selection',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!$display
            || $display == 'product_plans_default'
            || (empty($productPlans)
            && $globalProdSelection != 'products_selected')) {
            return $this->getGlobalSelectedPlans();
        }

        $plans = $this->getAllPlans();
        foreach ($plans as $key => $plan) {
            if (! in_array($plan->id, $productPlans)) {
                unset($plans[$key]);
            }
        }

        return $plans;
    }

    public function creditRequest($planId, $depositAmount, $email, $quoteId = null)
    {
        $secret = $this->config->getValue(
            'payment/divido_financing/secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
  
        $quote       = $this->cart->getQuote();
        if ($quoteId != null) {
            $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $quote = $this->_objectManager->create('Magento\Quote\Model\Quote')->load($quoteId);
        }
        $shipAddr    = $quote->getShippingAddress();
        $country     = $shipAddr->getCountryId();
        $billingAddr = $quote->getBillingAddress();
        $shippingAddress = $this->getAddressDetail($shipAddr);
        $billingAddress  = $this->getAddressDetail($billingAddr);
        
        if (empty($country)) {
            $shipAddr = $quote->getBillingAddress();
            $country = $shipAddr->getCountry();
        }
        
        if (!empty($email)) {
            if (!$quote->getCustomerEmail()) {
                $quote->setCustomerEmail($email);
                $quote->save();
            }
        } else {
            if ($existingEmail = $quote->getCustomerEmail()) {
                $email = $existingEmail;
            }
        }
        //TODO - get languages correctly
        $language = 'en';
        $store = $this->storeManager->getStore();
        $currency = $store->getCurrentCurrencyCode();

        $customer = [
            'title'             => '',
            'firstName'         => $shipAddr->getFirstName(),
            'middleNames'       => $shipAddr->getMiddleName(),
            'lastName'          => $shipAddr->getLastName(),
            'country'           => $country,
            'postcode'          => $shipAddr->getPostcode(),
            'email'             => $email,
            'phoneNumber'       => $shipAddr->getTelephone(),
            'shippingAddress'   => $shippingAddress,
            'addresses'         => [$billingAddress],
        ];

        $products = [];
        foreach ($quote->getAllItems() as $item) {
            if ($item->getParentItemId() == null) {
                $products[] = [
                    'type'     => 'product',
                    'name'     => $item->getName(),
                    'quantity' => (int)$item->getQty(),
                    'price'    => (int)$item->getPriceInclTax() * 100,
                ];
            }
        }
        $totals = $quote->getTotals();
        $grandTotal = $totals['grand_total']->getValue();
        $deposit = round($depositAmount);
        $shipping = $shipAddr->getShippingAmount() * 100;
        if (! empty($shipping)) {
            $products[] = [
                'type'     => 'product',
                'name'     => 'Shipping & Handling',
                'quantity' => (int) '1',
                'price'    => (int) $shipping,
            ];
        }
        $discount = $shipAddr->getDiscountAmount();
        if (! empty($discount)) {
            $products[] = [
                'type'     => 'product',
                'name'     => 'Discount',
                'quantity' => (int) '1',
                'price'    => (int) $discount * 100,
            ];
        }
        $quoteId   = $quote->getId();
        $salt      = uniqid('', true);
        $quoteHash = $this->hashQuote($salt, $quoteId);
        $response_url = $this->urlBuilder->getBaseUrl() . self::CALLBACK_PATH;
        $checkout_url = $this->urlBuilder->getUrl(self::CHECKOUT_PATH);
        
        if (!empty($this->getCustomCheckoutUrl())) {
            $checkout_url = $this->urlBuilder->getUrl($this->getCustomCheckoutUrl());
        }
        
        $redirect_url = $this->urlBuilder->getUrl(
            self::REDIRECT_PATH,
            ['quote_id' => $quoteId]
        );
        if (!empty($this->getCustomRedirectUrl())) {
            $redirect_url = $this->getCustomRedirectUrl().'/quote_id/'.$quoteId;
        }

        $sdk                       = $this->getSdk();
        $application               = (new \Divido\MerchantSDK\Models\Application())
            ->withCountryId($country)
            ->withFinancePlanId($planId)
            ->withApplicants([$customer])
            ->withOrderItems($products)
            ->withDepositAmount($deposit)
            ->withFinalisationRequired(false)
            ->withMerchantReference('')
            ->withUrls(
                [
                    'merchant_redirect_url' => $redirect_url,
                    'merchant_checkout_url' => $checkout_url,
                    'merchant_response_url' => $response_url,
                ]
            )
            ->withMetadata(
                [
                    'initial_cart_value' => $grandTotal,
                    'quote_id'           => $quoteId,
                    'quote_hash'         => $quoteHash,

                ]
            );
        //todo - improve error handling
        /*TODO FIX HMAC
        if ('' !== $secret ) {
            $this->logger->debug('Hmac Version'.$secret);

            $response              = $sdk->applications()->createApplication($application,[],['Content-Type' => 'application/json', 'X-Divido-Hmac-Sha256' => $secret]);
        }else{
            $this->logger->debug('Non Hmac');

            $response              = $sdk->applications()->createApplication($application,[],['Content-Type' => 'application/json']);
        }
        */
        $response              = $sdk->applications()->createApplication($application,[],['Content-Type' => 'application/json']);

        $application_response_body = $response->getBody()->getContents();
        
        $decode                    = json_decode($application_response_body);
        $this->logger->debug(serialize($decode));
        $result_id                 = $decode->data->id;
        $result_redirect           = $decode->data->urls->application_url;
        if ($response) {
            $lookupModel = $this->lookupFactory->create();
            $lookupModel->load($quoteId, 'quote_id');
            $lookupModel->setData('quote_id', $quoteId);
            $lookupModel->setData('salt', $salt);
            $lookupModel->setData('deposit_value', $deposit);
            $lookupModel->setData('proposal_id', $result_id);
            $lookupModel->setData('initial_cart_value', $grandTotal);
            $lookupModel->save();
            return $result_redirect;
        } else {
            if ($response->status === 'error') {
                throw new \Magento\Framework\Exception\LocalizedException(__($decode));
            }
        }
    }

    public function hashQuote($salt, $quoteId)
    {
        return hash('sha256', $salt.$quoteId);
    }

    public function getApiKey()
    {
        $apiKey = $this->config->getValue(
            'payment/divido_financing/api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $apiKey;
    }

    public function getDividoKey()
    {
        $apiKey = $this->getApiKey();
        
        if (empty($apiKey)) {
            return '';
        }
    
            $keyParts = explode('.', $apiKey);
            $relevantPart = array_shift($keyParts);
    
            $jsKey = strtolower($relevantPart);
            
            return $jsKey;
    }

    public function getScriptUrl()
    {
        $this->logger->debug('GetScript URL HElper');

        $apiKey = $this->getApiKey();
        $scriptUrl= "//cdn.divido.com/widget/dist/divido.calculator.js";

        if (empty($apiKey)) {
            return $scriptUrl;
        }

        $platformEnv = $this->getPlatformEnv();
        $this->logger->debug('platform env:'.$platformEnv);

        $scriptUrl= "//cdn.divido.com/widget/dist/" . $platformEnv . ".calculator.js";
        $this->logger->debug('Url:'.$scriptUrl);

        return (string) $scriptUrl;
    }

    public function plans2list($plans)
    {
        $plansBare = array_map(
            function ($plan) {
                return $plan->id;
            },
            $plans
        );

        $plansBare = array_unique($plansBare);

        return implode(',', $plansBare);
    }

    public function getLookupForOrder($order)
    {
        $quoteId = $order->getQuoteId();

        $lookupModel = $this->lookupFactory->create();
        $lookupModel->load($quoteId, 'quote_id');
        if (! $lookupModel->getId()) {
            return null;
        }

        return [
            'proposal_id'        => $lookupModel->getData('proposal_id'),
            'application_id'     => $lookupModel->getData('application_id'),
            'deposit_amount'     => $lookupModel->getData('deposit_value'),
            'initial_cart_value' => $lookupModel->getData('initial_cart_value'),
            'order_id'           => $lookupModel->getData('order_id')

        ];
    }

    public function autoFulfill($order)
    {
        // Check if it's a finance order
        $lookup = $this->getLookupForOrder($order);
        if ($lookup === null) {
            return false;
        }

        // If fulfilment is enabled
        $autoFulfilment = $this->config->getValue(
            'payment/divido_financing/auto_fulfilment',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $fulfilmentStatus = $this->config->getValue(
            'payment/divido_financing/fulfilment_status',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (! $autoFulfilment || ! $fulfilmentStatus) {
            return false;
        }

        $currentStatus  = $order->getData('status');
        $previousStatus = $order->getOrigData('status');

        if ($currentStatus != $fulfilmentStatus || $currentStatus == $previousStatus) {
            return false;
        }

        $trackingNumbers = [];
        $shippingMethod = $order->getShippingDescription();

        $tracks = $order->getTracksCollection()->toArray();
        if ($tracks && isset($tracks['items'])) {
            foreach ($tracks['items'] as $track) {
                $trackingNumbers[] = "{$track['title']}: {$track['track_number']}";
            }
        }

        $trackingNumbers = implode(',', $trackingNumbers);
        $applicationId = $lookup['application_id'];
        $grandTotal = $lookup['initial_cart_value'];

        return $this->setFulfilled($applicationId, $grandTotal, $shippingMethod, $trackingNumbers);
    }

    public function createSignature($payload, $secret)
    {
        $hmac = hash_hmac('sha256', $payload, $secret, true);
        $signature = base64_encode($hmac);

        return $signature;
    }

    /**
     * Returns and array from magento address object
     *
     * Converts a magento array object into an array for use within our form
     *
     * @param object $addressObject
     * @return array
     */
    public function getAddressDetail($addressObject)
    {
        $street = str_replace("\n", " ", $addressObject['street']);
        $addressText     = implode(' ', [$street,$addressObject['city'],$addressObject['postcode']]);
        $addressArray = [
            'postcode'          => $addressObject['postcode'],
            'street'            => $street,
            'flat'              => '',
            'buildingNumber'    => '',
            'buildingName'      => '',
            'town'              => $addressObject['city'],
            'flat'              => '',
            'text'              => $addressText,
        ];

        return $addressArray;
    }


    public function getHeadlessMode()
    {
        $headless = $this->config->getValue(
            //TODO Fix Value
            'payment/divido_financing/divido_financing_developer/headless_support',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $headless;
    }


    public function getCustomCheckoutUrl()
    {
        if (0 == $this->getHeadlessMode()) {
            return false;
        }
        $customUrl = $this->config->getValue(
            'payment/divido_financing/custom_checkout_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $customUrl;
    }

    public function getCustomRedirectUrl()
    {
        $customUrl = $this->config->getValue(
            'payment/divido_financing/custom_redirect_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $customUrl;
    }


    public function updateInvoiceStatus($order)
    {
      // Check if it's a divido order
        $lookup = $this->getLookupForOrder($order);
        if ($lookup === null) {
            return false;
        }
         $invoiceStatus = $this->config->getValue(
             'payment/divido_financing/invoice_status',
             \Magento\Store\Model\ScopeInterface::SCOPE_STORE
         );
        if (! $invoiceStatus) {
            return false;
        }
        //todo understand what status we should update
        $currentStatus  = $order->getData('status');
        $previousStatus = $order->getOrigData('status');
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
        $order->setStatus($invoiceStatus);
        $order->addStatusToHistory($order->getStatus(), 'ORDER  processed successfully with reference');
        $order->save();
    }

    protected function getPlans()
    {
        $sdk            = $this->getSdk();
        $finances       = false;
        if (false === $finances) {
            $request_options = (new \Divido\MerchantSDK\Handlers\ApiRequestOptions());
            try {
                $plans = $sdk->getAllPlans($request_options);
                $plans = $plans->getResources();
                return $plans;
            } catch (Exception $e) {
                return [];
            }
        }
    }
    public function setFulfilled($application_id, $order_total, $shipping_method = null, $tracking_numbers = null)
    {
        // First get the application you wish to create an activation for.
        $application = (new \Divido\MerchantSDK\Models\Application())
            ->withId($application_id);
        $items       = [
            [
                'name'     => "Magento 2 Activation",
                'quantity' => 1,
                'price'    => $order_total * 100,
            ],
        ];
        // Create a new application activation model.
        $application_activation = (new \Divido\MerchantSDK\Models\ApplicationActivation())
            ->withOrderItems($items)
            ->withDeliveryMethod($shipping_method)
            ->withTrackingNumber($tracking_numbers);
        // Create a new activation for the application.
        $env                      = $this->getEnvironment($this->getApiKey());
        $sdk                      = $this->getSdk();
        $response                 = $sdk->applicationActivations()->createApplicationActivation($application, $application_activation);
        $activation_response_body = $response->getBody()->getContents();
    }

    public function autoCancel($order)
    {
        // Check if it's a finance order
        $lookup = $this->getLookupForOrder($order);
        if ($lookup === null) {
            return false;
        }

        $applicationId = $lookup['application_id'];
        $order_total = $lookup['initial_cart_value'];

        $order_id = $lookup['order_id'];
        return $this->sendCancellation($applicationId, $order_total, $order_id);
    }

    private function cancelLookup($orderId)
    {
        $lookupModel = $this->lookupFactory->create();
        $lookupModel->load($orderId, 'order_id');

        if (!$lookupModel->getId()) {
            return null;
        }
        $lookupModel->setData('canceled', 1);
        $lookupModel->save();

        return;
    }


    public function sendCancellation($application_id, $order_total, $orderId)
    {
        // First get the application you wish to create an activation for.
        $application = (new \Divido\MerchantSDK\Models\Application())
            ->withId($application_id);
        $items       = [
            [
                'name'     => "Magento 2 Cancellation",
                'quantity' => 1,
                'price'    => $order_total * 100,
            ],
        ];
        // Create a new application activation model.
        $application_cancellation = (new \Divido\MerchantSDK\Models\ApplicationCancellation())
            ->withOrderItems($items);
        // Create a new activation for the application.
        $sdk                      = $this->getSdk();
        $response                 = $sdk->applicationCancellations()->createApplicationCancellation($application, $application_cancellation);
        $activation_response_body = $response->getBody()->getContents();

        $this->cancelLookup($orderId);
    }

    public function autoRefund($order)
    {
        // Check if it's a finance order
        $lookup = $this->getLookupForOrder($order);
        if ($lookup === null) {
            return false;
        }

        $applicationId = $lookup['application_id'];
        $order_total = $lookup['initial_cart_value'];
        $order_id = $lookup['order_id'];

        return $this->sendRefund($applicationId, $order_total, $order_id);
    }


    public function sendRefund($application_id, $order_total, $order_id)
    {
        $application = (new \Divido\MerchantSDK\Models\Application())
            ->withId($application_id);
        $items       = [
            [
                'name'     => "Magento 2 Refund",
                'quantity' => 1,
                'price'    => $order_total * 100,
            ],
        ];
        $application_refund = (new \Divido\MerchantSDK\Models\ApplicationRefund())
            ->withOrderItems($items)
            ->withComment('As per customer request.')
            ->withAmount($order_total * 100);
        // Create a new activation for the application.
        $sdk                      = $this->getSdk();
        $response                 = $sdk->applicationRefunds()->createApplicationRefund($application, $application_refund);
        $activation_response_body = $response->getBody()->getContents();
    }
}
