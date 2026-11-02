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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use PHPUnit\Framework\TestCase;

final class ServiceSchedulingOptionsTest extends TestCase
{
    public function testDefaultsToUseDefaultAndNulls(): void
    {
        $options = new ServiceSchedulingOptions();

        self::assertNull($options->checkTimeperiodId);
        self::assertNull($options->maxCheckAttempts);
        self::assertNull($options->normalCheckInterval);
        self::assertNull($options->retryCheckInterval);
        self::assertSame(TriStateEnum::UseDefault, $options->activeCheckEnabled);
        self::assertSame(TriStateEnum::UseDefault, $options->passiveCheckEnabled);
        self::assertSame(TriStateEnum::UseDefault, $options->isVolatile);
    }

    public function testAcceptsValidValues(): void
    {
        $options = new ServiceSchedulingOptions(
            checkTimeperiodId: new TimePeriodId(3),
            maxCheckAttempts: 5,
            normalCheckInterval: 10,
            retryCheckInterval: 2,
            activeCheckEnabled: TriStateEnum::True,
            passiveCheckEnabled: TriStateEnum::False,
            isVolatile: TriStateEnum::True,
        );

        self::assertSame(3, $options->checkTimeperiodId?->value);
        self::assertSame(5, $options->maxCheckAttempts);
        self::assertSame(10, $options->normalCheckInterval);
        self::assertSame(2, $options->retryCheckInterval);
        self::assertSame(TriStateEnum::True, $options->activeCheckEnabled);
        self::assertSame(TriStateEnum::False, $options->passiveCheckEnabled);
        self::assertSame(TriStateEnum::True, $options->isVolatile);
    }

    public function testRejectsAMaxCheckAttemptsBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceSchedulingOptions(maxCheckAttempts: 0);
    }

    public function testRejectsANormalCheckIntervalBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceSchedulingOptions(normalCheckInterval: 0);
    }

    public function testRejectsARetryCheckIntervalBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceSchedulingOptions(retryCheckInterval: 0);
    }

    public function testAcceptsTheLowerBoundaryOfOne(): void
    {
        $options = new ServiceSchedulingOptions(maxCheckAttempts: 1, normalCheckInterval: 1, retryCheckInterval: 1);

        self::assertSame(1, $options->maxCheckAttempts);
        self::assertSame(1, $options->normalCheckInterval);
        self::assertSame(1, $options->retryCheckInterval);
    }
}
