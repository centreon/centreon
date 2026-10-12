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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\Shared\Domain\Aggregate\TriStateEnum;
use PHPUnit\Framework\TestCase;

final class DataProcessingTest extends TestCase
{
    public function testDefaultsToUseDefaultAndNulls(): void
    {
        $dataProcessing = new DataProcessing();

        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->checkFreshness);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->flapDetectionEnabled);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->eventHandlerEnabled);
        self::assertNull($dataProcessing->acknowledgmentTimeout);
        self::assertNull($dataProcessing->freshnessThreshold);
        self::assertNull($dataProcessing->lowFlapThreshold);
        self::assertNull($dataProcessing->highFlapThreshold);
        self::assertNull($dataProcessing->eventHandlerCommandId);
        self::assertSame([], $dataProcessing->eventHandlerArgs);
    }

    public function testAcceptsValidValues(): void
    {
        $dataProcessing = new DataProcessing(
            checkFreshness: TriStateEnum::True,
            acknowledgmentTimeout: 1,
            freshnessThreshold: 0,
            lowFlapThreshold: 0,
            highFlapThreshold: 100,
            eventHandlerCommandId: new CommandId(42),
            eventHandlerArgs: ['warn', 'crit'],
        );

        self::assertSame(1, $dataProcessing->acknowledgmentTimeout);
        self::assertSame(0, $dataProcessing->freshnessThreshold);
        self::assertSame(100, $dataProcessing->highFlapThreshold);
        self::assertSame(42, $dataProcessing->eventHandlerCommandId?->value);
        self::assertSame(['warn', 'crit'], $dataProcessing->eventHandlerArgs);
    }

    public function testRejectsAcknowledgmentTimeoutBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DataProcessing(acknowledgmentTimeout: 0);
    }

    public function testRejectsNegativeFreshnessThreshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DataProcessing(freshnessThreshold: -1);
    }

    public function testRejectsLowFlapThresholdAboveOneHundred(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DataProcessing(lowFlapThreshold: 101);
    }

    public function testRejectsHighFlapThresholdBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DataProcessing(highFlapThreshold: -1);
    }

    public function testAcceptsFlapThresholdBoundaries(): void
    {
        $dataProcessing = new DataProcessing(lowFlapThreshold: 0, highFlapThreshold: 100);

        self::assertSame(0, $dataProcessing->lowFlapThreshold);
        self::assertSame(100, $dataProcessing->highFlapThreshold);
    }

    public function testRejectsAnEventHandlerArgContainingTheStorageDelimiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DataProcessing(eventHandlerArgs: ['a!b']);
    }

    public function testAcceptsAnEventHandlerArgContainingAControlCharacter(): void
    {
        // Newlines, tabs and carriage returns are allowed: the storage formatter encodes them as
        // #BR#/#T#/#R#, matching legacy, so they round-trip through the single legacy column.
        $dataProcessing = new DataProcessing(eventHandlerArgs: ["a\nb"]);

        self::assertSame(["a\nb"], $dataProcessing->eventHandlerArgs);
    }

    public function testRejectsAnEventHandlerArgContainingAnEscapeToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DataProcessing(eventHandlerArgs: ['a#BR#b']);
    }

    public function testWithKeepsEveryValueWhenNothingIsProvided(): void
    {
        $original = new DataProcessing(TriStateEnum::True, TriStateEnum::False, TriStateEnum::True, 15, 120, 20, 40, new CommandId(3), ['restart']);

        self::assertTrue($original->equals($original->with()));
    }

    public function testWithReplacesOnlyTheProvidedValues(): void
    {
        $original = new DataProcessing(TriStateEnum::True, freshnessThreshold: 120, lowFlapThreshold: 20, eventHandlerArgs: ['restart']);

        $changed = $original->with(freshnessThreshold: 300, eventHandlerArgs: []);

        self::assertSame(300, $changed->freshnessThreshold);
        self::assertSame([], $changed->eventHandlerArgs);
        self::assertSame(TriStateEnum::True, $changed->checkFreshness);
        self::assertSame(20, $changed->lowFlapThreshold);
    }

    public function testWithClearsAValueSetToNull(): void
    {
        $original = new DataProcessing(freshnessThreshold: 120, eventHandlerCommandId: new CommandId(3));

        $changed = $original->with(freshnessThreshold: null, eventHandlerCommandId: null);

        self::assertNull($changed->freshnessThreshold);
        self::assertNull($changed->eventHandlerCommandId);
    }

    public function testWithStillEnforcesTheInvariants(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new DataProcessing())->with(lowFlapThreshold: 101);
    }

    public function testACentreonMonitoringAgentHostAlwaysChecksFreshnessWithAFixedThreshold(): void
    {
        $original = new DataProcessing(checkFreshness: TriStateEnum::False, freshnessThreshold: 30, lowFlapThreshold: 10);

        $changed = $original->withCentreonMonitoringAgentFreshness();

        self::assertSame(TriStateEnum::True, $changed->checkFreshness);
        self::assertSame(120, $changed->freshnessThreshold);
        self::assertSame(10, $changed->lowFlapThreshold);
    }

    public function testEqualsDistinguishesNullFromZero(): void
    {
        self::assertFalse((new DataProcessing(freshnessThreshold: 0))->equals(new DataProcessing()));
        self::assertTrue((new DataProcessing(eventHandlerArgs: ['a']))->equals(new DataProcessing(eventHandlerArgs: ['a'])));
        self::assertFalse((new DataProcessing(eventHandlerArgs: ['a']))->equals(new DataProcessing(eventHandlerArgs: ['b'])));
    }
}
