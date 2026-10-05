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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandTest extends TestCase
{
    #[DataProvider('centreonMonitoringAgentCommands')]
    public function testItRecognisesACentreonMonitoringAgentCommand(string $name, bool $locked, bool $expected): void
    {
        self::assertSame($expected, $this->command($name, $locked)->isCentreonMonitoringAgent());
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function centreonMonitoringAgentCommands(): iterable
    {
        yield 'locked, agent name' => ['OS-Windows-Centreon-Monitoring-Agent-Cpu', true, true];

        yield 'locked, -CMA- marker' => ['OS-Linux-CMA-Memory', true, true];

        yield 'locked, unrelated name' => ['OS-Linux-SNMP-Cpu', true, false];

        yield 'not locked, agent name' => ['OS-Windows-Centreon-Monitoring-Agent-Cpu', false, false];

        yield 'not locked, -CMA- marker' => ['custom-CMA-check', false, false];
    }

    private function command(string $name, bool $locked): Command
    {
        return new Command(
            id: new CommandId(1),
            name: new CommandName($name),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine('$USER1$/plugin'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: $locked,
            connector: null,
            comment: null,
        );
    }

    public function testMacrosAreEmptyWhenCommandLineHasNoMacro(): void
    {
        $command = $this->createCommand('$USER1$/check_icmp -H $HOSTADDRESS$ -w $ARG1$');

        self::assertSame([], $command->macros());
    }

    public function testMacrosAreDerivedFromCommandLineWithNullIdWhenNotStored(): void
    {
        $command = $this->createCommand('check $_HOSTUSER$ $_SERVICEPORT$ $_HOSTPASSWORD$');

        self::assertEquals(
            [
                new CommandMacro(null, 'USER', CommandMacroTypeEnum::Host),
                new CommandMacro(null, 'PASSWORD', CommandMacroTypeEnum::Host),
                new CommandMacro(null, 'PORT', CommandMacroTypeEnum::Service),
            ],
            $command->macros(),
        );
    }

    public function testMacrosIdsAreMatchedOnNameAndType(): void
    {
        $command = $this->createCommand(
            'check $_HOSTUSER$ $_SERVICEUSER$ $_HOSTPORT$',
            [
                new CommandMacro(10, 'USER', CommandMacroTypeEnum::Service),
                new CommandMacro(11, 'USER', CommandMacroTypeEnum::Host),
                // stale macro, no longer in the command line
                new CommandMacro(12, 'OLD', CommandMacroTypeEnum::Host),
            ],
        );

        self::assertEquals(
            [
                new CommandMacro(11, 'USER', CommandMacroTypeEnum::Host),
                new CommandMacro(null, 'PORT', CommandMacroTypeEnum::Host),
                new CommandMacro(10, 'USER', CommandMacroTypeEnum::Service),
            ],
            $command->macros(),
        );
    }

    public function testFirstStoredMacroWinsOnDuplicates(): void
    {
        $command = $this->createCommand(
            'check $_HOSTUSER$',
            [
                new CommandMacro(3, 'USER', CommandMacroTypeEnum::Host),
                new CommandMacro(4, 'USER', CommandMacroTypeEnum::Host),
            ],
        );

        self::assertEquals([new CommandMacro(3, 'USER', CommandMacroTypeEnum::Host)], $command->macros());
    }

    public function testSnmpHostMacrosAreExcluded(): void
    {
        $command = $this->createCommand(
            'check -C $_HOSTSNMPCOMMUNITY$ -v $_HOSTSNMPVERSION$ $_HOSTUSER$ $_SERVICESNMPCOMMUNITY$',
        );

        self::assertEquals(
            [
                new CommandMacro(null, 'USER', CommandMacroTypeEnum::Host),
                new CommandMacro(null, 'SNMPCOMMUNITY', CommandMacroTypeEnum::Service),
            ],
            $command->macros(),
        );
    }

    public function testStoredMacrosAreLoadedLazilyAndOnce(): void
    {
        $loader = new class () {
            public int $calls = 0;

            /**
             * @return list<CommandMacro>
             */
            public function load(): array
            {
                ++$this->calls;

                return [new CommandMacro(7, 'USER', CommandMacroTypeEnum::Host)];
            }
        };
        $command = $this->createCommand('check $_HOSTUSER$', $loader->load(...));

        self::assertSame(0, $loader->calls);
        $command->macros();
        self::assertEquals([new CommandMacro(7, 'USER', CommandMacroTypeEnum::Host)], $command->macros());
        self::assertSame(1, $loader->calls);
    }

    /**
     * @param list<CommandMacro>|(\Closure(): list<CommandMacro>) $storedMacros
     */
    private function createCommand(string $commandLine, array|\Closure $storedMacros = []): Command
    {
        return new Command(
            id: new CommandId(1),
            name: new CommandName('check'),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine($commandLine),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
            storedMacros: $storedMacros,
        );
    }
}
