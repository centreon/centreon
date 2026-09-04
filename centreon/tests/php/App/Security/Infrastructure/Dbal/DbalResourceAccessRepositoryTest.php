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

namespace Tests\App\Security\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalResourceAccessRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    // Constructed directly rather than fetched from the container: it keeps the ACL fixtures
    // this test builds (acl_groups / acl_resources / relations) fully isolated from whatever
    // topology/ACL state other integration tests may leave around the same connection.
    private DbalResourceAccessRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->repository = new DbalResourceAccessRepository($this->connection);

        $this->connection->insert('hostgroup', ['hg_id' => 201, 'hg_name' => 'HostGroup-201']);
        $this->connection->insert('hostgroup', ['hg_id' => 202, 'hg_name' => 'HostGroup-202']);
    }

    public function testUserWithNoAclGroupHasAccessToNoHostGroups(): void
    {
        $userId = new UserId($this->createContact('user-no-acl'));

        $accessibleHostGroupIds = $this->repository->findAccessibleHostGroupIds($userId);

        self::assertNotNull($accessibleHostGroupIds);
        self::assertCount(0, $accessibleHostGroupIds);
    }

    public function testUserWithAclResourceCarryingNoRelationAndNoAllFlagHasAccessToNoHostGroups(): void
    {
        // A resource that never had its host-group tab configured (no relation rows, flag unset)
        // grants zero host groups — it must not be mistaken for the "all host groups" flag.
        $contactId = $this->createContact('user-empty-resource');
        $this->linkContactToAclResource($contactId, restrictToHostGroupIds: [], allHostGroups: false);

        $userId = new UserId($contactId);

        $accessibleHostGroupIds = $this->repository->findAccessibleHostGroupIds($userId);

        self::assertNotNull($accessibleHostGroupIds);
        self::assertCount(0, $accessibleHostGroupIds);
    }

    public function testUserWithAclResourceFlaggedAllHostGroupsHasAccessToAllHostGroups(): void
    {
        $contactId = $this->createContact('user-all-flag');
        $this->linkContactToAclResource($contactId, restrictToHostGroupIds: [], allHostGroups: true);

        $userId = new UserId($contactId);

        self::assertNull($this->repository->findAccessibleHostGroupIds($userId));
    }

    public function testUserWithAclResourceRestrictedToASingleHostGroupSeesOnlyThatHostGroup(): void
    {
        $contactId = $this->createContact('user-restricted');
        $this->linkContactToAclResource($contactId, restrictToHostGroupIds: [201], allHostGroups: false);

        $userId = new UserId($contactId);

        $accessibleHostGroupIds = $this->repository->findAccessibleHostGroupIds($userId);
        self::assertNotNull($accessibleHostGroupIds);
        self::assertEquals([201], array_map(static fn (HostGroupId $id): int => $id->value, iterator_to_array($accessibleHostGroupIds)));
    }

    /**
     * Mirrors legacy's OR-across-resources semantics for the "all host groups" flag: a single
     * accessible resource with the flag set grants everything, even when another accessible
     * resource restricts to a specific host group.
     */
    public function testUserWithOneRestrictedAndOneAllFlagResourceHasAccessToAllHostGroups(): void
    {
        $contactId = $this->createContact('user-mixed-resources');
        $this->linkContactToAclResource($contactId, restrictToHostGroupIds: [201], allHostGroups: false);
        $this->linkContactToAclResource($contactId, restrictToHostGroupIds: [], allHostGroups: true);

        $userId = new UserId($contactId);

        self::assertNull($this->repository->findAccessibleHostGroupIds($userId));
    }

    private function createContact(string $alias): int
    {
        $this->connection->insert('contact', [
            'contact_name' => $alias,
            'contact_alias' => $alias,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $alias . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<int> $restrictToHostGroupIds host groups explicitly granted through this
     *                                          resource's relations; irrelevant once $allHostGroups is true
     */
    private function linkContactToAclResource(int $contactId, array $restrictToHostGroupIds, bool $allHostGroups): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'group-' . $contactId . '-' . random_int(1, PHP_INT_MAX),
            'acl_group_alias' => 'group-' . $contactId . '-' . random_int(1, PHP_INT_MAX),
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'resource-' . $aclGroupId,
            'acl_res_alias' => 'resource-' . $aclGroupId,
            'acl_res_activate' => '1',
            'all_hostgroups' => $allHostGroups ? '1' : '0',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($restrictToHostGroupIds as $hostGroupId) {
            $this->connection->insert('acl_resources_hg_relations', [
                'acl_res_id' => $aclResId,
                'hg_hg_id' => $hostGroupId,
            ]);
        }
    }
}
