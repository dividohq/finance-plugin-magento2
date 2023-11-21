<?php

namespace Divido\DividoFinancing\Test\Unit\Model;

use Divido\DividoFinancing\Model\CreditRequest;
use PHPUnit\Framework\TestCase;

class CreditRequestTest extends TestCase
{

    public function test_validateWebhookRequest(){
        $bodyObj = $this->generateWebhookObj();

        $scopeConf = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConf->expects($this->once())
            ->method("getValue")
            ->with('payment/divido_financing/secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ->willReturn('');
        
        $stream = (new \Laminas\Diactoros\StreamFactory())
            ->createStream(json_encode($bodyObj));

        $request = new \Laminas\Diactoros\Request(
            "https://test.uri",
            "POST",
            $stream
        );
        
        $model = $this->createModel([
            'scopeConfig' => $scopeConf
        ]);
        $this->assertSame(
            $bodyObj->metadata->quote_hash,
            $model->validateWebhookRequest($request)->metadata->quote_hash
        );
    }

    public function test_validateWebhookRequestWithIncorrectEventThrowsException(){
        $bodyObj = $this->generateWebhookObj('some-other-event');

        $scopeConf = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConf->expects($this->once())
            ->method("getValue")
            ->with('payment/divido_financing/secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ->willReturn('');
        
        
        $stream = (new \Laminas\Diactoros\StreamFactory())->createStream(json_encode($bodyObj));

        $request = new \Laminas\Diactoros\Request(
            "https://test.uri",
            "POST",
            $stream
        );

        $this->expectException(\Divido\DividoFinancing\Exceptions\UnusedWebhookException::class);
        
        $model = $this->createModel([
            'scopeConfig' => $scopeConf
        ]);
        $model->validateWebhookRequest($request);
        
    }

    public function test_validateWebhookRequestWithUnusedStatusThrowsException(){
        $bodyObj = $this->generateWebhookObj('application-status-update', CreditRequest::STATUS_AWAITING_ACTIVATION);

        $scopeConf = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConf->expects($this->once())
            ->method("getValue")
            ->with('payment/divido_financing/secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ->willReturn('');
        
        
        $stream = (new \Laminas\Diactoros\StreamFactory())->createStream(json_encode($bodyObj));

        $request = new \Laminas\Diactoros\Request(
            "https://test.uri",
            "POST",
            $stream
        );

        $this->expectException(\Divido\DividoFinancing\Exceptions\UnusedWebhookException::class);
        
        $model = $this->createModel([
            'scopeConfig' => $scopeConf
        ]);
        $model->validateWebhookRequest($request); 
        
    }

    public function test_validRetrieveWebhookLookupReturnsLookup(){
        $webhookObj = json_decode(json_encode([
            "application" => '2b668aab-c4d0-43e4-8a66-4096e4e2a840',
            "metadata" => [
                "quote_id" => 12,
                'quote_hash' => 'something'
            ]
        ]), false);

        $lookup = $this->createMock(\Divido\DividoFinancing\Model\Lookup::class);

        $lookup->expects($this->once())
        ->method("load")
        ->with($webhookObj->metadata->quote_id)
        ->willReturnSelf();

        $lookup->expects($this->once())
        ->method("getId")
        ->willReturn("1");

        $salt = 'salt';
        $lookup->expects($this->once())
        ->method("getData")
        ->with('salt')
        ->willReturn($salt);

        $mockHelper = $this->createMock(\Divido\DividoFinancing\Helper\Data::class);
        $mockHelper->expects($this->once())
        ->method('hashQuote')
        ->with($salt, $webhookObj->metadata->quote_id)
        ->willReturn('something');

        $mockLookupFactory = $this->createMock(\Divido\DividoFinancing\Model\LookupFactory::class);
        $mockLookupFactory->expects($this->once())
        ->method("create")
        ->willReturn($lookup);

        $model = $this->createModel([
            "lookupFactory" => $mockLookupFactory,
            "data" => $mockHelper
        ]);

        $functionLookup = $model->retrieveWebhookLookup($webhookObj);

        $this->assertSame(
            $lookup,
            $functionLookup
        );
    }

    public function test_invalidRetrieveWebhookLookupThrowsMessageValidationException(){
        $webhookObj = json_decode(json_encode([
            "application" => '2b668aab-c4d0-43e4-8a66-4096e4e2a840',
            "metadata" => [
                "quote_id" => 12,
                'quote_hash' => 'something'
            ]
        ]), false);

        $lookup = $this->createMock(\Divido\DividoFinancing\Model\Lookup::class);

        $lookup->expects($this->once())
        ->method("load")
        ->with($webhookObj->metadata->quote_id)
        ->willReturnSelf();

        $lookup->expects($this->once())
        ->method("getId")
        ->willReturn("1");

        $salt = 'salt';
        $lookup->expects($this->once())
        ->method("getData")
        ->with('salt')
        ->willReturn($salt);

        $mockHelper = $this->createMock(\Divido\DividoFinancing\Helper\Data::class);
        $mockHelper->expects($this->once())
        ->method('hashQuote')
        ->with($salt, $webhookObj->metadata->quote_id)
        ->willReturn('somethingelse');

        $mockLookupFactory = $this->createMock(\Divido\DividoFinancing\Model\LookupFactory::class);
        $mockLookupFactory->expects($this->once())
        ->method("create")
        ->willReturn($lookup);

        $this->expectException(\Divido\DividoFinancing\Exceptions\MessageValidationException::class);

        $model = $this->createModel([
            "lookupFactory" => $mockLookupFactory,
            "data" => $mockHelper
        ]);

        $model->retrieveWebhookLookup($webhookObj);

    }

    public function test_MissingRetrieveWebhookLookupThrowsLookupNotFoundException(){
        $webhookObj = json_decode(json_encode([
            "application" => '2b668aab-c4d0-43e4-8a66-4096e4e2a840',
            "metadata" => [
                "quote_id" => 12
            ]
        ]), false);

        $lookup = $this->createMock(\Divido\DividoFinancing\Model\Lookup::class);

        $lookup->expects($this->once())
        ->method("load")
        ->with($webhookObj->metadata->quote_id)
        ->willReturnSelf();

        $lookup->expects($this->once())
        ->method("getId")
        ->willReturn(false);
        
        $mockLookupFactory = $this->createMock(\Divido\DividoFinancing\Model\LookupFactory::class);
        $mockLookupFactory->expects($this->once())
        ->method("create")
        ->willReturn($lookup);

        $this->expectException(\Divido\DividoFinancing\Exceptions\LookupNotFoundException::class);

        $model = $this->createModel([
            "lookupFactory" => $mockLookupFactory
        ]);

        $model->retrieveWebhookLookup($webhookObj);

    }
    

    public function data_provider_retrieveDividoOrderByQuoteId() :\Generator {
        yield 'Success' => [
            'orderId' => '1',
            'exception' => false
        ];

        yield 'Failure' => [
            'orderId' => '',
            'exception' => true
        ];
    }

    /**
     * @dataProvider data_provider_retrieveDividoOrderByQuoteId
     * @param string $orderId
     * @param bool $exception
     */
    public function test_retrieveDividoOrderByQuoteId(
        string $orderId,
        bool $exception
    ){
        $quoteId = "1";
        $salesOrderPaymentTable = "m243_sales_order_payment";
        $mockResourceConnection = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $mockResourceConnection->expects($this->once())
            ->method("getTableName")
            ->with('sales_order_payment')
            ->willReturn($salesOrderPaymentTable);

        $mockOrderCollection = $this->createMock(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
        $mockOrderCollection->expects($this->once())
            ->method("addAttributeToFilter")
            ->with('quote_id', $quoteId);

        $mockSelect = $this->createMock(\Magento\Framework\DB\Select::class);
        $mockSelect->expects($this->once())
        ->method('join')
        ->with(["sop" => $salesOrderPaymentTable], 'main_table.entity_id = sop.parent_id', ['method'])
        ->willReturnSelf();
        $mockSelect->expects($this->once())
        ->method('where')
        ->with('sop.method = ?', 'divido_financing')
        ->willReturnSelf();

        $mockOrderCollection->expects($this->once())
        ->method('getSelect')
        ->willReturn($mockSelect);

        $mockOrderCollection->expects($this->once())
        ->method('setOrder')
        ->with('created_at', 'desc');

        $dataObject = new \Magento\Framework\DataObject(['id' => $orderId]);

        $mockOrderCollection->expects($this->once())
        ->method('getFirstItem')
        ->willReturn($dataObject);

        $mockOrderRepo = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        if($exception){
            $this->expectException(\Divido\DividoFinancing\Exceptions\OrderNotFoundException::class);
        } else {
            $mockOrder = $this->createMock(\Magento\Sales\Model\Order::class);
            $mockOrderRepo->expects($this->once())
                ->method('get')
                ->with($orderId)
                ->willReturn($mockOrder);
        }

        $model = $this->createModel([
            'orderRepo' => $mockOrderRepo,
            'orderCollection' => $mockOrderCollection,
            'resourceConnection' => $mockResourceConnection
        ]);
        $model->retrieveDividoOrderByQuoteId($quoteId);
    }

    public function test_convertMagentoRequestToPsrRequest(){
        $headers = [
            'content-type' => ['application/json']
        ];
        $content = json_encode(['test' => true]);

        $mockMagentoRequest = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $mockMagentoRequest->expects($this->once())
            ->method("getMethod")
            ->willReturn('POST');
        $mockMagentoRequest->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('http://mock-request.uri');
        
        $mockMagentoRequest->expects($this->once())
        ->method('getHeaders')
        ->willReturn($headers);

        $mockMagentoRequest->expects($this->once())
        ->method('getContent')
        ->willReturn($content);

        $model = $this->createModel();
        $psrRequest = $model->convertMagentoRequestToPsrRequest(
            $mockMagentoRequest,
            new \Laminas\Diactoros\RequestFactory(),
            new \Laminas\Diactoros\StreamFactory()
        );

        $this->assertSame(
            'POST',
            $psrRequest->getMethod()
        );

        $this->assertSame(
            array_merge(['Host' => ['mock-request.uri']], $headers),
            $psrRequest->getHeaders()
        );

        $this->assertSame(
            $content,
            $psrRequest->getBody()->getContents()
        );
    }


    public function test_validateQuotePriceFromLookupReturnsError(){
        $mockQuote = $this->createMock(\Magento\Quote\Model\Quote::class);

        $mockLookup = $this->createMock(\Divido\DividoFinancing\Model\Lookup::class);
        $mockLookup->expects($this->once())
            ->method("getData")
            ->with('initial_cart_value')
            ->willReturn(211.99);

        $this->expectException(\Divido\DividoFinancing\Exceptions\MessageValidationException::class);
        
        $model = $this->createModel();
        $model->validateQuotePriceFromLookup($mockQuote, $mockLookup);

    }

    public function data_provider_setOrderStatus() :\Generator {
        yield 'Holded Status' => [
            'status' => CreditRequest::STATUS_REFERRED,
            'func' => 'hold'
        ];

        yield 'Default Status' => [
            'status' => CreditRequest::STATUS_DECLINED,
            'func' => 'setStatus'
        ];

        yield 'Unused Status' => [
            'status' => CreditRequest::STATUS_FULFILLED,
            'func' => null
        ];
    }

    /**
     * @dataProvider data_provider_setOrderStatus
     * @param string $status
     * @param string|null $func
     */
    public function test_setOrderStatus(
        string $status,
        ?string $func
    ){
        $scopeConf = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConf->expects($this->once())
            ->method("getValue")
            ->with('payment/divido_financing/order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ->willReturn('');
        
        $model = $this->createModel([
            'scopeConfig' => $scopeConf
        ]);

        $mockOrder = $this->createMock(\Magento\Sales\Model\Order::class);
        if($func !== null){
            $mockOrder->expects($this->once())
                ->method($func);
        } else {
            $mockOrder->expects($this->never())
                ->method('hold');
            $mockOrder->expects($this->never())
                ->method('setStatus');
        }

        $model->setOrderStatus($mockOrder, $status);
    }

    public function data_provider_updateLookup() :\Generator {
        yield 'Cancelled Status' => [
            'status' => CreditRequest::STATUS_CANCELED,
            'expected' => 'canceled'
        ];

        yield 'Declined Status' => [
            'status' => CreditRequest::STATUS_DECLINED,
            'expected' => 'declined'
        ];

        yield 'Referred Status' => [
            'status' => CreditRequest::STATUS_REFERRED,
            'expected' => 'referred'
        ];

        yield 'Unused Status' => [
            'status' => CreditRequest::STATUS_FULFILLED,
            'expected' => null
        ];
    }
    
    /**
     * @dataProvider data_provider_updateLookup
     * @param string $status
     * @param string|null $expected
     */
    public function test_updateLookupByStatus(
        string $status,
        ?string $expected
    ){
        $lookup = $this->createMock(\Divido\DividoFinancing\Model\Lookup::class);
        if($expected !== null){
            $lookup->expects($this->once())
                ->method('setData')
                ->with($expected, 1);
            $lookup->expects($this->once())->method('save');
        }else {
            $lookup->expects($this->never())->method('setData');
            $lookup->expects($this->never())->method('save');
        }

        $model = $this->createModel();
        $model->updateLookupByStatus($lookup, $status);
        
    }

    public function test_getAllStatusTransitions(){
        $confStatus = 'testing';
        $scopeConf = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConf->expects($this->once())
            ->method("getValue")
            ->with('payment/divido_financing/order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ->willReturn($confStatus);
        
        $model = $this->createModel([
            'scopeConfig' => $scopeConf
        ]);

        $this->assertSame(
            $model->getAllStatusTransitions(),
            array_merge(
                CreditRequest::STATUS_TRANSITIONS, 
                [CreditRequest::COMPLETION_STATUS => $confStatus]
            )
        );
    }

    public function test_createOrder(){
        $orderId = 1;
        $webhookObj = json_decode(json_encode([
            "application" => '2b668aab-c4d0-43e4-8a66-4096e4e2a840',
            "metadata" => [
                "quote_id" => 12
            ]
        ]), false);

        $mockQuoteManagement = $this->createMock(\Magento\Quote\Model\QuoteManagement::class);
        $mockQuoteManagement->expects($this->once())
            ->method("placeOrder")
            ->with($webhookObj->metadata->quote_id)
            ->willReturn($orderId);

        $mockLookup = $this->createMock(\Divido\DividoFinancing\Model\Lookup::class);
        $mockLookup->expects($this->any())
            ->method('setData')
            ->withConsecutive(
                ['order_id', $orderId],
                ['application_id', $webhookObj->application]
            );
        $mockLookup->expects($this->once())->method('save');

        $mockOrder = $this->createMock(\Magento\Sales\Model\Order::class);

        $mockOrderRepo = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $mockOrderRepo->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($mockOrder);

        $overrides = [
            'quoteManagement' => $mockQuoteManagement,
            'orderRepo' => $mockOrderRepo
        ];

        $model = $this->createModel($overrides);

        $this->assertSame(
            $mockOrder,
            $model->createOrder($webhookObj, $mockLookup)
        );
    
    }

    public function test_sendWebhookResponse(){
        $newStatus = 400;
        $newMessage = 'This is a test';

        $pluginVersion = '2.1.0';
        $magentoVersion = '3.1.5';

        $mockHelper = $this->createMock(\Divido\DividoFinancing\Helper\Data::class);
        $mockHelper->expects($this->once())
            ->method('getVersion')
            ->willReturn($pluginVersion);
        $mockHelper->expects($this->once())
            ->method('getMagentoVersion')
            ->willReturn($magentoVersion);

        $response = [
            'message' => $newMessage,
            'ecom_platform' => 'Magento_2',
            'plugin_version' => $pluginVersion,
            'ecom_platform_version' => $magentoVersion
        ];

        $mockRes = $this->createMock(\Magento\Framework\Webapi\Rest\Response::class);
        $mockRes->expects($this->once())
            ->method('setHeader')
            ->with('Content-Type', 'application/json', true)
            ->willReturnSelf();
        $mockRes->expects($this->once())
            ->method('setStatusHeader')
            ->with($newStatus)
            ->willReturnSelf();
        $mockRes->expects($this->once())
            ->method('setBody')
            ->with(json_encode($response))
            ->willReturnSelf();
        $mockRes->expects($this->once())
            ->method('sendResponse');
        
        $model = $this->createModel([
            'response'=>$mockRes,
            'data' => $mockHelper
        ]);

        $model->sendWebhookResponse($newStatus, $newMessage);
    }

    public function test_version(){

        $pluginVersion = '2.1.0';
        $magentoVersion = '3.1.5';

        $mockHelper = $this->createMock(\Divido\DividoFinancing\Helper\Data::class);
        $mockHelper->expects($this->once())
            ->method('getVersion')
            ->willReturn($pluginVersion);
        $mockHelper->expects($this->once())
            ->method('getMagentoVersion')
            ->willReturn($magentoVersion);

        $response = [
            'ecom_platform' => 'Magento_2',
            'plugin_version' => $pluginVersion,
            'ecom_platform_version' => $magentoVersion
        ];

        $mockRes = $this->createMock(\Magento\Framework\Webapi\Rest\Response::class);
        $mockRes->expects($this->once())
            ->method('setHeader')
            ->with('Content-Type', 'application/json', true)
            ->willReturnSelf();
        $mockRes->expects($this->once())
            ->method('setStatusHeader')
            ->with(200)
            ->willReturnSelf();
        $mockRes->expects($this->once())
            ->method('setBody')
            ->with(json_encode($response))
            ->willReturnSelf();
        $mockRes->expects($this->once())
            ->method('sendResponse');
        
        $model = $this->createModel([
            'data' => $mockHelper,
            'response'=>$mockRes
        ]);
        $model->version();
    }

    private function createModel(array $overrides = []){

        $defaults = [
           'scopeConfig' => $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class),
           'request' => $this->createMock(\Magento\Framework\App\Request\Http::class),
           'response' => $this->createMock(\Magento\Framework\Webapi\Rest\Response::class),
           'orderRepo' => $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class),
           'cartRepo' => $this->createMock(\Magento\Quote\Api\CartRepositoryInterface::class),
           'quoteManagement' => $this->createMock(\Magento\Quote\Model\QuoteManagement::class),
           'resource' => $this->createMock(\Magento\Framework\Module\ResourceInterface::class),
           'data' => $this->createMock(\Divido\DividoFinancing\Helper\Data::class),
           'lookupFactory' => $this->createMock(\Divido\DividoFinancing\Model\LookupFactory::class),
           'logger' => $this->createMock(\Divido\DividoFinancing\Logger\Logger::class),
           'orderCollection' => $this->createMock(\Magento\Sales\Model\ResourceModel\Order\Collection::class),
           'resourceConnection' => $this->createMock(\Magento\Framework\App\ResourceConnection::class),
           'requestFactory' => new \Laminas\Diactoros\RequestFactory(),
           'streamFactory' => new \Laminas\Diactoros\StreamFactory()
        ];
        $defaults = array_merge($defaults, $overrides);
        $model = new CreditRequest(
            $defaults['scopeConfig'],
            $defaults['request'],
            $defaults['response'],
            $defaults['orderRepo'],
            $defaults['cartRepo'],
            $defaults['quoteManagement'],
            $defaults['resource'],
            $defaults['data'],
            $defaults['lookupFactory'],
            $defaults['logger'],
            $defaults['orderCollection'],
            $defaults['resourceConnection'],
            $defaults['requestFactory'],
            $defaults['streamFactory']
        );

        return $model;
    }

    private function generateWebhookObj(
        string $event = "application-status-update",
        string $status = CreditRequest::STATUS_READY
    ) :object {
        return (object)[
            "event" => $event,
            "status" => $status,
            "firstName" => "Ann",
            "lastName" => "Heselden",
            "phoneNumber" => "02012312321",
            "emailAddress" => "ann.heselden@divido.com",
            "application" => "2b668aab-c4d0-43e4-8a66-4096e4e2a840",
            "reference" => "",
            "proposal" => "1",
            "metadata" => (object) [
                "quote_id" => "14",
                "quote_hash" => "af2fc890d548c10bda28c63166ec95694bdefcc100debd70e5c90a94c1cebdf8"
            ]
        ];
    }

}
