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

use App\ActivityLogging\Domain\Repository\ActivityLogRepository;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandResource;
use Tests\App\Shared\ApiTestCase;

final class CreateCommandProcessorTest extends ApiTestCase
{
    public function testCreateCommand(): void
    {
        /** @var CommandRepository $repository */
        $repository = self::getContainer()->get(CommandRepository::class);

        $command = $repository->findOneByName(new CommandName('CommandNotif'));
        self::assertNull($command);

        $this->login();

        $response = $this->request('POST', '/api/latest/configuration/commands', [
            'json' => [
                'name' => 'CommandNotif',
                'type' => 'Notification',
                'command_line' => 'toto $ARG1$ $ARG2$ $_HOSTMAC1$ $_SERVICEMAC2$',
                'is_shell_enabled' => true,
                'connector' => '/api/latest/configuration/connectors/1',
                'comment' => 'coucou',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertJsonContains([
            'name' => 'CommandNotif',
            'type' => 'Notification',
            'command_line' => 'toto $ARG1$ $ARG2$ $_HOSTMAC1$ $_SERVICEMAC2$',
            'is_shell_enabled' => true,
            'comment' => 'coucou',
            'is_activated' => true,
            'is_from_monitoring_connector' => false,
        ]);
        self::assertArrayHasKey('id', $response->toArray());

        $command = $repository->findOneByName(new CommandName('CommandNotif'));
        self::assertNotNull($command);
    }

    public function testCannotCreateSameCommand(): void
    {
        $this->login();
        $this->request('POST', '/api/latest/configuration/commands', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => 'CommandNotif',
                'type' => 'Notification',
                'command_line' => 'toto $ARG1$ $ARG2$ $_HOSTMAC1$ $_SERVICEMAC2$',
                'is_shell_enabled' => true,
                'connector' => '/api/latest/configuration/connectors/1',
                'comment' => 'coucou',
            ],
        ]);
        self::assertResponseIsSuccessful();

        $this->request('POST', '/api/latest/configuration/commands', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => 'CommandNotif',
                'type' => 'Notification',
                'command_line' => 'toto $ARG1$ $ARG2$ $_HOSTMAC1$ $_SERVICEMAC2$',
                'is_shell_enabled' => true,
                'connector' => '/api/latest/configuration/connectors/1',
                'comment' => 'coucou',
            ],
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testCannotCreateCommandWithInvalidValues(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/commands', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => '',
                'type' => 'Notification',
                'command_line' => 'toto $ARG1$ $ARG2$ $_HOSTMAC1$ $_SERVICEMAC2$',
                'is_shell_enabled' => true,
                'connector' => '/api/latest/configuration/connectors/1',
                'comment' => 'coucou',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            'code' => 400,
            'message' => "[name] This value is too short. It should have 1 character or more.\n",
        ]);
    }

    public function testCannotCreateCommandWithInvalidValueTypes(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/commands', [
            'json' => [
                'name' => true,
                'type' => 'Notification',
                'command_line' => 'toto $ARG1$ $ARG2$ $_HOSTMAC1$ $_SERVICEMAC2$',
                'is_shell_enabled' => true,
                'connector' => '/api/latest/configuration/connectors/1',
                'comment' => 'coucou',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            'code' => 400,
            'message' => "[name] This value should be of type string.\n",
        ]);
    }

    public function testCannotCreateCommandIfNotLogged(): void
    {
        $this->request('POST', '/api/latest/configuration/commands', [
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCannotCreateCommandIfNotEnoughPermission(): void
    {
        $this->login('user');

        $this->request('POST', '/api/latest/configuration/commands', [
            'json' => [
                'name' => 'CommandNotif',
                'type' => 'Notification',
                'command_line' => 'toto $ARG1$ $ARG2$ $_HOSTMAC1$ $_SERVICEMAC2$',
                'is_shell_enabled' => true,
                'connector' => '/api/latest/configuration/connectors/1',
                'comment' => 'coucou',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains([
            'code' => 403,
            'message' => 'You are not allowed to create commands',
        ]);
    }

    public function testCreateCommandAddActivityLog(): void
    {
        /** @var ActivityLogRepository $repository */
        $repository = self::getContainer()->get(ActivityLogRepository::class);

        self::assertSame(0, $repository->count());

        $this->login();

        $this->request('POST', '/api/latest/configuration/commands', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => 'CommandNotif',
                'type' => 'Notification',
                'command_line' => 'toto $ARG1$ $ARG2$ $_HOSTMAC1$ $_SERVICEMAC2$',
                'is_shell_enabled' => true,
                'connector' => '/api/latest/configuration/connectors/1',
                'comment' => 'coucou',
            ],
        ]);

        self::assertResponseIsSuccessful();

        self::assertSame(1, $repository->count());
    }

    protected static function apiUsers(): array
    {
        return ['user'];
    }
}
