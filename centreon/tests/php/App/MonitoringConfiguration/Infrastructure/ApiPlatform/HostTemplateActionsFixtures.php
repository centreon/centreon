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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * Fixtures of the host template duplicate and delete HTTP tests.
 *
 * Requires the using class to extend Tests\App\Shared\ApiTestCase and to set $connection and
 * $realTimeConnection.
 */
trait HostTemplateActionsFixtures
{
    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_TEMPLATES_R[W]
    private const HOST_TEMPLATE_TOPOLOGY_PAGES = [6, 601, 60103];

    // CentreonACL read-only access right, see Centreon\Domain\Repository\TopologyRepository
    private const ACL_ACCESS_READ_ONLY = 2;

    private Connection $connection;

    private Connection $realTimeConnection;

    private function loginAsHostTemplateReader(): void
    {
        $username = bin2hex(random_bytes(8));
        self::createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = ?', [$username]);
        Assert::scalar($contactId);

        $this->connection->insert('acl_groups', ['acl_group_name' => "ht-reader-{$username}", 'acl_group_alias' => "ht-reader-{$username}", 'acl_group_activate' => '1']);
        $groupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $groupId, 'contact_contact_id' => (int) $contactId]);
        $this->connection->insert('acl_topology', ['acl_topo_name' => "ht-reader-{$username}", 'acl_topo_alias' => "ht-reader-{$username}", 'acl_topo_activate' => '1']);

        $topologyRuleId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_topology_relations', ['acl_group_id' => $groupId, 'acl_topology_id' => $topologyRuleId]);
        foreach (self::HOST_TEMPLATE_TOPOLOGY_PAGES as $page) {
            $topologyId = $this->connection->fetchOne('SELECT topology_id FROM topology WHERE topology_page = ?', [$page]);
            Assert::scalar($topologyId, "topology_page {$page} not found in fixtures");
            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $topologyRuleId,
                'access_right' => self::ACL_ACCESS_READ_ONLY,
            ]);
        }

        $this->login($username);
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insertTemplate(array $columns = []): int
    {
        $this->connection->insert('host', $columns + ['host_name' => $this->uniqueName(), 'host_register' => '0', 'host_activate' => '1', 'host_locked' => 0]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(string $name, ?int $pollerId = null): int
    {
        $this->connection->insert('host', ['host_name' => $name, 'host_address' => '127.0.0.1', 'host_register' => '1', 'host_activate' => '1']);
        $hostId = (int) $this->connection->lastInsertId();
        if ($pollerId !== null) {
            $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);
        }

        return $hostId;
    }

    private function insertPoller(): int
    {
        $this->connection->insert('nagios_server', ['name' => $this->uniqueName(), 'ns_ip_address' => '127.0.0.1', 'uid' => random_int(1, \PHP_INT_MAX), 'updated' => '0']);

        return (int) $this->connection->lastInsertId();
    }

    private function isPollerFlagged(int $pollerId): bool
    {
        return $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]) === '1';
    }

    private function templateIdByName(string $name): int|false
    {
        $id = $this->connection->fetchOne("SELECT host_id FROM host WHERE host_name = ? AND host_register = '0'", [$name]);

        return $id === false ? false : self::asInt($id);
    }

    /**
     * @return list<string> the action types logged for this host or template id, oldest first
     */
    private function loggedActions(int $id): array
    {
        /** @var list<string> */
        return $this->realTimeConnection->fetchFirstColumn(
            "SELECT action_type FROM log_action WHERE object_type = 'host' AND object_id = ? ORDER BY action_log_id",
            [$id],
        );
    }

    private function centreonAclRowCount(): int
    {
        return self::asInt($this->realTimeConnection->fetchOne('SELECT COUNT(*) FROM centreon_acl'));
    }

    private function uniqueName(): string
    {
        return 'ht-' . Uuid::v4()->toBase58();
    }

    private static function asInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && is_numeric($value)), 'Expected an integer column value.');

        return (int) $value;
    }
}
