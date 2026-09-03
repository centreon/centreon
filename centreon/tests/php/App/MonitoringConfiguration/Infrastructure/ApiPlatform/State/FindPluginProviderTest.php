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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\PluginResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class FindPluginProviderTest extends ApiTestCase
{
    private string $pluginDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginDir = sys_get_temp_dir() . '/centreon_test_plugins_' . bin2hex(random_bytes(4));
        mkdir($this->pluginDir, 0755, true);
        touch($this->pluginDir . '/check_dhcp');
        chmod($this->pluginDir . '/check_dhcp', 0755);

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(
            "UPDATE options SET `value` = :path WHERE `key` = 'nagios_path_plugins'",
            ['path' => $this->pluginDir . '/']
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->pluginDir)) {
            array_map('unlink', glob($this->pluginDir . '/*') ?: []);
            rmdir($this->pluginDir);
        }
        parent::tearDown();
    }

    public function testItFindPlugins(): void
    {
        $this->login();

        $this->request('GET', '/api/configuration/plugins/check_dhcp');
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(PluginResource::class);
        self::assertJsonContains(
            ['name' => 'check_dhcp']
        );
    }

    public function testItNotFindUnknownPlugin(): void
    {
        $this->login();

        $this->request('GET', '/api/configuration/plugins/unknown_plugin');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', '/api/configuration/plugins/check_dhcp');
        self::assertResponseStatusCodeSame(401);
    }
}
