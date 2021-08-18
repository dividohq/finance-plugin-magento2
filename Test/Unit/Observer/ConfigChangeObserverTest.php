<?php

namespace Divido\DividoFinancing\Test\Unit\Observer;

use Divido\DividoFinancing\Helper\Data;
use Divido\DividoFinancing\Observer\ConfigChangeObserver;
use Magento\Framework\Event;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

class ConfigChangeObserverTest extends TestCase
{
    public function executeShouldReturnEarlyIfPropertiesNotChangedDataProvider(): \Generator
    {
        yield 'FooAndBar none of the xpaths' => [
            [
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            0
        ];

        yield 'Only ApiKey changed' => [
            [
                ConfigChangeObserver::CONFIG_XPATH_API_KEY,
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            1
        ];

        yield 'Only Environment URL changed' => [
            [
                ConfigChangeObserver::CONFIG_XPATH_ENVIRONMENT_URL,
                uniqid('foo_'),
                uniqid('bar_'),
            ],
            1
        ];
    }

    /**
     * @dataProvider executeShouldReturnEarlyIfPropertiesNotChangedDataProvider
     * @param array $eventData
     * @param int $expectedExecutionTimes
     */
    public function test_executeShouldReturnEarlyIfPropertiesNotChanged(
        array $eventData,
        int $expectedExecutionTimes
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

        $mockedDataInstance->expects($this->exactly($expectedExecutionTimes))
            ->method('getApiKey');

        $configChangeObserver->execute(
            $observer
        );
    }
}
