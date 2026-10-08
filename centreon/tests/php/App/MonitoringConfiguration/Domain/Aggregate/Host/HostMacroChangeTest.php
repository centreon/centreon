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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use PHPUnit\Framework\TestCase;

final class HostMacroChangeTest extends TestCase
{
    public function testANewMacroWithAValueIsValid(): void
    {
        $change = new HostMacroChange(new HostMacroName('foo'), 'bar', isPassword: false);

        self::assertTrue($change->isNew());
        self::assertFalse($change->keepsStoredValue());
    }

    public function testAnEmptyStringIsAValue(): void
    {
        $change = new HostMacroChange(new HostMacroName('foo'), '', isPassword: false);

        self::assertFalse($change->keepsStoredValue());
    }

    public function testAnExistingPasswordMayKeepItsStoredValue(): void
    {
        $change = new HostMacroChange(new HostMacroName('pwd'), null, isPassword: true, id: new HostMacroId(1));

        self::assertFalse($change->isNew());
        self::assertTrue($change->keepsStoredValue());
    }

    public function testANewMacroNeedsAValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostMacroChange(new HostMacroName('pwd'), null, isPassword: true);
    }

    public function testANonPasswordMacroNeedsAValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostMacroChange(new HostMacroName('foo'), null, isPassword: false, id: new HostMacroId(1));
    }

    public function testItRejectsAValueLongerThan4096(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostMacroChange(new HostMacroName('big'), str_repeat('a', 4097), isPassword: false);
    }
}
