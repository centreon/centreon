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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class PatchHostProcessorTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts';

    /** @var array{headers: array{Content-Type: string}} */
    private const PATCH_HEADERS = ['headers' => ['Content-Type' => 'application/merge-patch+json']];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('PATCH', self::BASE_ENDPOINT . '/1', self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForAUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testItReturnsNotFoundForAnUnknownHost(): void
    {
        $this->login();

        $this->request('PATCH', self::BASE_ENDPOINT . '/9999999', self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testItDisablesAHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('0', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testItEnablesADisabledHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->update('host', ['host_activate' => '0'], ['host_id' => $hostId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => true]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testItFlagsThePollerWhenTogglingActivation(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    public function testItFlagsAllAclResourcesForAnAdmin(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r-' . bin2hex(random_bytes(4)), 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(204);
        /** @var int|string $changedFlag */
        $changedFlag = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$aclResId]);
        self::assertSame(1, (int) $changedFlag);
    }

    public function testItIsASilentNoOpWhenAlreadyInTheRequestedState(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId); // inserted as activated
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => true]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
        // Unchanged state: the poller must not be flagged for a reload it does not need.
        self::assertSame('0', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    private function insertPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'ns_ip_address' => '127.0.0.1',
            'uid' => random_int(1, \PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(string $name, int $pollerId): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        return $hostId;
    }

    private function uniqueName(string $prefix = 'host'): string
    {
        return $prefix . '-' . bin2hex(random_bytes(6));
    }
}
