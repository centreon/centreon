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
use Tests\App\Shared\GrantsTopologyPages;

/**
 * Creates non-admin API users holding the Hosts > Templates or Hosts > Hosts topology role (needs ApiTestCase).
 */
trait GrantsHostTemplateTopologyRole
{
    use GrantsTopologyPages;

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_TEMPLATES_R[W]
    private const TEMPLATES_TOPOLOGY_PAGES = [6, 601, 60103];

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_HOSTS_R[W]
    private const HOSTS_TOPOLOGY_PAGES = [6, 601, 60101];

    // CentreonACL access rights, see Centreon\Domain\Repository\TopologyRepository
    private const ACL_ACCESS_READ_WRITE = 1;
    private const ACL_ACCESS_READ_ONLY = 2;

    /**
     * Returns the access group id (reusable for row-level scoping).
     *
     * @param int $accessRight CentreonACL access right: 1 = read-write, 2 = read-only
     * @param string $tag unique suffix for the ACL rows of this test
     */
    private function grantHostTemplateTopologyRole(Connection $connection, int $contactId, int $accessRight, string $tag): int
    {
        return $this->grantTopologyPages($connection, $contactId, self::TEMPLATES_TOPOLOGY_PAGES, $accessRight, $tag);
    }

    /**
     * Returns the access group id (reusable for row-level scoping).
     *
     * @param int $accessRight CentreonACL access right: 1 = read-write, 2 = read-only
     * @param string $tag unique suffix for the ACL rows of this test
     */
    private function grantHostTopologyRole(Connection $connection, int $contactId, int $accessRight, string $tag): int
    {
        return $this->grantTopologyPages($connection, $contactId, self::HOSTS_TOPOLOGY_PAGES, $accessRight, $tag);
    }

    /**
     * Logs in a new non-admin user holding read-write on host templates and no other topology role.
     *
     * @return int the access group id
     */
    private function loginAsHostTemplateReadWriteUser(Connection $connection, string $tag): int
    {
        $username = bin2hex(random_bytes(8));
        $aclGroupId = $this->grantHostTemplateTopologyRole($connection, $this->createNonAdminContact($connection, $username), self::ACL_ACCESS_READ_WRITE, $tag);
        $this->login($username);

        return $aclGroupId;
    }
}
