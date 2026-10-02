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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\TimePeriod;

use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use PHPUnit\Framework\TestCase;

final class TimePeriodNameTest extends TestCase
{
    public function testItTrimsSurroundingWhitespace(): void
    {
        $name = new TimePeriodName('  My Time Period  ');

        self::assertSame('My Time Period', $name->value);
    }

    public function testItRejectsAWhitespaceOnlyName(): void
    {
        // legacy trims first, so a blank name fails the minimum length rather than passing
        $this->expectException(\InvalidArgumentException::class);

        new TimePeriodName('   ');
    }

    public function testItRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TimePeriodName('');
    }

    public function testItRejectsANameLongerThanTheColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TimePeriodName(str_repeat('a', TimePeriodName::MAX_LENGTH + 1));
    }

    public function testItAcceptsANameOfExactlyTheMaximumLength(): void
    {
        $value = str_repeat('a', TimePeriodName::MAX_LENGTH);

        self::assertSame($value, (new TimePeriodName($value))->value);
    }
}
