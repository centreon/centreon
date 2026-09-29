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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\HostSeverity;

use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use PHPUnit\Framework\TestCase;

final class HostSeverityNameTest extends TestCase
{
    public function testItExposesTheGivenValue(): void
    {
        $name = new HostSeverityName('Critical');

        self::assertSame('Critical', $name->value);
    }

    public function testItAcceptsTheMinimumLength(): void
    {
        $name = new HostSeverityName(str_repeat('a', HostSeverityName::MIN_LENGTH));

        self::assertSame(HostSeverityName::MIN_LENGTH, mb_strlen($name->value));
    }

    public function testItAcceptsTheMaximumLength(): void
    {
        $name = new HostSeverityName(str_repeat('a', HostSeverityName::MAX_LENGTH));

        self::assertSame(HostSeverityName::MAX_LENGTH, mb_strlen($name->value));
    }

    public function testItRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostSeverityName('');
    }

    public function testItRejectsANameLongerThanTheMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostSeverityName(str_repeat('a', HostSeverityName::MAX_LENGTH + 1));
    }
}
