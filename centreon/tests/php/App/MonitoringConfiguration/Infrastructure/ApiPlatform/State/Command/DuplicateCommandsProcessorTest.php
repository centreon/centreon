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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\DuplicateCommandResource;
use Symfony\Component\HttpFoundation\Response;
use Tests\App\Shared\ApiTestCase;

final class DuplicateCommandsProcessorTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/commands/_duplicate';

    public function testDuplicateCommandSuccessfully(): void
    {
        /** @var CommandRepository $repository */
        $repository = self::getContainer()->get(CommandRepository::class);

        $originalCommand = new Command(
            id: new CommandId(999),
            name: new CommandName('original_command'),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine('/usr/lib/nagios/plugins/check_ping -H $HOSTADDRESS$'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: new CommandComment('come comment'),
        );
        $repository->add($originalCommand);

        $this->login();

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'ids' => [1],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertMatchesResourceItemJsonSchema(DuplicateCommandResource::class);

        $responseData = $response->toArray();
        self::assertArrayHasKey('member', $responseData);
        self::assertIsArray($responseData['member']);
        self::assertCount(1, $responseData['member']);
        self::assertIsArray($responseData['member'][0]);
        self::assertIsString($responseData['member'][0]['command']);
        self::assertStringContainsString('/api/latest/configuration/commands/', $responseData['member'][0]['command']);
        self::assertEquals(204, $responseData['member'][0]['status']);
        self::assertIsString($responseData['member'][0]['message']);
        self::assertStringContainsString('Command duplicated successfully', $responseData['member'][0]['message']);
    }

    public function testDuplicateCommandWithoutPermission(): void
    {
        /** @var CommandRepository $repository */
        $repository = self::getContainer()->get(CommandRepository::class);

        $originalCommand = new Command(
            id: new CommandId(998),
            name: new CommandName('test_command'),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine('/usr/lib/nagios/plugins/check_http'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: new CommandComment('Test command'),
        );
        $repository->add($originalCommand);

        $this->login('user');

        $this->request('POST', self::BASE_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'ids' => [998],
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDuplicateNonExistentCommand(): void
    {
        $this->login();

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'ids' => [99999],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(DuplicateCommandResource::class);

        $responseData = $response->toArray();
        self::assertArrayHasKey('member', $responseData);
        self::assertIsArray($responseData['member']);
        self::assertCount(1, $responseData['member']);
        self::assertIsArray($responseData['member'][0]);
        self::assertEquals(404, $responseData['member'][0]['status']);
        self::assertIsString($responseData['member'][0]['message']);
        self::assertStringContainsString('Command with ID 99999 not found', $responseData['member'][0]['message']);
    }

    public function testDuplicateMultipleCommands(): void
    {
        /** @var CommandRepository $repository */
        $repository = self::getContainer()->get(CommandRepository::class);

        $command1 = new Command(
            id: new CommandId(997),
            name: new CommandName('command_one'),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine('/usr/lib/nagios/plugins/check_ping'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: new CommandComment('First command'),
        );
        $repository->add($command1);

        $command2 = new Command(
            id: new CommandId(996),
            name: new CommandName('command_two'),
            type: CommandTypeEnum::Notification,
            commandLine: new CommandLine('/usr/bin/mail'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: new CommandComment('Second command'),
        );
        $repository->add($command2);

        $this->login();

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'ids' => [1, 2],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(DuplicateCommandResource::class);

        $responseData = $response->toArray();
        self::assertIsArray($responseData['member']);
        self::assertCount(2, $responseData['member']);
    }

    protected static function apiUsers(): array
    {
        return ['user'];
    }
}
