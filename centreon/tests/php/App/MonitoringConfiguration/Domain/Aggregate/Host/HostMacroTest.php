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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use PHPUnit\Framework\TestCase;

final class HostMacroTest extends TestCase
{
    public function testItHoldsItsFields(): void
    {
        $macro = new HostMacro(new HostMacroName('community'), 'public', isPassword: false, description: 'SNMP');

        self::assertSame('COMMUNITY', $macro->name->value);
        self::assertSame('public', $macro->value);
        self::assertFalse($macro->isPassword);
        self::assertSame('SNMP', $macro->description);
    }

    public function testItRejectsAValueLongerThan4096(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostMacro(new HostMacroName('big'), str_repeat('a', 4097), isPassword: false);
    }

    public function testItAllowsANullDescription(): void
    {
        $macro = new HostMacro(new HostMacroName('secret'), 's3cr3t', isPassword: true);

        self::assertNull($macro->description);
        self::assertTrue($macro->isPassword);
    }

    public function testItAcceptsADescriptionAtTheByteLimit(): void
    {
        $macro = new HostMacro(
            new HostMacroName('big'),
            'v',
            isPassword: false,
            description: str_repeat('a', HostMacro::MAX_DESCRIPTION_LENGTH),
        );

        self::assertSame(HostMacro::MAX_DESCRIPTION_LENGTH, \strlen((string) $macro->description));
    }

    public function testItRejectsADescriptionExceedingTheByteLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // A single multibyte character is 2 bytes: MAX_DESCRIPTION_LENGTH such characters are within the
        // character limit but twice the byte budget of the TEXT column.
        new HostMacro(
            new HostMacroName('big'),
            'v',
            isPassword: false,
            description: str_repeat('é', HostMacro::MAX_DESCRIPTION_LENGTH),
        );
    }
}
