<?php

namespace Divido\DividoFinancing\Helper;

use \Divido\DividoFinancing\Model\LookupFactory;
use Divido\MerchantSDK\Environment;
use Divido\MerchantSDK\Exceptions\InvalidApiKeyFormatException;
use Divido\MerchantSDK\Exceptions\InvalidEnvironmentException;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Divido\DividoFinancing\Helper\EndpointHealthCheckTrait;
use Throwable;

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
    const VERSION            = '2.5.0';
    const WIDGET_LANGUAGES   = ["en", "fi" , "no", "es", "da", "fr", "de", "pe"];

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
        \Magento\Framework\Locale\Resolver $localeResolver
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
    }

    /**
     * Gets the SDK's Environment name for the given api key
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
     *  @param [string] $api_key - The platform API key.
     */
    public function getPlatformEnv()
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
            $sdk      = $this->getSdk();
            $response = $sdk->platformEnvironments()->getPlatformEnvironment();
            $finance_env = $response->getBody()->getContents();
            $decoded = json_decode($finance_env);
            if ($this->debug()) {
                $this->logger->info('getPlatformEnv:'.serialize($decoded));
            }

            $environment = $decoded->data->environment;

            $this->cache->save(
                $environment,
                $environmentNameCacheKey,
                [self::CACHE_DIVIDO_TAG],
                self::CACHE_PLATFORM_TTL
            );

            return $decoded->data->environment;
        }
    }

    /**
     * @return \Divido\MerchantSDK\Client
     * @throws RuntimeException
     */
    public function getSdk(): \Divido\MerchantSDK\Client
    {
        $apiKey = $this->getApiKey();
        if ($this->debug()) {
            $this->logger->info('Get SDK');
        }

        // Getting environment depending on how apiKey looks
        $env = $this->getEnvironment($apiKey);
        if ($this->debug()) {
            $this->logger->info('Get SDK'.$env);
        }

        // Get environment URL from config or calculate one from apikey
        $environmentUrl = $this->getEnvironmentUrl($apiKey);
        if ($this->debug()) {
            $this->logger->info('Environment URL ' . $environmentUrl);
        }

        // Create what is needed to create and return a MerchantSDK Client
        $client = new \GuzzleHttp\Client();

        $httpClientWrapper = new \Divido\MerchantSDK\HttpClient\HttpClientWrapper(
            new \Divido\MerchantSDKGuzzle6\GuzzleAdapter($client),
            $environmentUrl,
            $apiKey
        );

        return new \Divido\MerchantSDK\Client($httpClientWrapper, $env);
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
        $store = $this->storeManager->getStore();

        $customer = [
            'title'             => '',
            'firstName'         => $shipAddr->getFirstName(),
            'middleNames'       => $shipAddr->getMiddleName(),
            'lastName'          => $shipAddr->getLastName(),
            'country'           => $country,
            'postcode'          => $shipAddr->getPostcode(),
            'email'             => $email,
            'phoneNumber'       => $this->stripWhite($shipAddr->getTelephone()),
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
                'sku'      => 'SHPNG',
            ];
        }
        $discount = $shipAddr->getDiscountAmount();
        if (! empty($discount)) {
            $products[] = [
                'type'     => 'product',
                'name'     => 'Discount',
                'quantity' => (int) 1,
                'price'    => (int) ($discount * 100),
                'sku'      => 'DSCNT',
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

        $sdk = $this->getSdk();
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

        if (!empty($secret)) {
            $secret = $this->create_signature(json_encode($application->getPayload()), $secret);
            $this->logger->debug('Hmac Version'.$secret);
            $response = $sdk
                ->applications()
                ->createApplication(
                    $application,
                    [],
                    ['Content-Type' => 'application/json', 'X-Divido-Hmac-Sha256' => $secret]
                );
        }else{
            $this->logger->debug('Non Hmac');
            $response = $sdk
                ->applications()
                ->createApplication($application,[],['Content-Type' => 'application/json']);
        }

        $application_response_body = $response->getBody()->getContents();

        $decode = json_decode($application_response_body);
        if ($this->debug()){
            $debug = $decode->data;
            unset($debug->applicants);
            $this->logger->info("Application Payload: ".serialize($debug));
        }
        $result_id = $decode->data->id;
        $result_redirect = $decode->data->urls->application_url;
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


    /**
     * Updates the metadata of the Application to include the Magento 2 internal Order id
     *
     * @param $applicationId The Divido Application ID
     * @param $orderId The ID Magento attributes to the order
     */
    public function updateMerchantReference($applicationId, $orderId)
    {
        try{
            $sdk  = $this->getSdk();
            $application = $sdk->applications()->getSingleApplication($applicationId);
            $application = json_decode($application->getBody()->getContents());

            $financePlanId =  $application->data->finance_plan->id;
            $orderItems = $application->data->order_items;
            $applicants = $application->data->applicants;

            $application    = (new \Divido\MerchantSDK\Models\Application())
                ->withId($applicationId)
                ->withFinancePlanId($financePlanId)
                ->withApplicants($applicants)
                ->withOrderItems($orderItems)
                ->withMetadata([
                    "merchant_reference" => $orderId
                ]);
            $this->logger->info("updating order id ". (string)$orderId);
            $response = $sdk->applications()->updateApplication($application, [], ['Content-Type' => 'application/json']);

            $applicationResponseBody = $response->getBody()->getContents();

            $this->logger->info('update response');
            $this->logger->info(serialize($applicationResponseBody));

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

    /**
     * Returns the url to calculator JavaScript file
     * @return string
     */
    public function getScriptUrl(): string
    {
        if ($this->debug()) {
            $this->logger->info('GetScript URL HElper');
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
    public function getAddressDetail($addressObject)
    {
        $street = str_replace("\n", " ", $addressObject['street']);
        $addressText     = implode(' ', [$street, $addressObject['city'],$addressObject['postcode']]);
        $addressArray = [
            'postcode' => $addressObject['postcode'],
            'text'     => $addressText,
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
}
