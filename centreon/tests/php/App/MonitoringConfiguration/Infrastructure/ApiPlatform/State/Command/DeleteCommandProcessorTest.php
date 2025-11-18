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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use Tests\App\Shared\ApiTestCase;

final class DeleteCommandProcessorTest extends ApiTestCase
{
    public function testDeleteCommandSuccessfully(): void
    {
        /** @var CommandRepository $repository */
        $repository = self::getContainer()->get(CommandRepository::class);

        $repository->add(new Command(
            id: new CommandId(1),
            name: new CommandName('original name'),
            commandLine: new CommandLine('original command'),
            type: CommandTypeEnum::Check,
            isShellEnabled: true,
            isFromMonitoringConnector: false,
            isActivated: true,
            connector: null,
            comment: null,
        ));

        $this->login();

        $this->request('DELETE', '/api/latest/configuration/commands/1');

        self::assertResponseIsSuccessful();
    }

    public function testDeleteNonExistingCommand(): void
    {
        $this->login();

        $this->request('DELETE', '/api/latest/configuration/commands/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains([
            'title' => 'An error occurred',
            'status' => 404,
        ]);
    }

    public function testDeleteCommandIsFromMonitoringConnector(): void
    {
        /** @var CommandRepository $repository */
        $repository = self::getContainer()->get(CommandRepository::class);

        $repository->add(new Command(
            id: new CommandId(1),
            name: new CommandName('original name'),
            commandLine: new CommandLine('original command'),
            type: CommandTypeEnum::Check,
            isShellEnabled: true,
            isFromMonitoringConnector: true,
            isActivated: true,
            connector: null,
            comment: null,
        ));

        $this->login();

        $this->request('DELETE', '/api/latest/configuration/commands/1');

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains([
            'title' => 'An error occurred',
            'status' => 404,
        ]);
    }

    public function testDeleteCommandWithoutAuthentication(): void
    {
        $this->request('DELETE', '/api/latest/configuration/commands/1');

        self::assertResponseStatusCodeSame(401);
    }
}
