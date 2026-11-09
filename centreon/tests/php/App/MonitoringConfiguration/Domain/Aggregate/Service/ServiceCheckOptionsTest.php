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
}
