<?php

declare(strict_types=1);

namespace Divido\DividoFinancing\Traits;

use Divido\DividoFinancing\Exceptions\MessageValidationException;
use \Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

trait ValidationTrait
{
    private $DIVIDO_HMAC_HEADER_NAME = 'X-DIVIDO-HMAC-SHA256';

    /**
     * Checks the status code and validates the response payload against the schema
     *
     * @param ResponseInterface $response
     * @param string $schema
     * @param integer $expectedStatusCode
     * @return object
     */
    public function validateResponse(ResponseInterface $response, string $schema, int $expectedStatusCode = 200): object
    {
        $this->checkStatusCode($response, $expectedStatusCode);
        return $this->validateMessage($response, $schema);
    }

    /**
     * Checks the status code of the response is the same as the expected code
     *
     * @param ResponseInterface $response
     * @param integer $expectedStatusCode
     * @return void
     * @throws MerchantApiBadResponseException if response status code doesn't match expected
     */
    public function checkStatusCode(ResponseInterface $response, int $expectedStatusCode = 200) :void{
        if($response->getStatusCode() !== $expectedStatusCode){
            throw new MerchantApiBadResponseException(
                sprintf(
                    "Expected status code %d: Received %d.",
                    $expectedStatusCode,
                    $response->getStatusCode()
                ),
                json_decode($response->getBody()->getContents(), true)['code'] ?? 500001,
                json_decode($response->getBody()->getContents(), true)['context'] ?? ''
            );
        }
    }

    public function validateRequest(RequestInterface $request, string $schema, ?string $expectedHmac=null) :object {
        if($expectedHmac === null){
            return $this->validateMessage($request, $schema);
        }

        $hmacHeaders = $request->getHeader($this->DIVIDO_HMAC_HEADER_NAME);
        if(count($hmacHeaders) !== 1){
            $amount = (count($hmacHeaders) > 1) ? 'many' : 'few';
            throw new MessageValidationException(sprintf("Received too %s HMAC Headers", $amount));
        }

        return $this->validateMessage($request, $schema);
    }

    /**
     * Ensures the 
     *
     * @param MessageInterface $message
     * @param string $schema
     * @return object
     * @throws \Exception if schema could not be found
     * @throws MessageValidationException if body does not match supplied schema
     */
    public function validateMessage(MessageInterface $message, string $schema) :object
    {
        $validator = new Validator();
        $raw = (string) $message->getBody();
        
        $body = json_decode($raw, null, 512, JSON_THROW_ON_ERROR);

        $path = sprintf('%s/../Schemas/%s.json', __DIR__, $schema);
        $realPath = realpath($path);
        if ($realPath === false) {
            throw new \Exception("Could not find relevant schema file ({$path})");
        }
        // validate
        $validator->validate(
            $body,
            ['$ref' => "file://{$path}"],
            Constraint::CHECK_MODE_APPLY_DEFAULTS
        );

        if (!$validator->isValid()) {
            throw new MessageValidationException (
                implode(
                    " | ", 
                    array_map(
                        function($error){
                            return sprintf("%s: %s", $error['property'], $error['message']);
                        },
                        $validator->getErrors()
                    )
                )
            );
        }

        return $body;
    }
}