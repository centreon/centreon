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
use Webmozart\Assert\Assert;

/**
 * Creates non-admin API users holding the "Configuration > Services > Services by host" topology role,
 * bridged to ServicePermissionEnum by CredentialTransformer::LEGACY_PERMISSION_MAP. Used by the full-HTTP
 * tests of the service form selectors (/configuration/services/*).
 *
 * Requires the using class to extend Tests\App\Shared\ApiTestCase (createApiUser()).
 */
trait GrantsServiceTopologyRole
{
    // topology pages whose hierarchy builds ROLE_CONFIGURATION_SERVICES_SERVICES_BY_HOST_R[W]
    private const SERVICE_TOPOLOGY_PAGES = [6, 602, 60201];

    // CentreonACL read-write access right, see Centreon\Domain\Repository\TopologyRepository
    private const ACL_ACCESS_READ_WRITE = 1;

    private function createNonAdminContact(Connection $connection, string $alias): int
    {
        self::createApiUser($connection, $alias, admin: false);

        $contactId = $connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );
        Assert::notFalse($contactId);
        Assert::scalar($contactId);

        return (int) $contactId;
    }

    /**
     * Grants "Configuration > Services > Services by host" through real ACL menu rows and returns the
     * access group id (reusable for row-level scoping).
     *
     * @param int $accessRight CentreonACL access right: 1 = read-write, 2 = read-only
     * @param string $tag unique suffix for the ACL rows of this test
     */
    private function grantServiceTopologyRole(Connection $connection, int $contactId, int $accessRight, string $tag): int
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

        foreach (self::SERVICE_TOPOLOGY_PAGES as $topologyPage) {
            $topologyId = $connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            Assert::notFalse($topologyId, "topology_page {$topologyPage} not found in fixtures");
            Assert::scalar($topologyId);

            $connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopologyId,
                'access_right' => $accessRight,
            ]);
        }

        return $aclGroupId;
    }

    /**
     * Logs in a new non-admin user holding read-write on "Services by host".
     */
    private function loginAsServiceReadWriteUser(Connection $connection, string $tag): void
    {
        $username = bin2hex(random_bytes(8));
        $this->grantServiceTopologyRole($connection, $this->createNonAdminContact($connection, $username), self::ACL_ACCESS_READ_WRITE, $tag);
        $this->login($username);
    }
}
