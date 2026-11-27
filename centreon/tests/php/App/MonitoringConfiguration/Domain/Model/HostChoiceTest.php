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

namespace Tests\App\MonitoringConfiguration\Domain\Model;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Model\HostChoice;
use PHPUnit\Framework\TestCase;

final class HostChoiceTest extends TestCase
{
    public function testItRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostChoice(new HostId(1), '');
    }

    public function testItAcceptsTheLiteralZero(): void
    {
        // stringNotEmpty(), not notEmpty(): notEmpty() relies on empty(), which treats "0" as empty.
        self::assertSame('0', (new HostChoice(new HostId(1), '0'))->name);
    }

    public function testItKeepsTheRawNameUntrimmed(): void
    {
        self::assertSame(' a host ', (new HostChoice(new HostId(1), ' a host '))->name);
    }

    public function testItAcceptsANameLongerThanTheColumnSize(): void
    {
        // No length bound: a projection that is never written back must not fail hydration on a long name.
        $name = str_repeat('a', 300);

        self::assertSame($name, (new HostChoice(new HostId(1), $name))->name);
    }
}
