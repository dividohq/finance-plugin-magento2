<?php

namespace Divido\DividoFinancing\Proxies;

use Psr\Http\Client\ClientInterface;
use Divido\MerchantSDK\Models\Application;
use Divido\MerchantSDK\Models\ApplicationActivation;
use Divido\MerchantSDK\Models\ApplicationCancellation;
use Divido\MerchantSDK\Models\ApplicationRefund;
use Divido\DividoFinancing\Logger\Logger;
use Divido\DividoFinancing\Traits\ValidationTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestFactoryInterface;


/**
 * A proxy between the Merchant API Pub and the HttpApiWrapper
 */
class MerchantApiPubProxy{
    use ValidationTrait;

    const HTTP_METHOD_POST = 'POST';
    const HTTP_METHOD_GET = 'GET';
    const HTTP_METHOD_PATCH = 'PATCH';

    const PATHS = [
        self::HTTP_METHOD_GET => [
            'APPLICATION' => '/applications/%s',
            'HEALTH' => '/health',
            'PLANS' => '/finance-plans',
            'ENVIRONMENT' => '/environment'
        ],
        self::HTTP_METHOD_POST => [
            'APPLICATION' => '/applications',
            'ACTIVATION' => '/applications/%s/activations',
            'REFUND' => '/applications/%s/refunds',
            'CANCELLATION' => '/applications/%s/cancellations'
        ],
        self::HTTP_METHOD_PATCH => [
            'APPLICATION' => '/applications/%s'
        ]
    ];
    const EXPECTED_RESPONSE_CODES = [
        self::HTTP_METHOD_GET => [
            'APPLICATION' => 200,
            'HEALTH' => 200,
            'PLANS' => 200,
            'ENVIRONMENT' => 200
        ],
        self::HTTP_METHOD_POST => [
            'APPLICATION' => 201,
            'ACTIVATION' => 201,
            'REFUND' => 201,
            'CANCELLATION' => 201
        ],
        self::HTTP_METHOD_PATCH => [
            'APPLICATION' => 200
        ]
    ];

    const HEADER_KEYS_API = 'X-DIVIDO-API-KEY';
    const HEADER_KEYS_SHARED_SECRET = 'X-Divido-Hmac-Sha256';
    
    private ClientInterface $client;

    private string $environmentUrl;

    private string $apiKey;

    private Logger $logger;

    private RequestFactoryInterface $requestFactory;

    public function __construct(
        ClientInterface $client,
        string $environmentUrl,
        RequestFactoryInterface $requestFactoryInterface,
        string $apiKey,
        Logger $logger
    ){
        $this->client = $client;
        $this->setEnvironmentUrl($environmentUrl);
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->requestFactory = $requestFactoryInterface;
    }

    public function request(
        string $method,
        string $endpoint,
        array $additionalParams = []
    ): ResponseInterface {

        $params = array_merge_recursive([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                self::HEADER_KEYS_API => $this->apiKey
            ]
        ], $additionalParams);

        $request = $this->requestFactory->createRequest(
            $method, 
            sprintf("%s%s", $this->environmentUrl, $endpoint)
        );

        foreach($params['headers'] as $key => $value) {
            $request = $request->withAddedHeader($key, $value);
        }
    
        if(isset($params['body'])){
            $request->getBody()->write($params['body']);
        }
        
        try{
            $response = $this->client->sendRequest($request);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf("MerchantApiPubProxy - Received the following error: %s", $e->getMessage()),
                ['method' => $method, 'endpoint' => $endpoint]
            );

            throw $e;
        }

        return $response;
        
    }

    /**
     * Makes a request to the Merchant API Pub health endpoint and returns true if API is healthy
     *
     * @return boolean
     */
    public function getHealth():bool{

        $response = $this->request(self::HTTP_METHOD_GET, self::PATHS[self::HTTP_METHOD_GET]['HEALTH']);

        $this->checkStatusCode($response);
        
        return $response->getBody()->getContents() === 'OK';
    }

    /**
     * Makes a request to the environment endpoint, and returns a json
     * object of the response body
     *
     * @return object
     */
    public function getEnvironment():object{

        $response = $this->request(self::HTTP_METHOD_GET, self::PATHS[self::HTTP_METHOD_GET]['ENVIRONMENT']);
        
        return $this->validateResponse(
            $response, 
            'environment', 
            self::EXPECTED_RESPONSE_CODES[self::HTTP_METHOD_GET]['ENVIRONMENT']
        );
    }

    /**
     * Makes a request to the finance plans endpoint, and returns a json
     * object of the response body
     *
     * @return object
     */
    public function getFinancePlans() :object{
        $response = $this->request(self::HTTP_METHOD_GET, self::PATHS[self::HTTP_METHOD_GET]['PLANS']);

        return $this->validateResponse(
            $response, 
            'finance-plans', 
            self::EXPECTED_RESPONSE_CODES[self::HTTP_METHOD_GET]['PLANS']
        );
    }

    public function getApplication(string $applicationId) :object{
        $path = sprintf(
            self::PATHS[self::HTTP_METHOD_GET]['APPLICATION'],
            $applicationId
        );

        $response = $this->request(self::HTTP_METHOD_GET, $path);
        
        return $this->validateResponse(
            $response, 
            'application', 
            self::EXPECTED_RESPONSE_CODES[self::HTTP_METHOD_GET]['APPLICATION']
        );
    }

    /**
     * Makes a request to create an application, and returns a json
     * object of the response body
     *
     * @param Application $application
     * @param string|null $hmac
     * @return object
     */
    public function postApplication(Application $application, ?string $hmac = null): object{

        $body = $application->getJsonPayload();

        $params = ['body' => $body];

        if ($hmac !== null) {
            $params['headers'][self::HEADER_KEYS_SHARED_SECRET] = $hmac;
        }
        
        $response = $this->request(
            self::HTTP_METHOD_POST,
            self::PATHS[self::HTTP_METHOD_POST]['APPLICATION'],
            $params
        );

        return $this->validateResponse(
            $response, 
            'application', 
            self::EXPECTED_RESPONSE_CODES[self::HTTP_METHOD_POST]['APPLICATION']
        );
    }

    /**
     * Makes a request to create an activation, and returns a json
     * object of the response body
     *
     * @param string $applicationId
     * @param ApplicationActivation $activation
     * @return object
     */
    public function postActivation(string $applicationId, ApplicationActivation $activation): object{

        $body = $activation->getJsonPayload();

        $path = sprintf(
            self::PATHS[self::HTTP_METHOD_POST]['ACTIVATION'],
            $applicationId
        );

        $response = $this->request(self::HTTP_METHOD_POST, $path, ['body' => $body]);

        return $this->validateResponse(
            $response, 
            'activation', 
            self::EXPECTED_RESPONSE_CODES[self::HTTP_METHOD_POST]['ACTIVATION']
        );
    }

    /**
     * Makes a request to create a cancellation, and returns a json
     * object of the response body
     *
     * @param string $applicationId
     * @param ApplicationCancellation $cancellation
     * @return object
     */
    public function postCancellation(string $applicationId, ApplicationCancellation $cancellation): object{

        $path = sprintf(
            self::PATHS[self::HTTP_METHOD_POST]['CANCELLATION'],
            $applicationId
        );

        $body = $cancellation->getJsonPayload();

        $response = $this->request(self::HTTP_METHOD_POST, $path, ['body' => $body]);

        return $this->validateResponse(
            $response, 
            'cancellation', 
            self::EXPECTED_RESPONSE_CODES[self::HTTP_METHOD_POST]['CANCELLATION']
        );
    }

    /**
     * Makes a request to create a refund, and returns a json
     * object of the response body
     *
     * @param string $applicationId
     * @param ApplicationRefund $refund
     * @return object
     */
    public function postRefund(string $applicationId, ApplicationRefund $refund): object{
        
        $path = sprintf(
            self::PATHS[self::HTTP_METHOD_POST]['REFUND'],
            $applicationId
        );

        $body = $refund->getJsonPayload();
        $response = $this->request(self::HTTP_METHOD_POST, $path, ['body' => $body]);

        return $this->validateResponse(
            $response, 
            'refund', 
            self::EXPECTED_RESPONSE_CODES[self::HTTP_METHOD_POST]['REFUND']
        );
    }

    
    /**
     * Makes a request to update an application, and returns a json
     * object of the response body
     *
     * @param Application $application
     * @return object
     */
    public function updateApplication(Application $application): object{

        $body = $application->getJsonPayload();

        $path = sprintf(
            self::PATHS[self::HTTP_METHOD_PATCH]['APPLICATION'],
            $application->getId()
        );

        $response = $this->request(self::HTTP_METHOD_PATCH, $path, ['body' => $body]);

        return $this->validateResponse(
            $response, 
            'application', 
            self::EXPECTED_RESPONSE_CODES[self::HTTP_METHOD_PATCH]['APPLICATION']
        );
    }

    /**
     * Attempts to turn the body of the response into a json object
     *
     * @param ResponseInterface $response
     * @return object
     */
    private function getResponseBodyObj(ResponseInterface $response):object{
        return json_decode(
            $response->getBody()->getContents(), 
            false, 
            512, 
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * Get the value of environmentUrl
     */
    public function getEnvironmentUrl(): string
    {
        return $this->environmentUrl;
    }

    /**
     * Set the value of environmentUrl
     */
    public function setEnvironmentUrl(string $environmentUrl): self
    {
        $this->environmentUrl = $environmentUrl;

        return $this;
    }
}
