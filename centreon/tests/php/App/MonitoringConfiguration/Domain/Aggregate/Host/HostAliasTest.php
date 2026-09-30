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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use PHPUnit\Framework\TestCase;

final class HostAliasTest extends TestCase
{
    public function testItTrimsSurroundingWhitespace(): void
    {
        $alias = new HostAlias('  my-alias  ');

        self::assertSame('my-alias', $alias->value);
    }

    public function testItRejectsAWhitespaceOnlyAlias(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostAlias('   ');
    }

    public function testItRejectsAnEmptyAlias(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostAlias('');
    }

    public function testItRejectsAnAliasLongerThan200Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostAlias(str_repeat('a', 201));
    }
}
