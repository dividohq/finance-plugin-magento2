<?php

namespace Divido\DividoFinancing\Helper;

use Divido\DividoFinancing\Exceptions\RefundException;
use \Divido\DividoFinancing\Model\LookupFactory;
use Divido\MerchantSDK\Environment;
use Divido\MerchantSDK\Exceptions\InvalidApiKeyFormatException;
use Divido\MerchantSDK\Exceptions\InvalidEnvironmentException;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Divido\DividoFinancing\Helper\EndpointHealthCheckTrait;
use Divido\DividoFinancing\Model\RefundItems;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\ClientFactory as GuzzleClientFactory;
use Laminas\Diactoros\RequestFactory as LaminasRequestFactory;
use Divido\DividoFinancing\Proxies\MerchantApiPubProxy;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    use EndpointHealthCheckTrait;

    const CACHE_DIVIDO_TAG   = 'divido_cache';
    const CACHE_PLANS_KEY    = 'divido_plans';
    const CACHE_PLANS_TTL    = 3600;
    const CACHE_PLATFORM_KEY = 'platform_env';
    const CACHE_PLATFORM_TTL = 3600;
    const CALLBACK_PATH      = 'rest/V1/divido/update/';
    const REDIRECT_PATH      = 'divido/financing/success/';
    const CHECKOUT_PATH      = 'checkout/';
    const VERSION            = '2.10.1';
    const WIDGET_LANGUAGES   = ["en", "fi" , "no", "es", "da", "fr", "de", "pe"];
    const SHIPPING           = 'SHPNG';
    const DISCOUNT           = 'DSCNT';
    const V4_CALCULATOR_URL  = 'https://cdn.divido.com/widget/v4/divido.calculator.js';
    const SUCCESSFUL_REFUND_STATUS = 201;
    const PAYMENT_METHOD = 'divido_financing';

    const REFUND_CANCEL_REASONS = [
        "novuna" => [
            "ALTERNATIVE_PAYMENT_METHOD_USED" => "Alternative Payment Method Used",
            "GOODS_FAULTY" => "Goods Faulty",
            "GOODS_NOT_RECEIVED" => "Goods Not Received",
            "GOODS_RETURNED" => "Goods Returned",
            "LOAN_AMENDED" => "Loan Amended",
            "NOT_GOING_AHEAD" => "Not Going Ahead",
            "NO_CUSTOMER_INFORMATION" => "No Customer Information"
        ]
    ];

    const NON_PARTIAL_LENDERS = [
        "novuna"
    ];

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
    private $localeResolver;
    private $clientFactory;
    private $merchantApiProxy;
    private $requestFactory;
    private $quoteRepository;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Divido\DividoFinancing\Logger\Logger $logger,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resource,
        LookupFactory $lookupFactory,
        UrlInterface $urlBuilder,
        ProductFactory $productFactory,
        \Magento\Framework\Locale\Resolver $localeResolver,
        GuzzleClientFactory $clientFactory,
        LaminasRequestFactory $requestFactory,
        \Magento\Quote\Model\QuoteRepository $quoteRepository
    ) {

        $this->config         = $scopeConfig;
        $this->logger         = $logger;
        $this->cache          = $cache;
        $this->cart           = $cart;
        $this->storeManager   = $storeManager;
        $this->resource       = $resource;
        $this->lookupFactory  = $lookupFactory;
        $this->urlBuilder     = $urlBuilder;
        $this->productFactory = $productFactory;
        $this->localeResolver = $localeResolver;
        $this->clientFactory = $clientFactory;
        $this->requestFactory = $requestFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Gets the API's Environment name
     *
     * @param string|bool $apiKey The config API key (will default to get from settings)
     *
     * @return bool|string
     */
    public function getEnvironment($apiKey = false)
    {
        $apiKey = (false === $apiKey) ? $this->getApiKey() : $apiKey;

        // Validate the API key format
        try{
            Environment::validateApiKeyFormat($apiKey);
        }catch (InvalidApiKeyFormatException $e){
            $this->logger->error($e->getMessage());
            return false;
        }

        // Get the Environment Name from the API key
        try{
            $environment = Environment::getEnvironmentFromAPIKey($apiKey);
            $this->logger->info('getEnv: '.$environment);
            return $environment;
        }catch (InvalidApiKeyFormatException | InvalidEnvironmentException $e){
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function getMerchantApiProxy(){
        if(null === $this->merchantApiProxy){
            $apiKey = $this->getApiKey();
            $environmentUrl = $this->getEnvironmentUrl($apiKey);

            $client = $this->clientFactory->create([
                'config' => [
                    'base_uri' => $environmentUrl
                ]
            ]);

            $this->merchantApiProxy = new MerchantApiPubProxy(
                $client,
                $this->requestFactory,
                $apiKey,
                $this->logger
            );
        }

        return $this->merchantApiProxy;

    }

    /**
     * @param $apiKey
     * @return bool
     * @throws \Divido\MerchantSDK\Exceptions\InvalidApiKeyFormatException
     */
    public function validateApiKeyFormat($apiKey = false): bool
    {
        $apiKey = (false === $apiKey) ? $this->getApiKey() : $apiKey;

        return Environment::validateApiKeyFormat($apiKey);
    }

    /**
     * Get Finance Platform Environment function
     *
     *  @return string the tenancy environment
     */
    public function getPlatformEnv() :string
    {
        $environmentURl = $this->getEnvironmentUrl();

        // Unique cache key for environment url with the hashed environment_url as key
        $environmentNameCacheKey = sprintf(
            '%s_%s',
            self::CACHE_PLATFORM_KEY,
            md5($environmentURl)
        );

        if ($env = $this->cache->load($environmentNameCacheKey)) {
            return $env;
        } else {
            $responseObj = $this->getMerchantApiProxy()->getEnvironment();
    
            if ($this->debug()) {
                $this->logger->info('getPlatformEnv:'.serialize($decoded));
            }

            $environment = $responseObj->data->environment;

            $this->cache->save(
                $environment,
                $environmentNameCacheKey,
                [self::CACHE_DIVIDO_TAG],
                self::CACHE_PLATFORM_TTL
            );

            return $decoded->data->environment;
        }
    }

    public function getBranding()
    {
        $plans = $this->getGlobalSelectedPlans();
        if(!$plans || !isset($plans[0]->lender->branding)){
            return '{}';
        }
        $branding = $plans[0]->lender->branding;
        $branding->lender = $plans[0]->lender->name;
        return json_encode($branding);
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
        $options = [
            'flags' => FILTER_FLAG_ALLOW_FRACTION
        ];

        return filter_var(
            str_replace(',', '.', strval($threshold)),
            FILTER_SANITIZE_NUMBER_FLOAT,
            $options
        );
    }

    public function getActive()
    {
        $active = $this->config->getValue(
            'payment/divido_financing/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $active;
    }

    /**
     * @param $apiKey
     * @return string
     */
    private function getPlansCacheKey($apiKey)
    {
        // Try to get environment URL as part of the cache key
        try {
            $environmentUrl = $this->getEnvironmentUrl($apiKey);
        } catch (RuntimeException $e) {
            // If there is a problem getting the environment url, skip it.
            $environmentUrl = '';
        }

        return sprintf(
            '%s_%s',
            self::CACHE_PLANS_KEY,
            md5($apiKey . $environmentUrl)
        );
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

        $cacheKey = $this->getPlansCacheKey($apiKey);

        if ($plans = $this->cache->load($cacheKey)) {
            if ($this->debug()) {
                $this->logger->info('Cached Plans Key:' . $cacheKey);
            }
            $plans = unserialize($plans);
            return $plans;
        }

        $response = $this->getPlans();

        if (!isset($response[0]->id)) {
            $this->logger->error('Could not get financing plans.');
            $this->cleanCache();
            return [];
        }

        $plans = $response;

        $this->cache->save(
            serialize($plans),
            $cacheKey,
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
            if($plan->credit_amount->minimum_amount > ($grandTotal*100) || $plan->credit_amount->maximum_amount < ($grandTotal*100)) {
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
            $productPlans = ($productPlans == null) ? [] : explode(',', $productPlans);
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
        $quote = $this->cart->getQuote();
        if ($quoteId != null) {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->quoteRepository->get($quoteId);
        }
        $shipAddr    = $quote->getShippingAddress();
        $country     = $shipAddr->getCountry();
        $billingAddr = $quote->getBillingAddress();
        $shippingAddress = $this->getApplicationAddressFromMagentoAddress($shipAddr);
        $billingAddress  = $this->getApplicationAddressFromMagentoAddress($billingAddr);

        if (!empty($email) && empty($quote->getCustomerEmail())) {
            $quote->setCustomerEmail($email);
            $this->quoteRepository->save($quote);
        }

        if(empty($email)){
            $email = $quote->getCustomerEmail();
        }

        $customer = [
            'title'             => '',
            'firstName'         => $billingAddr->getFirstName(),
            'middleNames'       => $billingAddr->getMiddleName(),
            'lastName'          => $billingAddr->getLastName(),
            'country'           => $billingAddr->getCountry(),
            'postcode'          => $billingAddr->getPostcode(),
            'email'             => $email,
            'phoneNumber'       => $this->stripWhite($billingAddr->getTelephone()),
            'addresses'         => [$billingAddress],
            'shippingAddress'   => $shippingAddress,
        ];

        $products = [];
        foreach ($quote->getAllItems() as $item) {
            if ($item->getParentItemId() == null) {
                $products[] = [
                    'type'     => 'product',
                    'name'     => $item->getName(),
                    'quantity' => (int)$item->getQty(),
                    'price'    => round($item->getPriceInclTax() * 100),
                    'sku'      => $item->getSku(),
                ];
            }
        }
        $totals = $quote->getTotals();
        $grandTotal = $totals['grand_total']->getValue();
        $deposit = round($depositAmount);
        $shipping = $shipAddr->getShippingInclTax() * 100;
        if (! empty($shipping)) {
            $products[] = [
                'type'     => 'product',
                'name'     => 'Shipping & Handling',
                'quantity' => (int) 1,
                'price'    => (int) $shipping,
                'sku'      => self::SHIPPING,
            ];
        }
        $discount = $shipAddr->getDiscountAmount();
        if (! empty($discount)) {
            $products[] = [
                'type'     => 'product',
                'name'     => 'Discount',
                'quantity' => (int) 1,
                'price'    => (int) ($discount * 100),
                'sku'      => self::DISCOUNT,
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

        $application = (new \Divido\MerchantSDK\Models\Application())
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
                    'initial_cart_value'    => $grandTotal,
                    'quote_id'              => $quoteId,
                    'quote_hash'            => $quoteHash,
                    'ecom_platform'         => 'Magento_2',
                    'ecom_platform_version' => $this->getMagentoVersion(),
                    'ecom_base_url'         => $this->returnUrl(),
                    'plugin_version'        => $this->getVersion()

                ]
            );
        
        $hmac = null;
        $secret = $this->config->getValue(
            'payment/divido_financing/secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if(!empty($secret)){
            $hmac = $this->create_signature(json_encode($application->getPayload()), $secret);
        }

        try {
            $responseObj = $this->getMerchantApiProxy()->postApplication($application, $hmac);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
        
        if ($this->debug()){
            $debug = $responseObj->data;
            unset($debug->applicants);
            $this->logger->info("Application Payload: ".serialize($debug));
        }
        $result_id = $responseObj->data->id;
        $result_redirect = $responseObj->data->urls->application_url;
        
        $lookupModel = $this->lookupFactory->create();
        $lookupModel->load($quoteId, 'quote_id');
        $lookupModel->setData('quote_id', $quoteId);
        $lookupModel->setData('salt', $salt);
        $lookupModel->setData('deposit_value', $deposit);
        $lookupModel->setData('proposal_id', $result_id);
        $lookupModel->setData('initial_cart_value', $grandTotal);
        $lookupModel->save();
        return $result_redirect;
        
    }


    /**
     * Updates the metadata of the Application to include the Magento 2 internal Order id
     *
     * @param $applicationId The Divido Application ID
     * @param $orderId The ID Magento attributes to the order
     */
    public function updateMerchantReference($applicationId, $orderId)
    {
        try{

            $application = (new \Divido\MerchantSDK\Models\Application())
                ->withId($applicationId)
                ->withApplicants(null)
                ->withOrderItems(null)
                ->withMerchantReference($orderId)
                ->withMetadata([
                    "merchant_reference" => $orderId
                ]);
            $this->logger->info("updating order id ". (string)$orderId);
            $this->getMerchantApiProxy()->updateApplication($application);

        } catch(\Exception $e){
            $this->logger->info("Error updating application" ,[$e->getMessage()]);
        }

    }

    public function hashQuote($salt, $quoteId)
    {
        return hash('sha256', $salt.$quoteId);
    }

    public function stripWhite($item){
        return str_replace(' ', '', $item);
    }

    public function getApiKey()
    {
        $apiKey = $this->config->getValue(
            'payment/divido_financing/api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $apiKey;
    }

    public function getCalcConfApiUrl()
    {
        $calcConfApiUrl = $this->config->getValue(
            'payment/divido_financing/calc_conf_api_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $calcConfApiUrl;
    }

    /**
     * @param string|false $apiKey
     * @return array Array of configuration data from MerchantSDK, for more information look in MerchantSDK\Environment::CONFIGURATION
     * @throws RuntimeException
     */
    private function getMerchantSdkEnvironmentConfiguration($apiKey = false): array
    {
        // Get environment name from ApiKey
        $env = $this->getEnvironment($apiKey);

        // If we could not find the current env from api key, throw an error
        if (empty($env)) {
            if ($this->debug()) {
                $this->logger->info('Could not find environment');
            }

            throw new RuntimeException(
                new Phrase('Could not find environment from api key')
            );
        }

        // If env does not exists in the configuration, throw error
        if (!array_key_exists($env, \Divido\MerchantSDK\Environment::CONFIGURATION)) {
            if ($this->debug()) {
                $this->logger->info('Could not determine configuration for DividoFinancing, environment: ' . $env);
            }

            throw new RuntimeException(
                new Phrase('Could not find environment configuration')
            );
        }

        // All good, return the configuration array from MerchantSDK
        return \Divido\MerchantSDK\Environment::CONFIGURATION[$env];
    }

    /**
     * Returns Environment URL from MerchantSDK Configuration based on environment
     *
     * @param string|false $apiKey Defaults to get from Magento config
     * @return string
     *
     * @throws RuntimeException
     */
    public function getEnvironmentUrl($apiKey = false): string
    {
        // Try to first get from Magento config
        $configEnvironmentUrl = $this->config->getValue(
            'payment/divido_financing/environment_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // If there is an url in the config, use that.
        if (!empty($configEnvironmentUrl)) {
            return $configEnvironmentUrl;
        }

        // Get configuration from MerchantSDK
        try{
            $merchantSdkEnvironmentConfiguration = $this->getMerchantSdkEnvironmentConfiguration($apiKey);
        }catch (RuntimeException $e){
            if ($this->debug()) {
                $this->logger->info($e->getMessage());
            }

            // We might not be able to get the configuration from the API key, if the API key is missing etc.
            // In that case we do not want to throw and error, we just want to return an empty string that the UI can use
            // and populate the 'environment_url' input with.
            return '';
        }

        // If the environment url is not valid
        if (!array_key_exists('base_uri', $merchantSdkEnvironmentConfiguration)) {
            if ($this->debug()) {
                $this->logger->info('Could not find base_uri in configuration');
            }

            throw new RuntimeException(
                new Phrase('Could not find base_uri in configuration')
            );
        }

        // Get URL from configuration
        $environmentUrl = $merchantSdkEnvironmentConfiguration['base_uri'];

        // If the environment url is not valid
        if (!is_string($environmentUrl) || empty($environmentUrl)) {
            if ($this->debug()) {
                $this->logger->info('Error while trying to determine Environment URL for DividoFinancing');
            }

            throw new RuntimeException(
                new Phrase('Could not determine URL for DividoFinancing')
            );
        }

        return $environmentUrl;
    }

    public function getShortApiKey()
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return '';
        }

        $shortKey = explode('.', $apiKey)[0];
        return strtolower($shortKey);
    }

    /**
     * Returns the url to calculator JavaScript file
     * @return string
     */
    public function getScriptUrl(): string
    {
        if ($this->debug()) {
            $this->logger->info('GetScript URL HElper');
        }

        if($this->getCalcConfApiUrl()){
            return self::V4_CALCULATOR_URL;
        }
        $apiKey = $this->getApiKey();
        $scriptUrl= "//cdn.divido.com/widget/v3/divido.calculator.js";

        if (empty($apiKey)) {
            return $scriptUrl;
        }

        $tenantName = $this->getPlatformEnv();
        if ($this->debug()) {
            $this->logger->info('platform env:'.$tenantName);
        }

        // Get environment part of script url
        $environmentName = $this->getEnvironment($apiKey);
        if ($this->debug()) {
            $this->logger->info('Environment: ' . $environmentName);
        }

        // Namespace for script, each item in the array will be added with a dot (".") between them
        $namespaceParts = [];

        // Adding tenant name to namespace
        $namespaceParts[] = $tenantName;

        // If anything but production
        if($environmentName !== Environment::PRODUCTION){
            // Adding environment to namespace
            $namespaceParts[] = $environmentName;
        }

        // Render script URL
        $scriptUrl= sprintf(
            '//cdn.divido.com/widget/v3/%s.calculator.js',
            implode('.', $namespaceParts)
        );

        if ($this->debug()) {
            $this->logger->info('Url:'.$scriptUrl);
        }

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
    public function getApplicationAddressFromMagentoAddress(\Magento\Customer\Model\Address\AbstractAddress $address) :array
    {
        $addressText = implode(
            ', ', 
            [
                ...$address->getStreet(), 
                $address->getCity(),
                $address->getRegion()
            ]
        );
        $addressArray = [
            'postcode' => $address->getPostcode(),
            'text' => $addressText,
            'country' => $address->getCountry(),
            'co' => $address->getCountry()
        ];

        return array_filter($addressArray);
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

    public function getAutoRefund(){
        $autoRefund = $this->config->getValue(
            'payment/divido_financing/auto_refund',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $autoRefund;
    }

    public function getConfigValue(string $term){
        $value = $this->config->getValue(
            sprintf('payment/divido_financing/%s', $term),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $value;
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
        try {
            $responseObj = $this->getMerchantApiProxy()->getFinancePlans();
            return $responseObj->data;
        } catch (\Exception $e) {
            $this->logger->warning("Could not retrieve finance plans", ['error' => $e->getMessage()]);
            return [];
        }
    }
    public function setFulfilled($application_id, $order_total, $shipping_method = null, $tracking_numbers = null)
    {
        $items = [
            [
                'name'     => "Order Activation",
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
        try{
            $this->getMerchantApiProxy()->postActivation($application_id, $application_activation);
        } catch(\Exception $e){
            $this->logger->warning(
                'Could not activate application', 
                [
                    'application'=> $application_id, 
                    'reason' => $e->getMessage()
                ]
            );
            throw $e;
        }
    }

    public function autoCancel($order, $reason=null)
    {
        // Check if it's a finance order
        $lookup = $this->getLookupForOrder($order);
        if ($lookup === null) {
            return false;
        }

        $applicationId = $lookup['application_id'];
        $order_total = $lookup['initial_cart_value'];

        $order_id = $lookup['order_id'];

        $autoCancellation = $this->getConfigValue('auto_cancellation');

        if (! $autoCancellation) {
            return $this->cancelLookup($order_id);
        }
        return $this->sendCancellation($applicationId, $order_total, $order_id, $reason);
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


    public function sendCancellation($application_id, $order_total, $orderId, string $reason=null)
    {
        // Create a new application activation model.
        $application_cancellation = (new \Divido\MerchantSDK\Models\ApplicationCancellation())
            ->withOrderItems([
                [
                    'name'     => "Order Cancellation",
                    'quantity' => 1,
                    'price'    => $order_total * 100,
                ],
            ]);
        
        if($reason !== null){
            $application_cancellation = $application_cancellation->withReason($reason);
        }
        // Create a new activation for the application.
        try{
            $this->getMerchantApiProxy()->postCancellation($application_id, $application_cancellation);
        } catch( \Exception $e){
            $this->logger->warning(
                'Could not cancel application', 
                [
                    'application'=> $application_id, 
                    'reason' => $e->getMessage()
                ]
            );
            throw $e;
        }

        $this->cancelLookup($orderId);
    }

    public function autoRefund($order, int $amount, RefundItems $refundItems, ?string $reason=null)
    {
        // Check if it's a finance order
        $lookup = $this->getLookupForOrder($order);
        if ($lookup === null) {
            throw new RefundException("Could not retrieve order locally");
        }

        $applicationId = $lookup['application_id'];
        $order_id = $lookup['order_id'];

        $autoRefund = $this->getAutoRefund();

        if ($autoRefund) {
            try{
                $response = $this->sendRefund($applicationId, $amount, $refundItems, $reason);
            } catch (\Exception $e){
                $this->logger->warning('Could not refund order', [
                    'order ID' => $order_id,
                    'application ID' => $applicationId,
                    'response' => $e->getMessage() 
                ]);
                throw new RefundException("Can not refund order: Refund attempt unsuccessful");
            }
        }

    }

    /**
     * Creates a refund request and sends it via the SDK.
     * Returns the returned ResponseInterface object.
     *
     * @param string $application_id
     * @param integer $amount
     * @param RefundItems $refundItems
     * @param string|null $reason
     * @return ResponseInterface
     */
    public function sendRefund(string $application_id, int $amount, RefundItems $refundItems, ?string $reason=null)
    {
        
        $items = [];
        /** @var \Divido\DividoFinancing\Model\RefundItem $item  */
        foreach($refundItems as $item){
            $items[] = [
                'name'     => $item->getName(),
                'quantity' => $item->getQuantity(),
                'price'    => $item->getAmount(),
            ];
        }

        $application_refund = (new \Divido\MerchantSDK\Models\ApplicationRefund())
            ->withOrderItems($items)
            ->withComment('As per customer request.')
            ->withAmount($amount);
        
        if($reason !== null){
          $application_refund = $application_refund->withReason($reason);
        }

        // Create a new activation for the application.
        $response = $this->getMerchantApiProxy()->postRefund($application_id, $application_refund);
        
        return $response;
    }

    public function debug()
    {
        $debug = $this->config->getValue(
            'payment/divido_financing/debug',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $debug;
    }

    public function getDescription()
    {
            return $this->config->getValue(
                'payment/divido_financing/description',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }

    /**
     * Retrieve the language override value from the merchant configuration
     *
     * @return int A boolean integer with 1 signifying the language should be overriden
     */
    public function getLanguageOverride():int
    {
            return $this->config->getValue(
                'payment/divido_financing/language_override',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }

    public function getWidgetFootnote()
    {
            return $this->config->getValue(
                'payment/divido_financing/widget_footer',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }

    public function getWidgetButtonText()
    {
            return $this->config->getValue(
                'payment/divido_financing/widget_button_text',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }

    public function getWidgetMode()
    {
            return $this->config->getValue(
                'payment/divido_financing/widget_mode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }

    public function getVersion()
    {
        return self::VERSION;
    }

    public function getMagentoVersion()
    {
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $this->_objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        return $productMetadata->getVersion();
    }

    public function returnUrl()
    {
        return $this->urlBuilder->getBaseUrl();
    }

    /**
     * Returns the ISO code of the store's native language
     *
     * @return string|null The ISO code or null if config states otherwise or code not supported
     */
    public function getWidgetLanguage():?string {
        if(0 === $this->getLanguageOverride()){
            return null;
        }

        $locale = $this->localeResolver->getLocale();
        if($this->debug()){
            $this->logger->info("Locale: {$locale}");
        }
        list($code, $country)  = explode("_", $locale);
        if(!in_array($code, self::WIDGET_LANGUAGES)){
            return null;
        }
        return $code;
    }

    /**
     * Generates a signature hash, based on the API key secret
     *
     * @param string $payload A json string of the application
     * @param string $secret The API key secret set in the merchant portal
     * @return string The signature hash
     */
    public function create_signature(string $payload, string $secret):string {
        $hmac = hash_hmac('sha256', $payload, $secret, true);
        $signature = base64_encode($hmac);
        return $signature;
    }

    public function getApplicationFromOrder($order) :object {
        $lookup = $this->getLookupForOrder($order);
        if ($lookup === null) {
            throw new \Exception("Could not find application locally");
        }

        $applicationId = $lookup['application_id'];
        $getApplicationResObj = $this->getMerchantApiProxy()->getApplication($applicationId);

        return $getApplicationResObj->data;
    }

    public function getRefundAmount(RefundItems $refundItems){
        $refundAmount = 0;
        /** @var \Divido\DividoFinancing\Model\RefundItem $ri */
        foreach($refundItems as $ri){
            $refundAmount += $ri->getAmount() * $ri->getQuantity();
        }
        return $refundAmount;
    }
}
