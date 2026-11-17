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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceCheckOptionsTest extends TestCase
{
    public function testItHoldsACheckCommandWithItsArguments(): void
    {
        $options = new ServiceCheckOptions(new CommandId(4), ['80', 'warn']);

        self::assertSame(4, $options->checkCommandId?->value);
        self::assertSame(['80', 'warn'], $options->args);
        self::assertSame([], $options->macros);
    }

    /**
     * Arguments without a command: the command may come from the template chain.
     */
    public function testItAcceptsArgumentsWithoutADirectCheckCommand(): void
    {
        $options = new ServiceCheckOptions(null, ['80']);

        self::assertNull($options->checkCommandId);
        self::assertSame(['80'], $options->args);
    }

    public function testItReindexesArgumentsAsAList(): void
    {
        $options = new ServiceCheckOptions(new CommandId(4), [2 => 'a', 5 => 'b']);

        self::assertSame(['a', 'b'], $options->args);
    }

    public function testItRejectsAnArgumentContainingTheStorageDelimiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceCheckOptions(new CommandId(4), ['a!b']);
    }

    public function testItRejectsAnArgumentContainingAnEscapeToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceCheckOptions(new CommandId(4), ['a#T#b']);
    }

    public function testItHoldsItsOwnMacrosIndependentlyOfTheCommand(): void
    {
        $macro = new ServiceMacro(new ServiceMacroName('token'), 'value', isPassword: false, id: new ServiceMacroId(9));

        $options = new ServiceCheckOptions(null, [], [3 => $macro]);

        self::assertSame([$macro], $options->macros);
    }

    /**
     * Same-name stored macros are kept as read.
     */
    public function testItKeepsTwoMacrosWithTheSameName(): void
    {
        $first = new ServiceMacro(new ServiceMacroName('token'), 'one', isPassword: false);
        $second = new ServiceMacro(new ServiceMacroName('TOKEN'), 'two', isPassword: false);

        $options = new ServiceCheckOptions(null, [], [$first, $second]);

        self::assertSame([$first, $second], $options->macros);
    }

    public function testItRejectsAnInheritedMacro(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceCheckOptions(null, [], [
            new ServiceMacro(new ServiceMacroName('token'), 'v', isPassword: false, id: new ServiceMacroId(9), parent: ServiceMacroParentEnum::Template),
        ]);
    }

    /**
     * All-empty arguments are no argument, as legacy stores them; a mixed list is kept as is.
     *
     * @param list<string> $args
     * @param list<string> $expected
     */
    #[DataProvider('argumentListProvider')]
    public function testAnAllEmptyArgumentListIsNoArgument(array $args, array $expected): void
    {
        self::assertSame($expected, (new ServiceCheckOptions(new CommandId(4), $args))->args);
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function argumentListProvider(): iterable
    {
        yield 'no argument' => [[], []];

        yield 'one empty argument' => [[''], []];

        yield 'two empty arguments' => [['', ''], []];

        yield 'an empty and a filled argument' => [['', 'warn'], ['', 'warn']];
    }

    /**
     * A list mixing filled and empty arguments is refused on write.
     *
     * @param list<string> $args
     */
    #[DataProvider('partlyFilledProvider')]
    public function testItTellsAPartlyFilledArgumentList(array $args, bool $expected): void
    {
        self::assertSame($expected, (new ServiceCheckOptions(new CommandId(4), $args))->hasPartlyFilledArguments());
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function partlyFilledProvider(): iterable
    {
        yield 'no argument' => [[], false];

        yield 'every argument filled' => [['80', 'warn'], false];

        yield 'every argument empty' => [['', ''], false];

        yield 'a filled then an empty argument' => [['80', ''], true];

        yield 'an empty argument between filled ones' => [['80', '', 'crit'], true];
    }

    public function testEqualsHoldsForTheSameValues(): void
    {
        self::assertTrue(self::checkOptions()->equals(self::checkOptions()));
    }

    /**
     * Ids and descriptions are not compared: a macro equivalent to the stored one is no change, whichever row
     * it would be written to.
     */
    public function testEqualsComparesTheMacrosAsASetOfNameValueAndPasswordFlag(): void
    {
        $reordered = new ServiceCheckOptions(new CommandId(2), ['80', 'crit'], [
            new ServiceMacro(new ServiceMacroName('TOKEN'), 's3cr3t', isPassword: true),
            new ServiceMacro(new ServiceMacroName('PORT'), '8080', isPassword: false, description: 'kept', id: new ServiceMacroId(4)),
        ]);

        self::assertTrue(self::checkOptions()->equals($reordered));
    }

    public function testEqualsCountsTwoStoredMacrosOfTheSameName(): void
    {
        $port = new ServiceMacro(new ServiceMacroName('PORT'), '8080', isPassword: false);
        $other = new ServiceMacro(new ServiceMacroName('PORT'), '9090', isPassword: false);

        self::assertFalse((new ServiceCheckOptions(null, [], [$port, $other]))->equals(new ServiceCheckOptions(null, [], [$port, $port])));
        self::assertFalse((new ServiceCheckOptions(null, [], [$port, $port]))->equals(new ServiceCheckOptions(null, [], [$port])));
    }

    #[DataProvider('changeProvider')]
    public function testEqualsDetectsAChange(ServiceCheckOptions $changed): void
    {
        self::assertFalse(self::checkOptions()->equals($changed));
    }

    /**
     * @return iterable<string, array{ServiceCheckOptions}>
     */
    public static function changeProvider(): iterable
    {
        $macros = self::checkOptions()->macros;

        yield 'command' => [new ServiceCheckOptions(new CommandId(3), ['80', 'crit'], $macros)];

        yield 'no command' => [new ServiceCheckOptions(null, ['80', 'crit'], $macros)];

        yield 'arguments' => [new ServiceCheckOptions(new CommandId(2), ['80', 'warn'], $macros)];

        yield 'argument order' => [new ServiceCheckOptions(new CommandId(2), ['crit', '80'], $macros)];

        yield 'a macro value' => [new ServiceCheckOptions(new CommandId(2), ['80', 'crit'], [
            $macros[0]->withValue('9090', false),
            $macros[1],
        ])];

        yield 'a macro name' => [new ServiceCheckOptions(new CommandId(2), ['80', 'crit'], [
            $macros[0]->rename(new ServiceMacroName('OTHER')),
            $macros[1],
        ])];

        yield 'a password flag' => [new ServiceCheckOptions(new CommandId(2), ['80', 'crit'], [
            $macros[0],
            $macros[1]->withValue('s3cr3t', false),
        ])];

        yield 'a macro less' => [new ServiceCheckOptions(new CommandId(2), ['80', 'crit'], [$macros[0]])];
    }

    private static function checkOptions(): ServiceCheckOptions
    {
        return new ServiceCheckOptions(new CommandId(2), ['80', 'crit'], [
            new ServiceMacro(new ServiceMacroName('PORT'), '8080', isPassword: false),
            new ServiceMacro(new ServiceMacroName('TOKEN'), 's3cr3t', isPassword: true),
        ]);
    }
}
