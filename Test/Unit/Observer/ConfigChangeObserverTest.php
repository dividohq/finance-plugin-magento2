<?php

namespace Divido\DividoFinancing\Test\Unit\Observer;

use Divido\DividoFinancing\Helper\Data;
use Divido\DividoFinancing\Observer\ConfigChangeObserver;
use Divido\MerchantSDK\Client;
use Divido\MerchantSDK\Exceptions\InvalidApiKeyFormatException;
use Magento\Framework\Event;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

class ConfigChangeObserverTest extends TestCase
{
    public function data_provider_shouldOnlyCallEndpointCheckIfURLorAPIKeyHasChanged(): \Generator
    {
        yield 'FooAndBar none of the xpaths' => [
            [
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            false // Do NOT check the endpoint
        ];

        yield 'Only ApiKey changed' => [
            [
                ConfigChangeObserver::CONFIG_XPATH_API_KEY,
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            true
        ];

        yield 'Only Environment URL changed' => [
            [
                ConfigChangeObserver::CONFIG_XPATH_ENVIRONMENT_URL,
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            true
        ];
    }

    /**
     * @dataProvider data_provider_shouldOnlyCallEndpointCheckIfURLorAPIKeyHasChanged
     * @param array $eventData
     * @param bool $shouldCheckEndpointHealth
     */
    public function test_shouldOnlyCallEndpointCheckIfURLorAPIKeyHasChanged(
        array $eventData,
        bool $shouldCheckEndpointHealth
    ): void {
        $mockedDataInstance = $this->createMock(Data::class);
        $mockedMessageManager = $this->createMock(ManagerInterface::class);

        $configChangeObserver = new ConfigChangeObserver(
            $mockedDataInstance,
            $mockedMessageManager
        );

        $observer = $this->createMock(\Magento\Framework\Event\Observer::class);

        $event = new Event(
            [
                'changed_paths' => $eventData
            ]
        );

        $observer->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);

        $mockProxy = $this->createMock(\Divido\DividoFinancing\Proxies\MerchantApiPubProxy::class);
        $mockProxy->expects($this->exactly(
                $shouldCheckEndpointHealth === true ? 1 : 0
            ))
            ->method('getHealth')
            ->willReturn(true);

        $mockedDataInstance->expects(
            $this->exactly(
                $shouldCheckEndpointHealth === true ? 1 : 0
            )
        )
            ->method('getMerchantApiProxy')
            ->willReturn($mockProxy);

        $configChangeObserver->execute(
            $observer
        );
    }

    public function test_ifGettingSDKThrowsErrorShouldAddErrorMessage(): void
    {
        $mockedDataInstance = $this->createMock(Data::class);
        $mockedMessageManager = $this->createMock(ManagerInterface::class);

        $configChangeObserver = new ConfigChangeObserver(
            $mockedDataInstance,
            $mockedMessageManager
        );

        $observer = $this->createMock(\Magento\Framework\Event\Observer::class);

        $event = new Event(
            [
                'changed_paths' => [
                    ConfigChangeObserver::CONFIG_XPATH_ENVIRONMENT_URL,
                    ConfigChangeObserver::CONFIG_XPATH_API_KEY,
                ]
            ]
        );

        $observer->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);

        $mockedDataInstance->expects($this->never())
            ->method('getEndpointHealthCheckResult');

        $mockProxy = $this->createMock(\Divido\DividoFinancing\Proxies\MerchantApiPubProxy::class);
        $mockProxy->expects($this->once())
            ->method('getHealth')
            ->willReturn(false);
        $mockedDataInstance->expects($this->once())
            ->method('getMerchantApiProxy')
            ->willReturn($mockProxy);
            //->willThrowException(new RuntimeException(new Phrase('Some error message')));

        $mockedMessageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with('Error, could not validate the health of endpoint, please check the "environment_url" setting');

        $configChangeObserver->execute(
            $observer
        );
    }

    public function messageShouldReflectResponseFromGetHealthCheckResultDataProvider(): \Generator
    {
        yield "when false is returned" => [
            // Result of health check
            false,
            // Expected error message
            'Error, could not validate the health of endpoint, please check the "environment_url" setting',
            // Expected success message
            null,
        ];

        yield "when true is returned" => [
            true,
            null,
            null,
        ];
    }

    /**
     * @dataProvider messageShouldReflectResponseFromGetHealthCheckResultDataProvider
     * @param $sdkHealthCheckResult
     * @param string|null $expectedErrorMessage
     * @param string|null $expectedSuccessMessage
     */
    public function test_messageShouldReflectResponseFromGetHealthCheckResult(
        $sdkHealthCheckResult,
        ?string $expectedErrorMessage,
        ?string $expectedSuccessMessage
    ): void {
        $mockedDataInstance = $this->createMock(Data::class);

        $mockedSdkClient = $this->createMock(Client::class);
        $mockedMessageManager = $this->createMock(ManagerInterface::class);

        $configChangeObserver = new ConfigChangeObserver(
            $mockedDataInstance,
            $mockedMessageManager
        );

        $observer = $this->createMock(\Magento\Framework\Event\Observer::class);

        $event = new Event(
            [
                'changed_paths' => [
                    ConfigChangeObserver::CONFIG_XPATH_ENVIRONMENT_URL,
                    ConfigChangeObserver::CONFIG_XPATH_API_KEY,
                ]
            ]
        );

        $observer->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);
        
        $mockProxy = $this->createMock(\Divido\DividoFinancing\Proxies\MerchantApiPubProxy::class);
        $mockProxy->expects($this->once())
            ->method('getHealth')
            ->willReturn($sdkHealthCheckResult);

        $mockedDataInstance->expects($this->once())
            ->method('getMerchantApiProxy')
            ->willReturn($mockProxy);

        if ($expectedErrorMessage) {
            $mockedMessageManager->expects($this->once())
                ->method('addErrorMessage')
                ->with($expectedErrorMessage);
        } else {
            $mockedMessageManager->expects($this->never())
                ->method('addErrorMessage');
        }

        if ($expectedSuccessMessage) {
            $mockedMessageManager->expects($this->once())
                ->method('addSuccessMessage')
                ->with($expectedSuccessMessage);
        } else {
            $mockedMessageManager->expects($this->never())
                ->method('addSuccessMessage');
        }

        $configChangeObserver->execute(
            $observer
        );
    }

    public function data_provider_shouldCheckApiKeyValidityIfItHasChanged(): \Generator
    {
        yield 'FooAndBar none of the xpaths' => [
            [
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            false // Do NOT check the endpoint
        ];

        yield 'Only ApiKey changed' => [
            [
                ConfigChangeObserver::CONFIG_XPATH_API_KEY,
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            true
        ];

        yield 'Only Environment URL changed' => [
            [
                ConfigChangeObserver::CONFIG_XPATH_ENVIRONMENT_URL,
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            false
        ];

        yield 'Both Environment URL and api key changed' => [
            [
                ConfigChangeObserver::CONFIG_XPATH_ENVIRONMENT_URL,
                ConfigChangeObserver::CONFIG_XPATH_API_KEY,
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            true
        ];
    }

    /**
     * @dataProvider data_provider_shouldCheckApiKeyValidityIfItHasChanged
     * @param array $eventData
     * @param bool $shouldCheckApiKeyValidity
     */
    public function test_shouldCheckApiKeyValidity(
        array $eventData,
        bool $shouldCheckApiKeyValidity
    ): void {
        $mockedDataInstance = $this->createMock(Data::class);
        $mockedMessageManager = $this->createMock(ManagerInterface::class);

        $configChangeObserver = new ConfigChangeObserver(
            $mockedDataInstance,
            $mockedMessageManager
        );

        $mockProxy = $this->createMock(\Divido\DividoFinancing\Proxies\MerchantApiPubProxy::class);
        $mockProxy->expects($this->any())
            ->method('getHealth')
            ->willReturn(true);
        $mockedDataInstance->expects($this->any())
        ->method('getMerchantApiProxy')
        ->willReturn($mockProxy);

        $observer = $this->createMock(\Magento\Framework\Event\Observer::class);

        $event = new Event(
            [
                'changed_paths' => $eventData
            ]
        );

        $observer->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);

        $mockedDataInstance->expects(
            $this->exactly(
                $shouldCheckApiKeyValidity === true ? 1 : 0
            )
        )
            ->method('validateApiKeyFormat')
            ->willReturn(true);

        $configChangeObserver->execute(
            $observer
        );
    }

    public function test_errorMessageGeneratedInExceptionShouldBeAddedToMessageManager(): void
    {
        $mockedDataInstance = $this->createMock(Data::class);
        $mockedMessageManager = $this->createMock(ManagerInterface::class);

        $configChangeObserver = new ConfigChangeObserver(
            $mockedDataInstance,
            $mockedMessageManager
        );

        $errorMessage = uniqid('message_');

        $mockedDataInstance->expects($this->once())
            ->method('validateApiKeyFormat')
            ->willThrowException(
                new InvalidApiKeyFormatException($errorMessage)
            );
        
        // Health check is OK
        $mockProxy = $this->createMock(\Divido\DividoFinancing\Proxies\MerchantApiPubProxy::class);
        $mockProxy->expects($this->once())
            ->method('getHealth')
            ->willReturn(true);

        $mockedDataInstance->expects($this->once())
            ->method('getMerchantApiProxy')
            ->willReturn($mockProxy);

        $observer = $this->createMock(\Magento\Framework\Event\Observer::class);

        $event = new Event(
            [
                'changed_paths' => [
                    ConfigChangeObserver::CONFIG_XPATH_API_KEY,
                    uniqid('bar_'),

                ]
            ]
        );

        $observer->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);


        // Check that the correct error message is added to message manager
        $mockedMessageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with($errorMessage);

        // Execute
        $configChangeObserver->execute($observer);
    }
}
