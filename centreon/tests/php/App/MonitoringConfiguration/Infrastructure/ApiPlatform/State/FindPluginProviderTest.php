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
use Tests\App\Shared\ApiTestCase;

/** @group wip */
final class FindPluginProviderTest extends ApiTestCase
{
    public function testItFindPlugins(): void
    {
        $this->login();

        $this->request('GET', '/api/latest/configuration/plugins/check_dhcp');
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(PluginResource::class);
        self::assertJsonContains(
            ['name' => 'check_dhcp', 'command_line' => '/usr/lib64/nagios/plugins/check_dhcp']
        );
    }

    public function testItNotFindUnknownPlugin(): void
    {
        $this->login();

        $this->request('GET', '/api/latest/configuration/plugins/unknown_plugin');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', '/api/latest/configuration/plugins/check_dhcp');
        self::assertResponseStatusCodeSame(401);
    }
}
