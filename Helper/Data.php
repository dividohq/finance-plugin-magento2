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

    public function getConnection()
    {
        if (! $this->connection) {
            $this->connection = $this->resource->getConnection('core_write');
        }

        return $this->connection;
    }

    public function cleanCache()
    {
        $this->cache->clean('matchingTag', [self::CACHE_DIVIDO_TAG]);
    }

    public function getSuffix()
    {
        $suffix = $this->config->getValue(
            'payment/divido_financing/product_page_widget_suffix',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    
        return $suffix;
    }


    public function getPrefix()
    {
        
        $prefix = $this->config->getValue(
            'payment/divido_financing/product_page_widget_prefix',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        return $prefix;
    }
    
    public function getProductSelection(){
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
            $plans = unserialize($plans);
            return $plans;
        }

        \Divido::setMerchant($apiKey);

        $response = \Divido_Finances::all();

        if ($response->status !== 'ok') {
            $this->logger->addError('Divido: Could not get financing plans.');
            $this->cleanCache();
            return [];
        }

        $plans = $response->finances;

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
            $planMinTotal = $grandTotal - ($grandTotal * ($plan->min_deposit / 100));
            if ($planMinTotal < $plan->min_amount) {
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

        $dbConn  = $this->getConnection();
        $tblCpev = $this->resource->getTableName('catalog_product_entity_varchar');
        $tblEava = $this->resource->getTableName('eav_attribute');

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

    public function creditRequest($planId, $depositPercentage, $email, $quoteId = null)
    {
        $apiKey = $this->getApiKey();
        \Divido::setMerchant($apiKey);

        $secret = $this->config->getValue(
            'payment/divido_financing/secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    
        if ($secret) {
            \Divido::setSharedSecret($secret);
        }
       
        $quote       = $this->cart->getQuote();
        if ($quoteId != null) {
            $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $quote = $this->_objectManager->create('Magento\Quote\Model\Quote')->load($quoteId);
        }
        $shipAddr    = $quote->getShippingAddress();
        $country     = $shipAddr->getCountryId();
        $billingAddr = $quote->getBillingAddress();
        //Transform Addresses to be compatible with our form
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

        $language = 'EN';
        $store = $this->storeManager->getStore();
        $currency = $store->getCurrentCurrencyCode();

        $customer = [
            'title'             => '',
            'first_name'        => $shipAddr->getFirstName(),
            'middle_name'       => $shipAddr->getMiddleName(),
            'last_name'         => $shipAddr->getLastName(),
            'country'           => $country,
            'postcode'          => $shipAddr->getPostcode(),
            'email'             => $email,
            'mobile_number'     => '',
            'phone_number'      => $shipAddr->getTelephone(),
            'shippingAddress'   => $shippingAddress,
            'address'           => $billingAddress,
        ];

        $products = [];
        foreach ($quote->getAllItems() as $item) {
            if($item->getParentItemId() == null){
                $products[] = [
                    'type'     => 'product',
                    'text'     => $item->getName(),
                    'quantity' => $item->getQty(),
                    'value'    => $item->getPriceInclTax(),
                ];
            }
        }
        $totals = $quote->getTotals();
        $grandTotal = $totals['grand_total']->getValue();
        $deposit = round(($depositPercentage/100) * $grandTotal, 2);
        $minDeposit = $this->getSuggestedDeposit($quote, true);
        
        if($deposit < $minDeposit){
            $deposit = $minDeposit;
            }
            
        $shipping = $shipAddr->getShippingAmount();
        if (! empty($shipping)) {
            $products[] = [
                'type'     => 'product',
                'text'     => 'Shipping & Handling',
                'quantity' => '1',
                'value'    => $shipping,
            ];
        }
        $discount = $shipAddr->getDiscountAmount();
        if (! empty($discount)) {
            $products[] = [
                'type'     => 'product',
                'text'     => 'Discount',
                'quantity' => '1',
                'value'    => $discount,
            ];
        }
        $quoteId   = $quote->getId();
        $salt      = uniqid('', true);
        $quoteHash = $this->hashQuote($salt, $quoteId);
        $response_url = $this->urlBuilder->getBaseUrl() . self::CALLBACK_PATH;
        $checkout_url = $this->urlBuilder->getUrl(self::CHECKOUT_PATH);
        
        if (!empty( $this->getCustomCheckoutUrl()))
        {
            $checkout_url = $this->urlBuilder->getUrl($this->getCustomCheckoutUrl());
        }


        
        $redirect_url = $this->urlBuilder->getUrl(
            self::REDIRECT_PATH,
            ['quote_id' => $quoteId]
        );
        if (!empty($this->getCustomRedirectUrl()))
        {
            $redirect_url = $this->getCustomRedirectUrl().'/quote_id/'.$quoteId;
        }

        $requestData = [
            'merchant' => $apiKey,
            'deposit'  => $deposit,
            'finance'  => $planId,
            'country'  => $country,
            'language' => $language,
            'currency' => $currency,
            'metadata' => [
                'quote_id'   => $quoteId,
                'quote_hash' => $quoteHash,
            ],
            'customer'     => $customer,
            'products'     => $products,
            'response_url' => $response_url,
            'redirect_url' => $redirect_url,
            'checkout_url' => $checkout_url,
            'initial_cart_value' => $grandTotal,
        ];
        $response = \Divido_CreditRequest::create($requestData);
        if ($response->status == 'ok') {
            $lookupModel = $this->lookupFactory->create();
            $lookupModel->load($quoteId, 'quote_id');
            $lookupModel->setData('quote_id', $quoteId);
            $lookupModel->setData('salt', $salt);
            $lookupModel->setData('deposit_value', $deposit);
            $lookupModel->setData('proposal_id', $response->id);
            $lookupModel->setData('initial_cart_value', $grandTotal);
            $lookupModel->save();
            return $response->url;
        } else {
            if ($response->status === 'error') {
                throw new \Magento\Framework\Exception\LocalizedException(__($response->error));
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
        return "//cdn.divido.com/calculator/v2.1/production/js/template.divido.js";
    }

    public function plans2list($plans)
    {
        $plansBare = array_map(function ($plan) {
            return $plan->id;
        }, $plans);

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
            'proposal_id'    => $lookupModel->getData('proposal_id'),
            'application_id' => $lookupModel->getData('application_id'),
            'deposit_amount' => $lookupModel->getData('deposit_value'),
            'initial_cart_value' => $lookupModel->getData('initial_cart_value')
        ];
    }

    public function autoFulfill($order)
    {
        // Check if it's a divido order
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

        return $this->setFulfilled($applicationId, $shippingMethod, $trackingNumbers);
    }

    public function setFulfilled($applicationId, $shippingMethod = null, $trackingNumbers = null)
    {
        $apiKey = $this->getApiKey();
        $params = [
            'application'    => $applicationId,
            'deliveryMethod' => $shippingMethod,
            'trackingNumber' => $trackingNumbers
        ];

        \Divido::setMerchant($apiKey);
        \Divido_Fulfillment::fulfill($params);
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
        $street = str_replace("\n"," ", $addressObject['street']);
        $addressText     = implode(' ', [$street,$addressObject['city'],$addressObject['postcode']]);
        $addressArray = [
            'postcode'          => '',
            'street'            => '',
            'flat'              => '',
            'buildingNumber'    => '',
            'buildingName'      => '',
            'town'              => '',
            'flat'              => '',
            'text'              => $addressText,
        ];

        return $addressArray;
    }

    public function getSuggestedDeposit($quote, $displayAsNumber=null){

        $display = null;
        $grandTotal=$this->getGrandTotal($quote);
        $displayPercentage = 0;

        foreach ($quote->getAllItems() as $item) {
        $product = $this->productFactory->create()->load($item->getProductId());
        $dispAttr = $product->getResource()->getAttribute('bundle_code');
        if($dispAttr){
            $dispAttrCode = $dispAttr->getAttributeCode();
            $bundleCode = $product->getData($dispAttrCode);
            if($bundleCode !=''){
                $display=$item->getPriceInclTax();
                $displayPercentage=($item->getPriceInclTax()/$grandTotal)*100;
                $displayPercentage=number_format($displayPercentage,'2');
                }
            }
    public function getCustomCheckoutUrl()
    {
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
        if(!$displayAsNumber){
            return $displayPercentage;
        }
        return $display;

    }

    public function updateInvoiceStatus($order)
    {
              // Check if it's a divido order
                $lookup = $this->getLookupForOrder($order);
                if ($lookup === null) {
                    error_log('Not divido order');
                    return false;
                }

                $invoiceStatus = $this->config->getValue(
                    'payment/divido_financing/invoice_status',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );

                if (! $invoiceStatus) {
                    return false;
                }

                $currentStatus  = $order->getData('status');
                $previousStatus = $order->getOrigData('status');

                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                $order->setStatus($invoiceStatus);
                $order->addStatusToHistory($order->getStatus(), 'ORDER  processed successfully with reference');
                $order->save();

    }


}
