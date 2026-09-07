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
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
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

        $this->connection->insert('nagios_server', ['id' => 101, 'name' => 'Poller-101', 'localhost' => '0', 'ns_activate' => '1', 'ns_ip_address' => '10.0.0.101', 'uid' => 200000000000101]);
        $this->connection->insert('nagios_server', ['id' => 102, 'name' => 'Poller-102', 'localhost' => '0', 'ns_activate' => '1', 'ns_ip_address' => '10.0.0.102', 'uid' => 200000000000102]);
        $this->connection->insert('hostgroup', ['hg_id' => 201, 'hg_name' => 'HostGroup-201']);
        $this->connection->insert('hostgroup', ['hg_id' => 202, 'hg_name' => 'HostGroup-202']);
    }

    public function testUserWithNoAclGroupHasAccessToAllPollers(): void
    {
        $userId = new UserId($this->createContact('user-no-acl'));

        self::assertTrue($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(101), $userId));
        self::assertNull($this->repository->findAccessiblePollerIds($userId));
    }

    public function testUserWithAclResourceHavingNoPollerRestrictionHasAccessToAllPollers(): void
    {
        $contactId = $this->createContact('user-unrestricted');
        $this->linkContactToAclResourceForPollers($contactId, restrictToPollerIds: []);

        $userId = new UserId($contactId);

        self::assertTrue($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(102), $userId));
        self::assertNull($this->repository->findAccessiblePollerIds($userId));
    }

    public function testUserWithAclResourceRestrictedToASinglePollerSeesOnlyThatPoller(): void
    {
        $contactId = $this->createContact('user-restricted');
        $this->linkContactToAclResourceForPollers($contactId, restrictToPollerIds: [101]);

        $userId = new UserId($contactId);

        self::assertFalse($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(101), $userId));
        self::assertFalse($this->repository->hasAccessToPoller(new PollerId(102), $userId));

        $accessiblePollerIds = $this->repository->findAccessiblePollerIds($userId);
        self::assertNotNull($accessiblePollerIds);
        self::assertEquals([101], array_map(static fn (PollerId $id): int => $id->value, iterator_to_array($accessiblePollerIds)));
    }

    /**
     * Mirrors centreonACL::setPollers(): the accessible poller set is the union of poller
     * relations across every accessible ACL resource. A resource that carries no poller
     * relation contributes nothing to that union and must not, on its own, grant access to
     * every poller — only an empty union (no accessible resource restricts pollers at all)
     * does.
     */
    public function testUserWithOneRestrictedAndOneUnrestrictedResourceSeesOnlyTheRestrictedPoller(): void
    {
        $contactId = $this->createContact('user-mixed-resources');
        $this->linkContactToAclResourceForPollers($contactId, restrictToPollerIds: [101]);
        $this->linkContactToAclResourceForPollers($contactId, restrictToPollerIds: []);

        $userId = new UserId($contactId);

        self::assertFalse($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(101), $userId));
        self::assertFalse($this->repository->hasAccessToPoller(new PollerId(102), $userId));

        $accessiblePollerIds = $this->repository->findAccessiblePollerIds($userId);
        self::assertNotNull($accessiblePollerIds);
        self::assertEquals([101], array_map(static fn (PollerId $id): int => $id->value, iterator_to_array($accessiblePollerIds)));
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
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [], allHostGroups: false);

        $userId = new UserId($contactId);

        $accessibleHostGroupIds = $this->repository->findAccessibleHostGroupIds($userId);

        self::assertNotNull($accessibleHostGroupIds);
        self::assertCount(0, $accessibleHostGroupIds);
    }

    public function testUserWithAclResourceFlaggedAllHostGroupsHasAccessToAllHostGroups(): void
    {
        $contactId = $this->createContact('user-all-flag');
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [], allHostGroups: true);

        $userId = new UserId($contactId);

        self::assertNull($this->repository->findAccessibleHostGroupIds($userId));
    }

    public function testUserWithAclResourceRestrictedToASingleHostGroupSeesOnlyThatHostGroup(): void
    {
        $contactId = $this->createContact('user-restricted');
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [201], allHostGroups: false);

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
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [201], allHostGroups: false);
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [], allHostGroups: true);

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
     * @param list<int> $restrictToPollerIds empty means the ACL resource carries no poller restriction at all
     */
    private function linkContactToAclResourceForPollers(int $contactId, array $restrictToPollerIds): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'group-' . $contactId,
            'acl_group_alias' => 'group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'resource-' . $contactId,
            'acl_res_alias' => 'resource-' . $contactId,
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($restrictToPollerIds as $pollerId) {
            $this->connection->insert('acl_resources_poller_relations', [
                'acl_res_id' => $aclResId,
                'poller_id' => $pollerId,
            ]);
        }
    }

    /**
     * @param list<int> $restrictToHostGroupIds host groups explicitly granted through this
     *                                          resource's relations; irrelevant once $allHostGroups is true
     */
    private function linkContactToAclResourceForHostGroups(int $contactId, array $restrictToHostGroupIds, bool $allHostGroups): void
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
