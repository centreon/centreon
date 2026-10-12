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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandMacroOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\ResourceCommandTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ResourceConnectorTransformer;
use PHPUnit\Framework\TestCase;

final class ResourceCommandTransformerTest extends TestCase
{
    public function testMacrosAreOnlyLoadedWhenRead(): void
    {
        $loader = new class () {
            public int $calls = 0;

            /**
             * @return list<CommandMacro>
             */
            public function load(): array
            {
                ++$this->calls;

                return [new CommandMacro(new CommandMacroId(5), 'USER', CommandMacroTypeEnum::Host)];
            }
        };
        $command = new Command(
            id: new CommandId(1),
            name: new CommandName('check'),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine('check $_HOSTUSER$ $_SERVICEPORT$'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
            storedMacros: $loader->load(...),
        );

        $resource = (new ResourceCommandTransformer(new ResourceConnectorTransformer()))->transform($command);

        self::assertSame(0, $loader->calls);
        self::assertEquals(
            [
                new CommandMacroOutput(5, 'USER', 'host'),
                new CommandMacroOutput(null, 'PORT', 'service'),
            ],
            $resource->getMacros(),
        );
        $resource->getMacros();
        self::assertSame(1, $loader->calls);
    }
}
