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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
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

    public function testItHoldsCustomMacrosIndependentlyOfTheCommand(): void
    {
        $macro = new HostMacro(new HostMacroName('community'), 'public', isPassword: false);

        // Macros do not require a check command, unlike arguments.
        $options = new CheckOptions(null, macros: [5 => $macro]);

        self::assertSame([$macro], $options->macros);
        self::assertSame([], $options->args);
    }

    public function testItKeepsOnlyTheFirstOfTwoMacrosWithTheSameName(): void
    {
        $first = new HostMacro(new HostMacroName('dup'), 'first', isPassword: false);
        $second = new HostMacro(new HostMacroName('dup'), 'second', isPassword: false);

        $options = new CheckOptions(null, macros: [$first, $second]);

        self::assertSame([$first], $options->macros);
    }

    public function testWithKeepsEveryValueWhenNothingIsProvided(): void
    {
        $original = new CheckOptions(new CommandId(1), ['-w'], [new HostMacro(new HostMacroName('TOKEN'), 'v', false)]);

        self::assertTrue($original->equals($original->with()));
    }

    public function testWithReplacesTheArgumentsAndKeepsTheCommand(): void
    {
        $changed = (new CheckOptions(new CommandId(1), ['-w']))->with(args: ['-c', '90']);

        self::assertSame(1, $changed->checkCommandId?->value);
        self::assertSame(['-c', '90'], $changed->args);
    }

    public function testWithKeepsTheArgumentsWhenTheCommandChanges(): void
    {
        $changed = (new CheckOptions(new CommandId(1), ['-w']))->with(checkCommandId: new CommandId(2));

        self::assertSame(2, $changed->checkCommandId?->value);
        self::assertSame(['-w'], $changed->args);
    }

    public function testRemovingTheCommandAlsoClearsTheArguments(): void
    {
        $changed = (new CheckOptions(new CommandId(1), ['-w']))->with(checkCommandId: null);

        self::assertNull($changed->checkCommandId);
        self::assertSame([], $changed->args);
    }

    public function testRemovingTheCommandAndSendingArgumentsIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CheckOptions(new CommandId(1)))->with(checkCommandId: null, args: ['-w']);
    }

    public function testArgumentsWithoutAnyCommandAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CheckOptions(null))->with(args: ['-w']);
    }

    public function testEqualsComparesTheMacros(): void
    {
        $options = new CheckOptions(null, [], [new HostMacro(new HostMacroName('TOKEN'), 'v', true, 'd')]);

        self::assertTrue($options->equals(new CheckOptions(null, [], [new HostMacro(new HostMacroName('TOKEN'), 'v', true, 'd')])));
        self::assertFalse($options->equals(new CheckOptions(null, [], [new HostMacro(new HostMacroName('TOKEN'), 'x', true, 'd')])));
        self::assertFalse($options->equals(new CheckOptions(null)));
    }
}
