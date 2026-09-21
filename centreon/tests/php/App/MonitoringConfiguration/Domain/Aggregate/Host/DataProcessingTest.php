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
use App\Shared\Domain\TriStateEnum;
use PHPUnit\Framework\TestCase;

final class DataProcessingTest extends TestCase
{
    public function testDefaultsToUseDefaultAndNulls(): void
    {
        $dp = new DataProcessing();

        self::assertSame(TriStateEnum::UseDefault, $dp->checkFreshness);
        self::assertSame(TriStateEnum::UseDefault, $dp->flapDetectionEnabled);
        self::assertSame(TriStateEnum::UseDefault, $dp->eventHandlerEnabled);
        self::assertNull($dp->acknowledgmentTimeout);
        self::assertNull($dp->freshnessThreshold);
        self::assertNull($dp->lowFlapThreshold);
        self::assertNull($dp->highFlapThreshold);
        self::assertNull($dp->eventHandlerCommandId);
        self::assertSame([], $dp->eventHandlerArgs);
    }

    public function testAcceptsValidValues(): void
    {
        $dp = new DataProcessing(
            checkFreshness: TriStateEnum::True,
            acknowledgmentTimeout: 1,
            freshnessThreshold: 0,
            lowFlapThreshold: 0,
            highFlapThreshold: 100,
            eventHandlerCommandId: new CommandId(42),
            eventHandlerArgs: ['warn', 'crit'],
        );

        self::assertSame(1, $dp->acknowledgmentTimeout);
        self::assertSame(0, $dp->freshnessThreshold);
        self::assertSame(100, $dp->highFlapThreshold);
        self::assertSame(42, $dp->eventHandlerCommandId?->value);
        self::assertSame(['warn', 'crit'], $dp->eventHandlerArgs);
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
        $dp = new DataProcessing(lowFlapThreshold: 0, highFlapThreshold: 100);

        self::assertSame(0, $dp->lowFlapThreshold);
        self::assertSame(100, $dp->highFlapThreshold);
    }
}
