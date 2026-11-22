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

namespace Tests\App\AiConfiguration\Domain\Usage;

use App\AiConfiguration\Domain\Usage\UsageWindow;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class UsageWindowTest extends TestCase
{
    public function testTheDayStartsAtMidnightUtc(): void
    {
        $clock = new MockClock('2026-03-15 18:45:12', 'UTC');

        self::assertSame($this->utc('2026-03-15 00:00:00'), UsageWindow::dayStart($clock->now()));
    }

    public function testTheMonthStartsOnTheFirstDayAtMidnightUtc(): void
    {
        $clock = new MockClock('2026-03-15 18:45:12', 'UTC');

        self::assertSame($this->utc('2026-03-01 00:00:00'), UsageWindow::monthStart($clock->now()));
    }

    public function testTheWindowsFollowUtcWhateverTheClockTimezone(): void
    {
        // 00:30 on March 1st in Paris is 23:30 on February 28th in UTC
        $clock = new MockClock('2026-03-01 00:30:00', 'Europe/Paris');

        self::assertSame($this->utc('2026-02-28 00:00:00'), UsageWindow::dayStart($clock->now()));
        self::assertSame($this->utc('2026-02-01 00:00:00'), UsageWindow::monthStart($clock->now()));
    }

    public function testTheWindowsFollowUtcAheadOfTheClockTimezone(): void
    {
        // 20:00 on January 31st in New York is already February 1st in UTC
        $clock = new MockClock('2026-01-31 20:00:00', 'America/New_York');

        self::assertSame($this->utc('2026-02-01 00:00:00'), UsageWindow::dayStart($clock->now()));
        self::assertSame($this->utc('2026-02-01 00:00:00'), UsageWindow::monthStart($clock->now()));
    }

    private function utc(string $dateTime): int
    {
        return (new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC')))->getTimestamp();
    }
}
