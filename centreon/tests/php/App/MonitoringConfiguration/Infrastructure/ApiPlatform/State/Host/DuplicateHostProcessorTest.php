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

final class DuplicateHostProcessorTest extends ApiTestCase
{
    private Connection $connection;

    private Connection $realTimeConnection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('POST', '/api/configuration/hosts/1/_duplicate');
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('web'), $pollerId);

        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(403);
    }

    public function testItReturns404WhenTheHostDoesNotExist(): void
    {
        $this->login();

        $this->request('POST', '/api/configuration/hosts/2147483000/_duplicate');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturns404WhenTheHostIsOutsideTheViewerAclScope(): void
    {
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('web'), $pollerId);

        // A non-admin with the host read/write role but no ACL access to this host: the source lookup
        // is ACL-scoped, so an out-of-scope host reads as not found (404), not 403 or 204. Proves the
        // command's viewerId scoping — dropping it would let this restricted user reach the host (204).
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostReadAndWriteTopologyRole($contactId);

        $this->login($username);

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(404);
    }

    public function testItDuplicatesAHostWithTheFirstFreeSuffix(): void
    {
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('web');
        $hostId = $this->insertHost($name, $pollerId);

        $this->login();

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(204);

        $copyId = $this->connection->fetchOne(
            "SELECT host_id FROM host WHERE host_name = :name AND host_register = '1'",
            ['name' => $name . '_1'],
        );
        self::assertIsScalar($copyId, 'the copy is persisted under the first free "_1" suffix');
        self::assertNotSame($hostId, (int) $copyId);
    }

    public function testItFlagsTheSourcePollerAsChanged(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);
        $hostId = $this->insertHost($this->uniqueName('web'), $pollerId);

        $this->login();

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(204);

        $updatedFlag = $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]);
        self::assertSame('1', $updatedFlag);
    }

    public function testItCopiesTheSourceAclConfigurationRelationsOntoTheCopy(): void
    {
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('web');
        $hostId = $this->insertHost($name, $pollerId);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'res-' . $name,
            'acl_res_alias' => 'res-' . $name,
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_resources_host_relations', [
            'host_host_id' => $hostId,
            'acl_res_id' => $aclResId,
        ]);
        $this->connection->insert('acl_resources_hostex_relations', [
            'host_host_id' => $hostId,
            'acl_res_id' => $aclResId,
        ]);

        $this->login();

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(204);

        $copyId = $this->hostIdByName($name . '_1');

        foreach (['acl_resources_host_relations', 'acl_resources_hostex_relations'] as $table) {
            $copyRelationCount = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM {$table} WHERE host_host_id = :hostId AND acl_res_id = :aclResId",
                ['hostId' => $copyId, 'aclResId' => $aclResId],
            );
            self::assertIsScalar($copyRelationCount);
            self::assertSame(1, (int) $copyRelationCount, "the copy inherits the source {$table} row");
        }
    }

    public function testItCopiesTheSourceRealtimeAclRowsOntoTheCopy(): void
    {
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('web');
        $hostId = $this->insertHost($name, $pollerId);

        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'grp-' . $name,
            'acl_group_alias' => 'grp-' . $name,
            'acl_group_activate' => '1',
        ]);
        $groupId = (int) $this->connection->lastInsertId();

        // Seed the real-time cache scoping the source host to that group (host-level row).
        $this->realTimeConnection->insert('centreon_acl', [
            'group_id' => $groupId,
            'host_id' => $hostId,
            'service_id' => null,
        ]);

        $this->login();

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(204);

        $copyId = $this->hostIdByName($name . '_1');

        $copyAclCount = $this->realTimeConnection->fetchOne(
            'SELECT COUNT(*) FROM centreon_acl WHERE host_id = :hostId AND service_id IS NULL AND group_id = :groupId',
            ['hostId' => $copyId, 'groupId' => $groupId],
        );
        self::assertIsScalar($copyAclCount);
        self::assertSame(1, (int) $copyAclCount, 'the copy inherits the source real-time ACL row');
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
        return $prefix . '_' . bin2hex(random_bytes(4));
    }

    private function hostIdByName(string $name): int
    {
        $id = $this->connection->fetchOne(
            "SELECT host_id FROM host WHERE host_name = :name AND host_register = '1'",
            ['name' => $name],
        );
        self::assertIsScalar($id, "expected a persisted host named {$name}");

        return (int) $id;
    }

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        /** @var int|string $contactId */
        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias],
        );

        return (int) $contactId;
    }

    /**
     * Grants the "Configuration > Hosts > Hosts" read-write topology access (the legacy menu role
     * bridged to HostPermissionEnum::CanReadAndWrite), without any resource-scoping ACL — so the
     * contact passes the permission gate but sees no host through centreon_acl.
     */
    private function grantHostReadAndWriteTopologyRole(int $contactId): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'topology-rw-group-' . $contactId,
            'acl_group_alias' => 'topology-rw-group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'topology-rw-rule-' . $contactId,
            'acl_topo_alias' => 'topology-rw-rule-' . $contactId,
            'acl_topo_activate' => '1',
        ]);
        $aclTopoId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_topology_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_topology_id' => $aclTopoId,
        ]);

        foreach ([6, 601, 60101] as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage],
            );
            self::assertIsScalar($topologyId, "topology_page {$topologyPage} not found in fixtures");

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopoId,
                'access_right' => 1,
            ]);
        }
    }
}
