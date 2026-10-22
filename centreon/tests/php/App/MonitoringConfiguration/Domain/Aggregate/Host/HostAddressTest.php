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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use PHPUnit\Framework\TestCase;

final class HostAddressTest extends TestCase
{
    public function testItTrimsSurroundingWhitespace(): void
    {
        $address = new HostAddress('  10.0.0.1  ');

        self::assertSame('10.0.0.1', $address->value);
    }

    public function testItRejectsAWhitespaceOnlyAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostAddress('   ');
    }

    public function testItRejectsAnEmptyAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostAddress('');
    }

    public function testItRejectsAnAddressLongerThan255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostAddress(str_repeat('a', 256));
    }

    public function testItAcceptsAValidHostname(): void
    {
        $address = new HostAddress('my-host.example.com');

        self::assertSame('my-host.example.com', $address->value);
    }

    public function testItRejectsAnAddressThatIsNeitherAnIpNorAHostname(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostAddress('not a valid host!!');
    }
}
