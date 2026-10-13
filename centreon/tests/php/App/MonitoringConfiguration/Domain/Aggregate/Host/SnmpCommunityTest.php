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

use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use PHPUnit\Framework\TestCase;

final class SnmpCommunityTest extends TestCase
{
    public function testItTrimsSurroundingWhitespace(): void
    {
        $community = new SnmpCommunity('  public  ');

        self::assertSame('public', $community->value);
    }

    public function testItRejectsAWhitespaceOnlyCommunity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SnmpCommunity('   ');
    }

    public function testItRejectsAnEmptyCommunity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SnmpCommunity('');
    }

    public function testItRejectsACommunityLongerThan255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SnmpCommunity(str_repeat('a', 256));
    }

    public function testItAcceptsAVaultReferenceUnchanged(): void
    {
        $path = 'secret::hashicorp_vault::monitoring/hosts/3f2a::_HOSTSNMPCOMMUNITY';

        self::assertSame($path, new SnmpCommunity($path)->value);
    }
}
