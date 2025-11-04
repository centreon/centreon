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

use Tests\App\Shared\ApiTestCase;

final class FindConnectorProviderTest extends ApiTestCase
{
    public function testGetConnector(): void
    {
        $this->login();
        $this->request('GET', '/api/latest/configuration/connectors/1');

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'id' => 1,
            'name' => 'Perl Connector',
            'command_line' => 'centreon_connector_perl --log-file=/var/log/centreon-engine/connector-perl.log',
            'description' => '',
            'is_activated' => true,
        ]);
    }

    public function testGetConnectorIsUnauthorizedForUserWithoutSufficientACL(): void
    {
        $this->login('user');
        $this->request('GET', '/api/latest/configuration/connectors/1');

        $this->assertResponseStatusCodeSame(403);
    }

    protected static function apiUsers(): array
    {
        return ['user'];
    }
}
