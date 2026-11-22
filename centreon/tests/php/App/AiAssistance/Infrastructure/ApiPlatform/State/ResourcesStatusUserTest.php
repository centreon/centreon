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

namespace Tests\App\AiAssistance\Infrastructure\ApiPlatform\State;

use Doctrine\DBAL\Connection;
use Tests\App\Shared\GrantsTopologyPages;

/**
 * A non-admin user holding the Resources Status page gets past the AI
 * assistance permission.
 */
final class ResourcesStatusUserTest extends AiAssistanceApiTestCase
{
    use GrantsTopologyPages;

    // Monitoring > Resources Status: builds ROLE_MONITORING_RESOURCES_STATUS_RW
    private const RESOURCES_STATUS_TOPOLOGY_PAGES = [2, 200];

    // CentreonACL access right: read-write
    private const ACL_ACCESS_READ_WRITE = 1;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testReadsTheAvailability(): void
    {
        $this->loginAsResourcesStatusUser();

        $this->request('GET', '/api/latest/ai/availability');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['is_available' => false, 'reason' => 'no_provider']);
    }

    public function testReachesTheAssistantsWhichNeedAProvider(): void
    {
        $this->loginAsResourcesStatusUser();

        $this->request('POST', '/api/latest/ai/resource-filter', ['json' => ['prompt' => 'critical services']]);

        // past the permission check: refused only because no provider is enabled
        self::assertResponseStatusCodeSame(409);
    }

    private function loginAsResourcesStatusUser(): void
    {
        $username = 'ai-assistance-operator-' . bin2hex(random_bytes(6));
        $this->grantTopologyPages(
            $this->connection,
            $this->createNonAdminContact($this->connection, $username),
            self::RESOURCES_STATUS_TOPOLOGY_PAGES,
            self::ACL_ACCESS_READ_WRITE,
            $username,
        );
        $this->login($username);
    }
}
