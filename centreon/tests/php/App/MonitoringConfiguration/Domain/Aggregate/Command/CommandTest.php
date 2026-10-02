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
}
