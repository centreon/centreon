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

namespace Tests\Core\Integration\Contact\Infrastructure\Api\FindContactGroups;

use Tests\Core\Integration\CoreApiTestCase;

/**
 * Integration test for the FindContactGroups endpoint.
 *
 * Route: GET /api/latest/configuration/contacts/groups
 * Controller: Core\Contact\Infrastructure\Api\FindContactGroups\FindContactGroupsController
 *
 * Run inside the Docker container:
 *   docker exec centreon-app bash -c "cd /usr/share/centreon && vendor/bin/phpunit -c phpunit.core-integration.xml --filter FindContactGroupsIntegrationTest"
 */
final class FindContactGroupsIntegrationTest extends CoreApiTestCase
{
    public function testAdminCanListContactGroups(): void
    {
        $this->login('test-cg-admin');

        $response = $this->request('GET', '/api/latest/configuration/contacts/groups');

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), associative: true);
        $this->assertArrayHasKey('result', $body);
        $this->assertIsArray($body['result']);
    }

    public function testNonAdminCanListContactGroupsHeIsMemberOf(): void
    {
        $contactGroupId = $this->createContactGroup('test-cg-integration', 'Test CG Integration');
        $this->addUserToContactGroup('test-cg-user', $contactGroupId);

        $this->login('test-cg-user');

        $response = $this->request('GET', '/api/latest/configuration/contacts/groups');

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), associative: true);
        $this->assertArrayHasKey('result', $body);
        $this->assertIsArray($body['result']);
        $this->assertNotEmpty($body['result'], 'Non-admin user should see contact groups they are a member of.');

        $contactGroupNames = array_column($body['result'], 'name');
        $this->assertContains('test-cg-integration', $contactGroupNames);
    }

    public function testNonAdminWithoutMembershipSeesEmptyList(): void
    {
        $this->login('test-cg-user');

        $response = $this->request('GET', '/api/latest/configuration/contacts/groups');

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), associative: true);
        $this->assertArrayHasKey('result', $body);
        $this->assertEmpty($body['result'], 'Non-admin user without contact group membership should see an empty list.');
    }

    public function testUnauthenticatedRequestIsRejected(): void
    {
        // No login() call — no token injected.
        $response = $this->request('GET', '/api/latest/configuration/contacts/groups');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    protected static function apiUsers(): array
    {
        return [
            ['identifier' => 'test-cg-admin', 'admin' => true],
            [
                'identifier' => 'test-cg-user',
                'admin' => false,
                'topologyPages' => [
                    ['id' => 6, 'access_right' => 1],   // Configuration (parent)
                    ['id' => 66, 'access_right' => 1],   // Users (parent)
                    ['id' => 85, 'access_right' => 2],   // Contact Groups (read-only)
                ],
            ],
        ];
    }
}
