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
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class FindCommandProviderTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('command', [
            'command_id' => 1,
            'command_name' => 'check_host_alive',
            'command_line' => '$USER1$/check_icmp -H $HOSTADDRESS$',
            'command_type' => 2,
            'enable_shell' => '0',
            'command_activate' => '1',
            'command_locked' => '0',
        ]);
    }

    public function testItFindCommand(): void
    {
        $this->login();

        $this->request('GET', '/api/configuration/commands/1');
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
    }

    public function testItReturnsCommandMacros(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('command', [
            'command_id' => 2,
            'command_name' => 'check_with_macros',
            'command_line' => 'check -C $_HOSTSNMPCOMMUNITY$ $_HOSTUSER$ $_SERVICEPORT$',
            'command_type' => 2,
            'enable_shell' => '0',
            'command_activate' => '1',
            'command_locked' => '1',
        ]);
        // only USER is stored, like commands created by monitoring connectors
        $connection->insert('on_demand_macro_command', [
            'command_macro_id' => 10,
            'command_macro_name' => 'USER',
            'command_command_id' => 2,
            'command_macro_type' => '1',
        ]);

        $this->login();

        $response = $this->request('GET', '/api/configuration/commands/2');
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertSame(
            [
                ['id' => 10, 'name' => 'USER', 'type' => 'host'],
                ['id' => null, 'name' => 'PORT', 'type' => 'service'],
            ],
            $response->toArray()['macros'],
        );
    }
}
