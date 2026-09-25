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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use PHPUnit\Framework\TestCase;

final class CheckOptionsTest extends TestCase
{
    public function testItHoldsACheckCommandWithItsArguments(): void
    {
        $options = new CheckOptions(new CommandId(42), ['-H', '127.0.0.1']);

        self::assertSame(42, $options->checkCommandId?->value);
        self::assertSame(['-H', '127.0.0.1'], $options->args);
    }

    public function testItAcceptsNoCheckCommandAndNoArguments(): void
    {
        $options = new CheckOptions(null);

        self::assertNull($options->checkCommandId);
        self::assertSame([], $options->args);
    }

    public function testItRejectsArgumentsWithoutACheckCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CheckOptions(null, ['-H', '127.0.0.1']);
    }

    public function testItReindexesArgumentsAsAList(): void
    {
        $options = new CheckOptions(new CommandId(1), [2 => 'b', 0 => 'a']);

        self::assertSame(['b', 'a'], $options->args);
    }

    public function testItRejectsAnArgumentContainingTheStorageDelimiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CheckOptions(new CommandId(1), ['a!b']);
    }

    public function testItRejectsAnArgumentContainingAnEscapeToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CheckOptions(new CommandId(1), ['a#BR#b']);
    }

    public function testItAcceptsAnArgumentContainingAControlCharacter(): void
    {
        // Newlines, tabs and carriage returns are allowed: the storage formatter encodes them as
        // #BR#/#T#/#R#, matching legacy, so they round-trip through the single legacy column.
        $options = new CheckOptions(new CommandId(1), ["a\nb"]);

        self::assertSame(["a\nb"], $options->args);
    }
}
