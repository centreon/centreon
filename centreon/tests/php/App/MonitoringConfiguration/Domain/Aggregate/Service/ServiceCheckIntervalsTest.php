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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceCheckIntervalsTest extends TestCase
{
    public function testBothAreUnsetByDefault(): void
    {
        $intervals = new ServiceCheckIntervals();

        self::assertNull($intervals->normal);
        self::assertNull($intervals->retry);
    }

    public function testItHoldsItsIntervals(): void
    {
        $intervals = new ServiceCheckIntervals(normal: 1, retry: 30);

        self::assertSame(1, $intervals->normal);
        self::assertSame(30, $intervals->retry);
    }

    /**
     * @return iterable<string, array{?int, ?int}>
     */
    public static function provideIntervalsBelowOne(): iterable
    {
        yield 'zero normal' => [0, null];

        yield 'negative normal' => [-5, null];

        yield 'zero retry' => [null, 0];

        yield 'negative retry' => [null, -1];
    }

    #[DataProvider('provideIntervalsBelowOne')]
    public function testAnIntervalBelowOneIsRejected(?int $normal, ?int $retry): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceCheckIntervals($normal, $retry);
    }
}
