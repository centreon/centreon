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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Service;

use App\MonitoringConfiguration\Infrastructure\Service\CommandArgumentsFormatter;
use PHPUnit\Framework\TestCase;

final class CommandArgumentsFormatterTest extends TestCase
{
    public function testItReturnsNullForNoArguments(): void
    {
        self::assertNull(CommandArgumentsFormatter::format([]));
    }

    public function testItBangJoinsArguments(): void
    {
        self::assertSame('!-H!127.0.0.1', CommandArgumentsFormatter::format(['-H', '127.0.0.1']));
    }

    public function testItEncodesControlCharactersTheLegacyWay(): void
    {
        // Newlines, tabs and carriage returns are stored as #BR#/#T#/#R# (CentreonHost::insert).
        self::assertSame(
            '!line1#BR#line2#T#col#R#ret',
            CommandArgumentsFormatter::format(["line1\nline2\tcol\rret"]),
        );
    }
}
