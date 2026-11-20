<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\Shared\Domain\Aggregate\TriStateEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceDataProcessingTest extends TestCase
{
    public function testDefaultsToUseDefaultAndNulls(): void
    {
        $dataProcessing = new ServiceDataProcessing();

        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->checkFreshness);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->flapDetectionEnabled);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->eventHandlerEnabled);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->obsessOverService);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->retainStatusInformation);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->retainNonStatusInformation);
        self::assertNull($dataProcessing->acknowledgmentTimeout);
        self::assertNull($dataProcessing->freshnessThreshold);
        self::assertNull($dataProcessing->lowFlapThreshold);
        self::assertNull($dataProcessing->highFlapThreshold);
        self::assertNull($dataProcessing->eventHandlerCommandId);
        self::assertSame([], $dataProcessing->eventHandlerArgs);
        self::assertSame([], $dataProcessing->stalkingOptions);
    }

    public function testAcceptsValidValues(): void
    {
        $dataProcessing = new ServiceDataProcessing(
            checkFreshness: TriStateEnum::True,
            flapDetectionEnabled: TriStateEnum::False,
            eventHandlerEnabled: TriStateEnum::True,
            obsessOverService: TriStateEnum::False,
            retainStatusInformation: TriStateEnum::True,
            retainNonStatusInformation: TriStateEnum::False,
            acknowledgmentTimeout: 1,
            freshnessThreshold: 0,
            lowFlapThreshold: 0,
            highFlapThreshold: 100,
            eventHandlerCommandId: new CommandId(42),
            eventHandlerArgs: ['warn', 'crit'],
            stalkingOptions: [ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Critical],
        );

        self::assertSame(TriStateEnum::False, $dataProcessing->obsessOverService);
        self::assertSame(TriStateEnum::True, $dataProcessing->retainStatusInformation);
        self::assertSame(TriStateEnum::False, $dataProcessing->retainNonStatusInformation);
        self::assertSame(1, $dataProcessing->acknowledgmentTimeout);
        self::assertSame(0, $dataProcessing->freshnessThreshold);
        self::assertSame(0, $dataProcessing->lowFlapThreshold);
        self::assertSame(100, $dataProcessing->highFlapThreshold);
        self::assertSame(42, $dataProcessing->eventHandlerCommandId?->value);
        self::assertSame(['warn', 'crit'], $dataProcessing->eventHandlerArgs);
        self::assertSame([ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Critical], $dataProcessing->stalkingOptions);
    }

    public function testRejectsAcknowledgmentTimeoutBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(acknowledgmentTimeout: 0);
    }

    public function testRejectsNegativeFreshnessThreshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(freshnessThreshold: -1);
    }

    public function testRejectsLowFlapThresholdAboveOneHundred(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(lowFlapThreshold: 101);
    }

    public function testRejectsLowFlapThresholdBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(lowFlapThreshold: -1);
    }

    public function testRejectsHighFlapThresholdAboveOneHundred(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(highFlapThreshold: 101);
    }

    public function testRejectsHighFlapThresholdBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(highFlapThreshold: -1);
    }

    public function testRejectsAnEventHandlerArgContainingTheStorageDelimiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(eventHandlerArgs: ['a!b']);
    }

    public function testRejectsAnEventHandlerArgContainingAnEscapeToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(eventHandlerArgs: ['a#BR#b']);
    }

    /**
     * Normalised to the only order legacy writes.
     */
    public function testItCollapsesDuplicatedStalkingOptionsAndNormalisesTheirOrder(): void
    {
        $dataProcessing = new ServiceDataProcessing(stalkingOptions: [
            ServiceStalkingOptionEnum::Critical,
            ServiceStalkingOptionEnum::Ok,
            ServiceStalkingOptionEnum::Unknown,
            ServiceStalkingOptionEnum::Critical,
            ServiceStalkingOptionEnum::Warning,
        ]);

        self::assertSame(
            [
                ServiceStalkingOptionEnum::Ok,
                ServiceStalkingOptionEnum::Warning,
                ServiceStalkingOptionEnum::Unknown,
                ServiceStalkingOptionEnum::Critical,
            ],
            $dataProcessing->stalkingOptions,
        );
    }

    /**
     * Freshness forced on with a 500-second threshold; every other field is kept.
     */
    public function testACentreonMonitoringAgentServiceAlwaysChecksFreshnessWithAFixedThreshold(): void
    {
        $fields = [
            'flapDetectionEnabled' => TriStateEnum::False,
            'eventHandlerEnabled' => TriStateEnum::True,
            'obsessOverService' => TriStateEnum::False,
            'retainStatusInformation' => TriStateEnum::True,
            'retainNonStatusInformation' => TriStateEnum::False,
            'acknowledgmentTimeout' => 5,
            'lowFlapThreshold' => 10,
            'highFlapThreshold' => 90,
            'eventHandlerCommandId' => new CommandId(42),
            'eventHandlerArgs' => ['warn'],
            'stalkingOptions' => [ServiceStalkingOptionEnum::Ok],
        ];
        $original = new ServiceDataProcessing(...[...$fields, 'checkFreshness' => TriStateEnum::False, 'freshnessThreshold' => 30]);

        $changed = $original->withCentreonMonitoringAgentFreshness();

        self::assertEquals(
            new ServiceDataProcessing(...[...$fields, 'checkFreshness' => TriStateEnum::True, 'freshnessThreshold' => 500]),
            $changed,
        );
    }

    /**
     * All-empty arguments are no argument, as legacy stores them; a mixed list is kept, with no all-or-none rule.
     *
     * @param list<string> $args
     * @param list<string> $expected
     */
    #[DataProvider('eventHandlerArgumentListProvider')]
    public function testAnAllEmptyEventHandlerArgumentListIsNoArgument(array $args, array $expected): void
    {
        self::assertSame($expected, (new ServiceDataProcessing(eventHandlerArgs: $args))->eventHandlerArgs);
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function eventHandlerArgumentListProvider(): iterable
    {
        yield 'no argument' => [[], []];

        yield 'one empty argument' => [[''], []];

        yield 'two empty arguments' => [['', ''], []];

        yield 'an empty and a filled argument' => [['', 'warn'], ['', 'warn']];
    }

    public function testEqualsHoldsForTheSameValues(): void
    {
        self::assertTrue(self::dataProcessing()->equals(self::dataProcessing()));
    }

    public function testEqualsIgnoresTheOrderTheStalkingOptionsWereGivenIn(): void
    {
        self::assertTrue(self::dataProcessing()->equals(
            self::dataProcessing(stalkingOptions: [ServiceStalkingOptionEnum::Critical, ServiceStalkingOptionEnum::Ok]),
        ));
    }

    #[DataProvider('fieldChangeProvider')]
    public function testEqualsDetectsAChangeOfEachField(ServiceDataProcessing $changed): void
    {
        self::assertFalse(self::dataProcessing()->equals($changed));
    }

    /**
     * @return iterable<string, array{ServiceDataProcessing}>
     */
    public static function fieldChangeProvider(): iterable
    {
        yield 'check freshness' => [self::dataProcessing(checkFreshness: TriStateEnum::UseDefault)];

        yield 'flap detection' => [self::dataProcessing(flapDetectionEnabled: TriStateEnum::UseDefault)];

        yield 'event handler enabled' => [self::dataProcessing(eventHandlerEnabled: TriStateEnum::UseDefault)];

        yield 'obsess over service' => [self::dataProcessing(obsessOverService: TriStateEnum::UseDefault)];

        yield 'retain status information' => [self::dataProcessing(retainStatusInformation: TriStateEnum::UseDefault)];

        yield 'retain non-status information' => [self::dataProcessing(retainNonStatusInformation: TriStateEnum::UseDefault)];

        yield 'acknowledgement timeout' => [self::dataProcessing(acknowledgmentTimeout: 16)];

        yield 'freshness threshold' => [self::dataProcessing(freshnessThreshold: 0)];

        yield 'low flap threshold' => [self::dataProcessing(lowFlapThreshold: 11)];

        yield 'high flap threshold' => [self::dataProcessing(highFlapThreshold: 91)];

        yield 'event handler' => [self::dataProcessing(eventHandlerCommandId: new CommandId(8))];

        yield 'no event handler' => [self::dataProcessing(eventHandlerCommandId: null)];

        yield 'event handler arguments' => [self::dataProcessing(eventHandlerArgs: ['other'])];

        yield 'stalking options' => [self::dataProcessing(stalkingOptions: [ServiceStalkingOptionEnum::Ok])];
    }

    /**
     * Every field set by default, none to the value object's own default.
     *
     * @param list<string> $eventHandlerArgs
     * @param list<ServiceStalkingOptionEnum> $stalkingOptions
     */
    private static function dataProcessing(
        TriStateEnum $checkFreshness = TriStateEnum::True,
        TriStateEnum $flapDetectionEnabled = TriStateEnum::False,
        TriStateEnum $eventHandlerEnabled = TriStateEnum::True,
        TriStateEnum $obsessOverService = TriStateEnum::False,
        TriStateEnum $retainStatusInformation = TriStateEnum::True,
        TriStateEnum $retainNonStatusInformation = TriStateEnum::False,
        int $acknowledgmentTimeout = 15,
        int $freshnessThreshold = 60,
        int $lowFlapThreshold = 10,
        int $highFlapThreshold = 90,
        ?CommandId $eventHandlerCommandId = new CommandId(7),
        array $eventHandlerArgs = ['arg'],
        array $stalkingOptions = [ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Critical],
    ): ServiceDataProcessing {
        return new ServiceDataProcessing(
            $checkFreshness,
            $flapDetectionEnabled,
            $eventHandlerEnabled,
            $obsessOverService,
            $retainStatusInformation,
            $retainNonStatusInformation,
            $acknowledgmentTimeout,
            $freshnessThreshold,
            $lowFlapThreshold,
            $highFlapThreshold,
            $eventHandlerCommandId,
            $eventHandlerArgs,
            $stalkingOptions,
        );
    }
}
