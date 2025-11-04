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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandResource;
use Tests\App\Shared\ApiTestCase;

final class UpdateCommandProcessorTest extends ApiTestCase
{
    public function testUpdateCommandSuccessfully(): void
    {
        $this->login();

        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Updated Command Name',
                'type' => 'Check',
                'command_line' => '/usr/lib/nagios/plugins/check_updated',
                'comment' => 'Updated comment',
                'is_shell_enabled' => true,
                'is_activated' => false,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertJsonContains([
            'name' => 'Updated Command Name',
            'type' => 'Check',
            'command_line' => '/usr/lib/nagios/plugins/check_updated',
            'comment' => 'Updated comment',
            'is_shell_enabled' => true,
            'is_activated' => false,
        ]);
    }

    public function testUpdateCommandAddConnector(): void
    {
        $this->login();

        // first make sure connector is not existing in command
        $response = $this->request('GET', '/api/latest/configuration/commands/1');
        self::assertArrayNotHasKey('connector', $response->toArray());

        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Command with Connector',
                'connector' => [
                    'id' => 1,
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertJsonContains([
            'name' => 'Command with Connector',
            'connector' => ['id' => 1, 'name' => 'Perl Connector'],
        ]);
    }

    public function testUpdateCommandRemoveConnector(): void
    {
        $this->login();

        // check thatt the command has a connector first
        $response = $this->request('GET', '/api/latest/configuration/commands/1');
        self::assertArrayHasKey('connector', $response->toArray());

        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Command without Connector',
                'connector' => null,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertArrayNotHasKey('connector', $response->toArray());
    }

    public function testUpdateCommandWithInvalidConnectorId(): void
    {
        $this->login();

        $response = $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Command with Invalid Connector',
                'connector' => [
                    'id' => 999999,
                    'name' => 'Non-existent Connector',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertJsonContains([
            'name' => 'Command with Invalid Connector',
        ]);
        self::assertArrayNotHasKey('connector', $response->toArray());
    }

    public function testUpdateCommandKeepExistingConnector(): void
    {
        $this->login();
        // First, add a connector to the command
        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'connector' => [
                    'id' => 1,
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertJsonContains([
            'connector' => ['id' => 1, 'name' => 'Perl Connector'],
        ]);
        // Now, update the command without specifying the connector
        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Command Keeping Connector',
            ],
        ]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        // ensure connector is still present
        self::assertJsonContains([
            'name' => 'Command Keeping Connector',
            'connector' => ['id' => 1, 'name' => 'Perl Connector'],
        ]);
    }

    public function testUpdateCommandDeactivate(): void
    {
        $this->login();

        // first, ensure command is activated before test
        $response = $this->request('GET', '/api/latest/configuration/commands/1');
        self::assertTrue($response->toArray()['is_activated']);

        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'is_activated' => false,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertJsonContains([
            'is_activated' => false,
        ]);
    }

    public function testUpdateCommandActivate(): void
    {
        $this->login();

        $response = $this->request('GET', '/api/latest/configuration/commands/1');
        // ensure command is deactivated before test
        self::assertFalse($response->toArray()['is_activated']);

        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'is_activated' => true,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertJsonContains([
            'is_activated' => true,
        ]);
    }

    public function testUpdateNonExistingCommand(): void
    {
        $this->login();

        $this->request('PATCH', '/api/latest/configuration/commands/999999', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Non-existent Command',
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains([
            'title' => 'An error occurred',
            'status' => 404,
        ]);
    }

    public function testUpdateCommandWithEmptyJson(): void
    {
        $this->login();

        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
    }

    public function testUpdateCommandIsShellEnabled(): void
    {
        $this->login();

        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'is_shell_enabled' => true,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'is_shell_enabled' => true,
        ]);
    }

    public function testUpdateCommandWithoutAuthentication(): void
    {
        $this->request('PATCH', '/api/latest/configuration/commands/1', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Unauthorized Update',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}
