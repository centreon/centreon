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

namespace Tests\App\Shared;

use Doctrine\DBAL\Connection;

/**
 * Creates non-admin API users and grants them topology pages (needs ApiTestCase).
 *
 * A page grants the ROLE_<LEVEL1>_<LEVEL2>_..._R[W] role built from its hierarchy:
 * grant the page with all its parents.
 */
trait GrantsTopologyPages
{
    private function createNonAdminContact(Connection $connection, string $alias): int
    {
        self::createApiUser($connection, $alias, admin: false);

        $contactId = $connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );
        \assert($contactId !== false && \is_scalar($contactId));

        return (int) $contactId;
    }

    /**
     * Returns the access group id (reusable for row-level scoping).
     *
     * @param list<int> $topologyPages
     * @param int $accessRight CentreonACL access right: 1 = read-write, 2 = read-only
     * @param string $tag unique suffix for the ACL rows of this test
     */
    private function grantTopologyPages(Connection $connection, int $contactId, array $topologyPages, int $accessRight, string $tag): int
    {
        $connection->insert('acl_groups', [
            'acl_group_name' => "topology-group-{$tag}",
            'acl_group_alias' => "topology-group-{$tag}",
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $connection->lastInsertId();
        $connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $connection->insert('acl_topology', [
            'acl_topo_name' => "topology-rule-{$tag}",
            'acl_topo_alias' => "topology-rule-{$tag}",
            'acl_topo_activate' => '1',
        ]);
        $aclTopologyId = (int) $connection->lastInsertId();
        $connection->insert('acl_group_topology_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_topology_id' => $aclTopologyId,
        ]);

        foreach ($topologyPages as $topologyPage) {
            $topologyId = $connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            \assert($topologyId !== false && \is_scalar($topologyId), "topology_page {$topologyPage} not found in fixtures");

            $connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopologyId,
                'access_right' => $accessRight,
            ]);
        }

        return $aclGroupId;
    }
}
