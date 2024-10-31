<?php

declare(strict_types=1);

namespace Zodimo\FRPTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Zodimo\BaseReturnTest\MockClosureTrait;
use Zodimo\FRP\Events\ExternalSignalValueEvent;
use Zodimo\FRP\Runtime;
use Zodimo\FRP\SignalConfigFactoryService;
use Zodimo\FRP\SignalFactoryService;
use Zodimo\FRP\SignalService;
use Zodimo\FRPTesting\FrpTestingEnvironmentFactoryTrait;

/**
 * @internal
 *
 * @coversNothing
 */
class SignalServiceTest extends TestCase
{
    use MockClosureTrait;
    use FrpTestingEnvironmentFactoryTrait;

    public EventDispatcherInterface $eventDispatcher;

    public Runtime $runtime;

    public SignalFactoryService $signalFactoryService;

    public SignalConfigFactoryService $signalConfigFactoryService;

    public SignalService $signalService;

    public function setUp(): void
    {
        $testingEnv = $this->createFrpTestEnvironment();

        $this->runtime = $testingEnv->runtime;
        $this->eventDispatcher = $testingEnv->container->get(EventDispatcherInterface::class);
        $this->signalConfigFactoryService = new SignalConfigFactoryService($testingEnv->container);
        $this->signalFactoryService = new SignalFactoryService($this->runtime);
        $this->signalService = new SignalService($this->signalFactoryService, $this->signalConfigFactoryService, $this->runtime);
    }

    public function testCanCreate(): void
    {
        $service = $this->signalService;
        $this->assertInstanceOf(SignalService::class, $service);
    }

    public function testCanUpdateRootValue(): void
    {
        $initialValue = 10;
        $newValue = 20;

        $externalClassName = ExternalSignalValueEvent::class;

        $signalConfig = $this->signalConfigFactoryService
            ->createRootSignalConfig()
            ->setExteralEventClass($externalClassName)
        ;
        $rootSignal = $this->signalService->createRootSignal($initialValue, $signalConfig);
        $this->assertEquals($initialValue, $rootSignal->getValue());

        $this->eventDispatcher->dispatch(ExternalSignalValueEvent::create($rootSignal->getId(), new \DateTimeImmutable(), $newValue, $externalClassName));
        $this->assertEquals($newValue, $rootSignal->getValue());
    }

    public function testCanUpdateDerivedValue(): void
    {
        $initialValue = 10;
        $newValue = 20;

        $externalClassName = ExternalSignalValueEvent::class;

        $func = fn (int $x) => $x + 10;
        $signalConfig = $this->signalConfigFactoryService
            ->createRootSignalConfig()
            ->setExteralEventClass($externalClassName)
        ;
        $rootSignal = $this->signalService->createRootSignal($initialValue, $signalConfig);
        $derivedSignal = $this->signalService->lift($func, $rootSignal);
        $this->assertEquals($func($initialValue), $derivedSignal->getValue());

        $this->runtime->notifyRootSignals(ExternalSignalValueEvent::create($rootSignal->getId(), new \DateTimeImmutable(), $newValue, $externalClassName));
        $this->assertEquals($func($newValue), $derivedSignal->getValue());
        $this->assertEquals(30, $derivedSignal->getValue());
    }

    public function testCanDeriveFromMultipleValues(): void
    {
        $initialIntValue = 10;
        $newIntValue = 20;

        $externalClassName = ExternalSignalValueEvent::class;

        $initialStringValue = 'Hi';
        $newStringValue = 'Bye';

        $func = fn (int $x, string $message): string => (string) ($x + 10).": {$message}";
        $signalConfig = $this->signalConfigFactoryService
            ->createRootSignalConfig()
            ->setExteralEventClass($externalClassName)
        ;
        $intRootSignal = $this->signalService->createRootSignal($initialIntValue, $signalConfig);
        $stringRootSignal = $this->signalService->createRootSignal($initialStringValue, $signalConfig);

        $derivedSignal = $this->signalService->lift2($func, $intRootSignal, $stringRootSignal);
        $this->assertEquals($func($initialIntValue, $initialStringValue), $derivedSignal->getValue());

        // update int
        $this->runtime->notifyRootSignals(ExternalSignalValueEvent::create($intRootSignal->getId(), new \DateTimeImmutable(), $newIntValue, $externalClassName));
        $this->assertEquals($func($newIntValue, $initialStringValue), $derivedSignal->getValue());
        $this->assertEquals('30: Hi', $derivedSignal->getValue());
        // update string
        $this->runtime->notifyRootSignals(ExternalSignalValueEvent::create($stringRootSignal->getId(), new \DateTimeImmutable(), $newStringValue, $externalClassName));
        $this->assertEquals($func($newIntValue, $newStringValue), $derivedSignal->getValue());
        $this->assertEquals('30: Bye', $derivedSignal->getValue());
    }

    public function testCanFoldP(): void
    {
        $initialFoldValue = 'Welcome:';
        $initialIntValue = 10;
        $event1Value = 11;
        $event2Value = 12;
        $event3Value = 13;

        $externalClassName = ExternalSignalValueEvent::class;

        $folpFunc = function (string $acc, int $new): string {
            return "{$acc} {$new}";
        };
        $signalConfig = $this->signalConfigFactoryService
            ->createRootSignalConfig()
            ->setExteralEventClass($externalClassName)
        ;
        $intRootSignal = $this->signalService->createRootSignal($initialIntValue, $signalConfig);

        $event1 = ExternalSignalValueEvent::create($intRootSignal->getId(), new \DateTimeImmutable(), $event1Value, $externalClassName);
        $event2 = ExternalSignalValueEvent::create($intRootSignal->getId(), new \DateTimeImmutable(), $event2Value, $externalClassName);
        $event3 = ExternalSignalValueEvent::create($intRootSignal->getId(), new \DateTimeImmutable(), $event3Value, $externalClassName);

        $foldedSignal = $this->signalService->foldp($folpFunc, $initialFoldValue, $intRootSignal);

        $expectedResultInitial = $folpFunc($initialFoldValue, $initialIntValue);
        $this->assertEquals($expectedResultInitial, $foldedSignal->getValue());

        $this->runtime->notifyRootSignals($event1);
        $expectedResultAfterEvent1 = $folpFunc($expectedResultInitial, $event1Value);
        $this->assertEquals($expectedResultAfterEvent1, $foldedSignal->getValue());

        $this->runtime->notifyRootSignals($event2);
        $expectedResultAfterEvent2 = $folpFunc($expectedResultAfterEvent1, $event2Value);
        $this->assertEquals($expectedResultAfterEvent2, $foldedSignal->getValue());

        $this->runtime->notifyRootSignals($event3);
        $expectedResultAfterEvent3 = $folpFunc($expectedResultAfterEvent2, $event3Value);
        $this->assertEquals($expectedResultAfterEvent3, $foldedSignal->getValue());
        // for clarity
        $this->assertEquals('Welcome: 10 11 12 13', $foldedSignal->getValue());
    }

    public function testCanFilterRootSignal(): void
    {
        $initialValue = 10;
        $ignoredValue = 20;
        $newValue = 30;

        $externalClassName = ExternalSignalValueEvent::class;

        $signalConfig = $this->signalConfigFactoryService
            ->createRootSignalConfig()
            ->setExteralEventClass($externalClassName)
            ->setFilter(fn (int $x) => $x !== $ignoredValue)
        ;
        $rootSignal = $this->signalService->createRootSignal($initialValue, $signalConfig);
        $this->assertEquals($initialValue, $rootSignal->getValue());

        $this->eventDispatcher->dispatch(ExternalSignalValueEvent::create($rootSignal->getId(), new \DateTimeImmutable(), $ignoredValue, $externalClassName));
        $this->assertEquals($initialValue, $rootSignal->getValue());
        $this->eventDispatcher->dispatch(ExternalSignalValueEvent::create($rootSignal->getId(), new \DateTimeImmutable(), $newValue, $externalClassName));
        $this->assertEquals($newValue, $rootSignal->getValue());
    }

    public function testCanFilterDerivedSignal(): void
    {
        $initialValue = 10;
        $ignoredValue = 20;
        $newValue = 30;

        $externalClassName = ExternalSignalValueEvent::class;

        $rootSignalConfig = $this->signalConfigFactoryService
            ->createRootSignalConfig()
            ->setExteralEventClass($externalClassName)
        ;
        $derivedSignalConfig = $this->signalConfigFactoryService->createDerivedSignalConfig()->setFilter(fn (int $x) => $x !== $ignoredValue);
        $rootSignal = $this->signalService->createRootSignal($initialValue, $rootSignalConfig);
        $derivedSignal = $this->signalService->lift(fn (int $x) => $x, $rootSignal, $derivedSignalConfig);
        $this->assertEquals($initialValue, $rootSignal->getValue());
        $this->assertEquals($initialValue, $derivedSignal->getValue());

        $this->eventDispatcher->dispatch(ExternalSignalValueEvent::create($rootSignal->getId(), new \DateTimeImmutable(), $ignoredValue, $externalClassName));
        $this->assertEquals($ignoredValue, $rootSignal->getValue());
        $this->assertEquals($initialValue, $derivedSignal->getValue());
        $this->eventDispatcher->dispatch(ExternalSignalValueEvent::create($rootSignal->getId(), new \DateTimeImmutable(), $newValue, $externalClassName));
        $this->assertEquals($newValue, $rootSignal->getValue());
        $this->assertEquals($newValue, $derivedSignal->getValue());
    }

    public function testTransition(): void
    {
        $initialValue = 1;
        $externalClassName = ExternalSignalValueEvent::class;

        $rootSignalConfig = $this->signalConfigFactoryService
            ->createRootSignalConfig()
            ->setExteralEventClass($externalClassName)
        ;
        $rootSignal = $this->signalService->createRootSignal($initialValue, $rootSignalConfig);

        $valueSequence = [9, 11, 10, 11, 8, 11, 10];
        $index = 0;
        $startTimestamp = new \DateTimeImmutable();
        $events = array_map(function (int $value) use ($startTimestamp, &$index, $rootSignal, $externalClassName) {
            $timestamp = $startTimestamp->add(new \DateInterval("PT{$index}S"));
            ++$index;

            return ExternalSignalValueEvent::create($rootSignal->getId(), $timestamp, $value, $externalClassName);
        }, $valueSequence);

        $from = 10;
        $to = 11;

        $fromFunc = fn (int $x): bool => $x == $from;
        $toFunc = fn (int $x): bool => $x == $to;

        /**
         * THIS IS THE TEST.
         */
        $func = $this->createClosureMock();
        $func->expects($this->once())->method('__invoke')->with($to)->willReturn(true);

        $signal = $this->signalService->transition($fromFunc, $toFunc, $func, false, $rootSignal);

        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }
        // final value is from 11 to 10 with is false
        $this->assertFalse($signal->getValue());
    }
}
