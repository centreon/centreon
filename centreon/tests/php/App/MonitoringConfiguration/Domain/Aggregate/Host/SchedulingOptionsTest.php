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

use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use PHPUnit\Framework\TestCase;

final class SchedulingOptionsTest extends TestCase
{
    public function testDefaultsToUseDefaultAndNulls(): void
    {
        $schedulingOptions = new SchedulingOptions();

        self::assertNull($schedulingOptions->checkTimeperiodId);
        self::assertNull($schedulingOptions->maxCheckAttempts);
        self::assertNull($schedulingOptions->normalCheckInterval);
        self::assertNull($schedulingOptions->retryCheckInterval);
        self::assertSame(TriStateEnum::UseDefault, $schedulingOptions->activeCheckEnabled);
        self::assertSame(TriStateEnum::UseDefault, $schedulingOptions->passiveCheckEnabled);
    }

    public function testAcceptsValidValues(): void
    {
        $timePeriodId = new TimePeriodId(1);

        $schedulingOptions = new SchedulingOptions(
            checkTimeperiodId: $timePeriodId,
            maxCheckAttempts: 3,
            normalCheckInterval: 5,
            retryCheckInterval: 1,
            activeCheckEnabled: TriStateEnum::True,
            passiveCheckEnabled: TriStateEnum::False,
        );

        self::assertSame($timePeriodId, $schedulingOptions->checkTimeperiodId);
        self::assertSame(3, $schedulingOptions->maxCheckAttempts);
        self::assertSame(5, $schedulingOptions->normalCheckInterval);
        self::assertSame(1, $schedulingOptions->retryCheckInterval);
        self::assertSame(TriStateEnum::True, $schedulingOptions->activeCheckEnabled);
        self::assertSame(TriStateEnum::False, $schedulingOptions->passiveCheckEnabled);
    }

    public function testRejectsAMaxCheckAttemptsBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SchedulingOptions(maxCheckAttempts: 0);
    }

    public function testRejectsANormalCheckIntervalBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SchedulingOptions(normalCheckInterval: 0);
    }

    public function testRejectsARetryCheckIntervalBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SchedulingOptions(retryCheckInterval: 0);
    }

    public function testAcceptsTheLowerBoundaryOfOne(): void
    {
        $schedulingOptions = new SchedulingOptions(
            maxCheckAttempts: 1,
            normalCheckInterval: 1,
            retryCheckInterval: 1,
        );

        self::assertSame(1, $schedulingOptions->maxCheckAttempts);
        self::assertSame(1, $schedulingOptions->normalCheckInterval);
        self::assertSame(1, $schedulingOptions->retryCheckInterval);
    }
}
