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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalResourceAccessRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private Connection $realTimeConnection;

    // Constructed directly rather than fetched from the container: it keeps the ACL fixtures
    // this test builds (acl_groups / acl_resources / relations) fully isolated from whatever
    // topology/ACL state other integration tests may leave around the same connection.
    private DbalResourceAccessRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;

        $this->repository = new DbalResourceAccessRepository($this->connection, $realTimeConnection);

        $this->connection->insert('nagios_server', ['id' => 101, 'name' => 'Poller-101', 'localhost' => '0', 'ns_activate' => '1', 'ns_ip_address' => '10.0.0.101', 'uid' => 200000000000101]);
        $this->connection->insert('nagios_server', ['id' => 102, 'name' => 'Poller-102', 'localhost' => '0', 'ns_activate' => '1', 'ns_ip_address' => '10.0.0.102', 'uid' => 200000000000102]);
        $this->connection->insert('hostgroup', ['hg_id' => 201, 'hg_name' => 'HostGroup-201']);
        $this->connection->insert('hostgroup', ['hg_id' => 202, 'hg_name' => 'HostGroup-202']);
    }

    public function testGrantResourceAccessSeedsCentreonAclForEachGivenGroup(): void
    {
        $host = $this->buildHost(9001);

        $this->repository->grantResourceAccess($host, new Collection([new AccessGroupId(10), new AccessGroupId(20)], AccessGroupId::class));

        /** @var list<array{group_id: int|string, host_id: int|string, service_id: int|string|null}> $rows */
        $rows = $this->realTimeConnection->fetchAllAssociative(
            'SELECT group_id, host_id, service_id FROM centreon_acl WHERE host_id = ? ORDER BY group_id',
            [9001],
        );

        self::assertCount(2, $rows);
        self::assertSame(10, (int) $rows[0]['group_id']);
        self::assertNull($rows[0]['service_id']);
        self::assertSame(20, (int) $rows[1]['group_id']);
    }

    public function testFlagAllResourcesAsChangedFlagsEveryAclResource(): void
    {
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r1', 'acl_res_alias' => 'r1', 'acl_res_activate' => '1', 'changed' => '0']);
        $firstId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r2', 'acl_res_alias' => 'r2', 'acl_res_activate' => '1', 'changed' => '0']);
        $secondId = (int) $this->connection->lastInsertId();

        $this->repository->flagAllResourcesAsChanged();

        /** @var int|string $firstChanged */
        $firstChanged = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$firstId]);
        /** @var int|string $secondChanged */
        $secondChanged = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$secondId]);

        self::assertSame(1, (int) $firstChanged);
        self::assertSame(1, (int) $secondChanged);
    }

    public function testUserWithNoAclGroupSeesNoHostSeverity(): void
    {
        // Legacy `if ($accessGroups === []) return []`: a user with no accessible ACL resource is
        // fully restricted (fails closed), returning an empty Collection rather than the null "see all".
        $userId = new UserId($this->createContact('user-no-acl-hs'));

        $accessibleSeverities = $this->repository->findAccessibleHostSeverityIds($userId);

        self::assertNotNull($accessibleSeverities);
        self::assertSame([], $accessibleSeverities->toArray());
    }

    public function testUserWithAclResourceHavingNoHostSeverityRestrictionReturnsNull(): void
    {
        $contactId = $this->createContact('user-unrestricted-hs');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: []);

        self::assertNull($this->repository->findAccessibleHostSeverityIds(new UserId($contactId)));
    }

    public function testUserWithAclResourceGrantingOnlyRegularCategoriesSeesNoHostSeverity(): void
    {
        // Legacy pivot: hasRestrictedAccessToHostCategories() is true as soon as the ACL carries any
        // host-category relation (regardless of level), and the level-scoped join then surfaces no
        // severity for a resource granting only regular (levelless) categories — so the user sees
        // nothing. The empty Collection (not null) preserves that fail-closed behaviour.
        $regularCategory = $this->insertHostCategory('HC-regular-only');

        $contactId = $this->createContact('user-regular-only-hs');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: [$regularCategory]);

        $accessibleSeverities = $this->repository->findAccessibleHostSeverityIds(new UserId($contactId));

        self::assertNotNull($accessibleSeverities, 'A resource granting only regular categories must not grant access to every severity.');
        self::assertSame([], $accessibleSeverities->toArray());
    }

    public function testUserWithAclResourceRestrictedToHostSeveritiesReturnsOnlySeverityIds(): void
    {
        $severityA = $this->insertHostSeverity('HS-A');
        $severityB = $this->insertHostSeverity('HS-B');
        $this->insertHostSeverity('HS-C'); // not linked to the user
        // a levelless (regular) category granted through the same resource must be filtered out
        $regularCategory = $this->insertHostCategory('HC-regular');

        $contactId = $this->createContact('user-restricted-hs');
        $this->linkContactToHostSeverityAclResource(
            $contactId,
            restrictToHostSeverityIds: [$severityA, $severityB, $regularCategory]
        );

        $accessibleSeverities = $this->repository->findAccessibleHostSeverityIds(new UserId($contactId));

        self::assertNotNull($accessibleSeverities);
        $accessibleIds = array_map(
            static fn (HostSeverityId $hostSeverityId): int => $hostSeverityId->value,
            $accessibleSeverities->toArray()
        );
        self::assertEqualsCanonicalizing([$severityA, $severityB], $accessibleIds);
    }

    public function testUserWithOneRestrictedAndOneUnrelatedResourceStaysRestrictedToTheUnion(): void
    {
        // Regression for the mixed-resource over-exposure class of bug (see PR #11523): a second
        // accessible resource that carries no host-severity relation must NOT widen access to "all".
        // The accessible set is the union across every resource; unrestricted only when zero anywhere.
        $severityA = $this->insertHostSeverity('HS-A');

        $contactId = $this->createContact('user-mixed-hs');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: [$severityA]);
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: []);

        $accessibleSeverities = $this->repository->findAccessibleHostSeverityIds(new UserId($contactId));

        self::assertNotNull($accessibleSeverities, 'A resource with no host-severity relation must not grant access to every severity.');
        self::assertEqualsCanonicalizing(
            [$severityA],
            array_map(static fn (HostSeverityId $id): int => $id->value, $accessibleSeverities->toArray())
        );
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

    public function testUserWithNoAclGroupSeesNoHostCategory(): void
    {
        // Tier 1 fail-closed: a user with no accessible ACL resource is fully restricted, returning
        // an empty Collection ("sees nothing"), not the null "see all".
        $userId = new UserId($this->createContact('user-no-acl-hc'));

        $accessibleCategories = $this->repository->findAccessibleHostCategoryIds($userId);

        self::assertNotNull($accessibleCategories);
        self::assertSame([], $accessibleCategories->toArray());
    }

    public function testUserWithAclResourceHavingNoHostCategoryRestrictionReturnsNull(): void
    {
        $contactId = $this->createContact('user-unrestricted-hc');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: []);

        self::assertNull($this->repository->findAccessibleHostCategoryIds(new UserId($contactId)));
    }

    public function testUserWithAclResourceRestrictedToHostCategoriesReturnsOnlyCategoryIds(): void
    {
        $categoryA = $this->insertHostCategory('HC-A');
        $categoryB = $this->insertHostCategory('HC-B');
        $this->insertHostCategory('HC-C'); // not linked to the user
        // a severity (levelled category) granted through the same resource must be filtered out
        $severity = $this->insertHostSeverity('HS-mixed');

        $contactId = $this->createContact('user-restricted-hc');
        $this->linkContactToHostSeverityAclResource(
            $contactId,
            restrictToHostSeverityIds: [$categoryA, $categoryB, $severity]
        );

        $accessibleCategories = $this->repository->findAccessibleHostCategoryIds(new UserId($contactId));

        self::assertNotNull($accessibleCategories);
        $accessibleIds = array_map(
            static fn (HostCategoryId $hostCategoryId): int => $hostCategoryId->value,
            $accessibleCategories->toArray()
        );
        self::assertEqualsCanonicalizing([$categoryA, $categoryB], $accessibleIds);
    }

    public function testUserGrantedOnlyHostSeveritiesIsRestrictedToNoHostCategory(): void
    {
        // the user IS restricted (a host-category ACL relation exists) but it points only at a
        // severity, so no regular category is accessible: an EMPTY (non-null) collection, not "see all".
        $severity = $this->insertHostSeverity('HS-only');

        $contactId = $this->createContact('user-severity-only');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: [$severity]);

        $accessibleCategories = $this->repository->findAccessibleHostCategoryIds(new UserId($contactId));

        self::assertNotNull($accessibleCategories, 'A severity-only grant is a restriction, not "see all".');
        self::assertSame([], $accessibleCategories->toArray());
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

    private function buildHost(int $id): Host
    {
        $host = new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($host, new HostId($id));

        return $host;
    }

    private function insertHostSeverity(string $name): int
    {
        // a host category carrying a level is a host "severity"
        return $this->insertHostCategory($name, level: 1);
    }

    private function insertHostCategory(string $name, ?int $level = null): int
    {
        $this->connection->insert('hostcategories', [
            'hc_name' => $name,
            'hc_activate' => '1',
            'level' => $level,
        ]);

        return (int) $this->connection->lastInsertId();
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
     * @param list<int> $restrictToHostSeverityIds hc_ids granted through this resource's relations
     *                                             (the shared acl_resources_hc_relations table); empty
     *                                             means the resource carries no host-severity restriction
     */
    private function linkContactToHostSeverityAclResource(int $contactId, array $restrictToHostSeverityIds): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'hs-group-' . $contactId . '-' . random_int(1, PHP_INT_MAX),
            'acl_group_alias' => 'hs-group-' . $contactId . '-' . random_int(1, PHP_INT_MAX),
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'hs-resource-' . $aclGroupId,
            'acl_res_alias' => 'hs-resource-' . $aclGroupId,
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($restrictToHostSeverityIds as $hostCategoryId) {
            $this->connection->insert('acl_resources_hc_relations', [
                'acl_res_id' => $aclResId,
                'hc_id' => $hostCategoryId,
            ]);
        }
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
