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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class GetInstallationCommandProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/pollers/installation-command';

    public function testItReturns401WhenNotAuthenticated(): void
    {
        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 1));
        self::assertResponseStatusCodeSame(401);
    }

    public function testItReturns404WhenPollerNotFound(): void
    {
        $this->login();

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 99999));
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturns403WhenUserHasNoPermission(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 1));
        self::assertResponseStatusCodeSame(403);
    }
}
