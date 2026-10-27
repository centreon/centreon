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
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
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

        $this->repository = new DbalResourceAccessRepository($this->connection, $realTimeConnection, new DbalAccessGroupRepository($this->connection));

        $this->connection->insert('nagios_server', ['id' => 101, 'name' => 'Poller-101', 'localhost' => '0', 'ns_activate' => '1', 'ns_ip_address' => '10.0.0.101', 'uid' => 200000000000101]);
        $this->connection->insert('nagios_server', ['id' => 102, 'name' => 'Poller-102', 'localhost' => '0', 'ns_activate' => '1', 'ns_ip_address' => '10.0.0.102', 'uid' => 200000000000102]);
        $this->connection->insert('hostgroup', ['hg_id' => 201, 'hg_name' => 'HostGroup-201']);
        $this->connection->insert('hostgroup', ['hg_id' => 202, 'hg_name' => 'HostGroup-202']);
        $this->connection->insert('view_img_dir', ['dir_id' => 301, 'dir_name' => 'ImageFolder-301']);
        $this->connection->insert('view_img_dir', ['dir_id' => 302, 'dir_name' => 'ImageFolder-302']);
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
        // fully restricted (fails closed): their scope allows nothing, it is not unrestricted.
        $userId = new UserId($this->createContact('user-no-acl-hs'));

        $severityScope = $this->repository->findHostSeverityAccessScope($userId);

        self::assertFalse($severityScope->isUnrestricted());
        self::assertSame([], $severityScope->allowedIdValues());
    }

    public function testUserWithAclResourceHavingNoHostSeverityRestrictionIsUnrestricted(): void
    {
        $contactId = $this->createContact('user-unrestricted-hs');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: []);

        self::assertTrue($this->repository->findHostSeverityAccessScope(new UserId($contactId))->isUnrestricted());
    }

    public function testUserWithAclResourceGrantingOnlyRegularCategoriesSeesNoHostSeverity(): void
    {
        // Legacy pivot: hasRestrictedAccessToHostCategories() is true as soon as the ACL carries any
        // host-category relation (regardless of level), and the level-scoped join then surfaces no
        // severity for a resource granting only regular (levelless) categories — so the user sees
        // nothing. The scope restricted to nothing preserves that fail-closed behaviour.
        $regularCategory = $this->insertHostCategory('HC-regular-only');

        $contactId = $this->createContact('user-regular-only-hs');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: [$regularCategory]);

        $severityScope = $this->repository->findHostSeverityAccessScope(new UserId($contactId));

        self::assertFalse($severityScope->isUnrestricted(), 'A resource granting only regular categories must not grant access to every severity.');
        self::assertSame([], $severityScope->allowedIdValues());
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

        $severityScope = $this->repository->findHostSeverityAccessScope(new UserId($contactId));

        self::assertFalse($severityScope->isUnrestricted());
        $accessibleIds = $severityScope->allowedIdValues();
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

        $severityScope = $this->repository->findHostSeverityAccessScope(new UserId($contactId));

        self::assertFalse($severityScope->isUnrestricted(), 'A resource with no host-severity relation must not grant access to every severity.');
        self::assertEqualsCanonicalizing(
            [$severityA],
            $severityScope->allowedIdValues()
        );
    }

    public function testUserWithNoAclGroupHasAccessToAllPollers(): void
    {
        $userId = new UserId($this->createContact('user-no-acl'));

        self::assertTrue($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(101), $userId));
        self::assertTrue($this->repository->findPollerAccessScope($userId)->isUnrestricted());
    }

    public function testUserWithAclResourceHavingNoPollerRestrictionHasAccessToAllPollers(): void
    {
        $contactId = $this->createContact('user-unrestricted');
        $this->linkContactToAclResourceForPollers($contactId, restrictToPollerIds: []);

        $userId = new UserId($contactId);

        self::assertTrue($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(102), $userId));
        self::assertTrue($this->repository->findPollerAccessScope($userId)->isUnrestricted());
    }

    public function testUserWithAclResourceRestrictedToASinglePollerSeesOnlyThatPoller(): void
    {
        $contactId = $this->createContact('user-restricted');
        $this->linkContactToAclResourceForPollers($contactId, restrictToPollerIds: [101]);

        $userId = new UserId($contactId);

        self::assertFalse($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(101), $userId));
        self::assertFalse($this->repository->hasAccessToPoller(new PollerId(102), $userId));

        $pollerScope = $this->repository->findPollerAccessScope($userId);
        self::assertFalse($pollerScope->isUnrestricted());
        self::assertEquals([101], $pollerScope->allowedIdValues());
    }

    public function testAPollerRelationWithoutPollerIsIgnored(): void
    {
        // `poller_id` is nullable: a relation left without poller grants nothing, and must not break the lookup.
        $contactId = $this->createContact('user-null-poller');
        $this->linkContactToAclResourceForPollers($contactId, restrictToPollerIds: [101, null]);

        self::assertSame([101], $this->repository->findPollerAccessScope(new UserId($contactId))->allowedIdValues());
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

        $pollerScope = $this->repository->findPollerAccessScope($userId);
        self::assertFalse($pollerScope->isUnrestricted());
        self::assertEquals([101], $pollerScope->allowedIdValues());
    }

    public function testUserWithNoAclGroupSeesNoHostCategory(): void
    {
        // Tier 1 fail-closed: a user with no accessible ACL resource is fully restricted, returning
        // a scope restricted to nothing ("sees nothing"), not an unrestricted one.
        $userId = new UserId($this->createContact('user-no-acl-hc'));

        $categoryScope = $this->repository->findHostCategoryAccessScope($userId);

        self::assertFalse($categoryScope->isUnrestricted());
        self::assertSame([], $categoryScope->allowedIdValues());
    }

    public function testUserWithAclResourceHavingNoHostCategoryRestrictionIsUnrestricted(): void
    {
        $contactId = $this->createContact('user-unrestricted-hc');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: []);

        self::assertTrue($this->repository->findHostCategoryAccessScope(new UserId($contactId))->isUnrestricted());
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

        $categoryScope = $this->repository->findHostCategoryAccessScope(new UserId($contactId));

        self::assertFalse($categoryScope->isUnrestricted());
        $accessibleIds = $categoryScope->allowedIdValues();
        self::assertEqualsCanonicalizing([$categoryA, $categoryB], $accessibleIds);
    }

    public function testUserGrantedOnlyHostSeveritiesIsRestrictedToNoHostCategory(): void
    {
        // the user IS restricted (a host-category ACL relation exists) but it points only at a
        // severity, so no regular category is accessible: a scope restricted to nothing, not an unrestricted one.
        $severity = $this->insertHostSeverity('HS-only');

        $contactId = $this->createContact('user-severity-only');
        $this->linkContactToHostSeverityAclResource($contactId, restrictToHostSeverityIds: [$severity]);

        $categoryScope = $this->repository->findHostCategoryAccessScope(new UserId($contactId));

        self::assertFalse($categoryScope->isUnrestricted(), 'A severity-only grant is a restriction, not "see all".');
        self::assertSame([], $categoryScope->allowedIdValues());
    }

    public function testUserWithNoAclGroupHasAccessToNoHostGroups(): void
    {
        $userId = new UserId($this->createContact('user-no-acl'));

        $hostGroupScope = $this->repository->findHostGroupAccessScope($userId);

        self::assertFalse($hostGroupScope->isUnrestricted());
        self::assertSame([], $hostGroupScope->allowedIdValues());
    }

    public function testUserWithAclResourceCarryingNoRelationAndNoAllFlagHasAccessToNoHostGroups(): void
    {
        // A resource that never had its host-group tab configured (no relation rows, flag unset)
        // grants zero host groups — it must not be mistaken for the "all host groups" flag.
        $contactId = $this->createContact('user-empty-resource');
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [], allHostGroups: false);

        $userId = new UserId($contactId);

        $hostGroupScope = $this->repository->findHostGroupAccessScope($userId);

        self::assertFalse($hostGroupScope->isUnrestricted());
        self::assertSame([], $hostGroupScope->allowedIdValues());
    }

    public function testUserWithAclResourceFlaggedAllHostGroupsHasAccessToAllHostGroups(): void
    {
        $contactId = $this->createContact('user-all-flag');
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [], allHostGroups: true);

        $userId = new UserId($contactId);

        self::assertTrue($this->repository->findHostGroupAccessScope($userId)->isUnrestricted());
    }

    public function testUserWithAclResourceRestrictedToASingleHostGroupSeesOnlyThatHostGroup(): void
    {
        $contactId = $this->createContact('user-restricted');
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [201], allHostGroups: false);

        $userId = new UserId($contactId);

        $hostGroupScope = $this->repository->findHostGroupAccessScope($userId);
        self::assertFalse($hostGroupScope->isUnrestricted());
        self::assertEquals([201], $hostGroupScope->allowedIdValues());
    }

    public function testAHostGroupRelationWithoutHostGroupIsIgnored(): void
    {
        // `hg_hg_id` is nullable: a relation left without host group grants nothing, and must not break the lookup.
        $contactId = $this->createContact('user-null-host-group');
        $this->linkContactToAclResourceForHostGroups($contactId, restrictToHostGroupIds: [201, null], allHostGroups: false);

        self::assertSame([201], $this->repository->findHostGroupAccessScope(new UserId($contactId))->allowedIdValues());
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

        self::assertTrue($this->repository->findHostGroupAccessScope($userId)->isUnrestricted());
    }

    public function testUserWithNoAclGroupHasAccessToNoImageFolders(): void
    {
        // Legacy `if ($accessGroups === []) return new \EmptyIterator()`: a user with no accessible
        // ACL resource is fully restricted (fails closed): their scope allows nothing, it is
        // not unrestricted.
        $userId = new UserId($this->createContact('user-no-acl-media'));

        $folderScope = $this->repository->findImageFolderAccessScope($userId);

        self::assertFalse($folderScope->isUnrestricted());
        self::assertSame([], $folderScope->allowedIdValues());
    }

    public function testUserWithAclResourceCarryingNoRelationAndNoAllFlagHasAccessToNoImageFolders(): void
    {
        $contactId = $this->createContact('user-empty-resource-media');
        $this->linkContactToAclResourceForImageFolders($contactId, restrictToImageFolderIds: [], allImageFolders: false);

        $userId = new UserId($contactId);

        $folderScope = $this->repository->findImageFolderAccessScope($userId);

        self::assertFalse($folderScope->isUnrestricted());
        self::assertSame([], $folderScope->allowedIdValues());
    }

    public function testUserWithAclResourceFlaggedAllImageFoldersHasAccessToAllImageFolders(): void
    {
        $contactId = $this->createContact('user-all-folders-media');
        $this->linkContactToAclResourceForImageFolders($contactId, restrictToImageFolderIds: [], allImageFolders: true);

        $userId = new UserId($contactId);

        self::assertTrue($this->repository->findImageFolderAccessScope($userId)->isUnrestricted());
    }

    public function testUserWithAclResourceRestrictedToASingleImageFolderSeesOnlyThatImageFolder(): void
    {
        $contactId = $this->createContact('user-single-folder-media');
        $this->linkContactToAclResourceForImageFolders($contactId, restrictToImageFolderIds: [301], allImageFolders: false);

        $userId = new UserId($contactId);

        $folderScope = $this->repository->findImageFolderAccessScope($userId);
        self::assertFalse($folderScope->isUnrestricted());
        self::assertEquals([301], $folderScope->allowedIdValues());
    }

    public function testAnImageFolderRelationWithoutFolderIsIgnored(): void
    {
        // `dir_id` is nullable: a relation left without folder grants nothing, and must not break the lookup.
        $contactId = $this->createContact('user-null-folder-media');
        $this->linkContactToAclResourceForImageFolders($contactId, restrictToImageFolderIds: [301, null], allImageFolders: false);

        self::assertSame([301], $this->repository->findImageFolderAccessScope(new UserId($contactId))->allowedIdValues());
    }

    /**
     * Mirrors legacy's OR-across-resources semantics for the "all image folders" flag: a single
     * accessible resource with the flag set grants everything, even when another accessible
     * resource restricts to a specific folder.
     */
    public function testUserWithOneRestrictedAndOneAllFlagResourceHasAccessToAllImageFolders(): void
    {
        $contactId = $this->createContact('user-mixed-resources-media');
        $this->linkContactToAclResourceForImageFolders($contactId, restrictToImageFolderIds: [301], allImageFolders: false);
        $this->linkContactToAclResourceForImageFolders($contactId, restrictToImageFolderIds: [], allImageFolders: true);

        $userId = new UserId($contactId);

        self::assertTrue($this->repository->findImageFolderAccessScope($userId)->isUnrestricted());
    }

    public function testDuplicateHostAccessCopiesTheSourceScopeOntoTheNewHost(): void
    {
        $this->connection->insert('host', ['host_id' => 9101, 'host_name' => 'dup-source', 'host_register' => '1']);
        $this->connection->insert('host', ['host_id' => 9102, 'host_name' => 'dup-copy', 'host_register' => '1']);

        $this->connection->insert('acl_resources', ['acl_res_name' => 'res-dup', 'acl_res_alias' => 'res-dup', 'acl_res_activate' => '1']);

        $aclResId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_resources_host_relations', ['host_host_id' => 9101, 'acl_res_id' => $aclResId]);
        $this->connection->insert('acl_resources_hostex_relations', ['host_host_id' => 9101, 'acl_res_id' => $aclResId]);

        // Real-time cache: two host-level rows, plus one service-level row that must NOT be copied.
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => 10, 'host_id' => 9101, 'service_id' => null]);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => 20, 'host_id' => 9101, 'service_id' => null]);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => 10, 'host_id' => 9101, 'service_id' => 555]);

        $this->repository->duplicateHostAccess(sourceHostId: new HostId(9101), newHostId: new HostId(9102));

        foreach (['acl_resources_host_relations', 'acl_resources_hostex_relations'] as $table) {
            self::assertSame(
                1,
                $this->countRows($this->connection, "SELECT COUNT(*) FROM {$table} WHERE host_host_id = ? AND acl_res_id = ?", [9102, $aclResId]),
                "the copy inherits the source {$table} row",
            );
        }

        /** @var list<array{group_id: int|string, service_id: int|string|null}> $rows */
        $rows = $this->realTimeConnection->fetchAllAssociative(
            'SELECT group_id, service_id FROM centreon_acl WHERE host_id = ? ORDER BY group_id',
            [9102],
        );
        self::assertCount(2, $rows, 'only the host-level rows are copied, never the service-level one');
        self::assertSame(10, (int) $rows[0]['group_id']);
        self::assertNull($rows[0]['service_id']);
        self::assertSame(20, (int) $rows[1]['group_id']);
        self::assertNull($rows[1]['service_id']);
    }

    public function testDuplicateHostAccessWritesNothingWhenTheSourceHasNoAcl(): void
    {
        $this->repository->duplicateHostAccess(sourceHostId: new HostId(9201), newHostId: new HostId(9202));

        self::assertSame(0, $this->countRows($this->connection, 'SELECT COUNT(*) FROM acl_resources_host_relations WHERE host_host_id = ?', [9202]));
        self::assertSame(0, $this->countRows($this->connection, 'SELECT COUNT(*) FROM acl_resources_hostex_relations WHERE host_host_id = ?', [9202]));
        self::assertSame(0, $this->countRows($this->realTimeConnection, 'SELECT COUNT(*) FROM centreon_acl WHERE host_id = ?', [9202]));
    }

    public function testDuplicateHostServiceAccessScopesTheCopysServicesForTheSourcesGroups(): void
    {
        $this->connection->insert('host', ['host_id' => 9301, 'host_name' => 'dup-svc-source', 'host_register' => '1']);
        $this->connection->insert('host', ['host_id' => 9302, 'host_name' => 'dup-svc-copy', 'host_register' => '1']);
        $this->connection->insert('service', ['service_id' => 7701, 'service_description' => 'svc-7701', 'service_register' => '1']);
        $this->connection->insert('service', ['service_id' => 7702, 'service_description' => 'svc-7702', 'service_register' => '1']);
        // The copy already carries its services (cloned/re-linked) before the ACL step runs.
        $this->connection->insert('host_service_relation', ['host_host_id' => 9302, 'service_service_id' => 7701]);
        $this->connection->insert('host_service_relation', ['host_host_id' => 9302, 'service_service_id' => 7702]);
        // The source host is visible to two groups at host level.
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => 10, 'host_id' => 9301, 'service_id' => null]);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => 20, 'host_id' => 9301, 'service_id' => null]);

        $this->repository->duplicateHostServiceAccess(sourceHostId: new HostId(9301), newHostId: new HostId(9302));

        /** @var list<array{group_id: int|string, service_id: int|string}> $rows */
        $rows = $this->realTimeConnection->fetchAllAssociative(
            'SELECT group_id, service_id FROM centreon_acl WHERE host_id = ? AND service_id IS NOT NULL ORDER BY group_id, service_id',
            [9302],
        );

        // Every (source group × copy service) pair is scoped.
        self::assertSame(
            [
                ['group_id' => 10, 'service_id' => 7701],
                ['group_id' => 10, 'service_id' => 7702],
                ['group_id' => 20, 'service_id' => 7701],
                ['group_id' => 20, 'service_id' => 7702],
            ],
            array_map(
                static fn (array $row): array => ['group_id' => (int) $row['group_id'], 'service_id' => (int) $row['service_id']],
                $rows,
            ),
        );
    }

    public function testDuplicateHostServiceAccessWritesNothingWhenTheCopyHasNoService(): void
    {
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => 10, 'host_id' => 9401, 'service_id' => null]);

        $this->repository->duplicateHostServiceAccess(sourceHostId: new HostId(9401), newHostId: new HostId(9402));

        self::assertSame(
            0,
            $this->countRows($this->realTimeConnection, 'SELECT COUNT(*) FROM centreon_acl WHERE host_id = ? AND service_id IS NOT NULL', [9402]),
        );
    }

    public function testDuplicateHostServiceAccessWritesNothingWhenNoGroupSeesTheSource(): void
    {
        $this->connection->insert('host', ['host_id' => 9501, 'host_name' => 'dup-svc-nogroup-source', 'host_register' => '1']);
        $this->connection->insert('host', ['host_id' => 9502, 'host_name' => 'dup-svc-nogroup-copy', 'host_register' => '1']);
        $this->connection->insert('service', ['service_id' => 7801, 'service_description' => 'svc-7801', 'service_register' => '1']);
        // The copy has a service, but no group sees the source (no host-level centreon_acl row).
        $this->connection->insert('host_service_relation', ['host_host_id' => 9502, 'service_service_id' => 7801]);

        $this->repository->duplicateHostServiceAccess(sourceHostId: new HostId(9501), newHostId: new HostId(9502));

        self::assertSame(
            0,
            $this->countRows($this->realTimeConnection, 'SELECT COUNT(*) FROM centreon_acl WHERE host_id = ? AND service_id IS NOT NULL', [9502]),
        );
    }

    public function testDuplicateHostServiceAccessIsIdempotent(): void
    {
        $this->connection->insert('host', ['host_id' => 9601, 'host_name' => 'dup-svc-idem-source', 'host_register' => '1']);
        $this->connection->insert('host', ['host_id' => 9602, 'host_name' => 'dup-svc-idem-copy', 'host_register' => '1']);
        $this->connection->insert('service', ['service_id' => 7901, 'service_description' => 'svc-7901', 'service_register' => '1']);
        $this->connection->insert('host_service_relation', ['host_host_id' => 9602, 'service_service_id' => 7901]);

        $this->realTimeConnection->insert('centreon_acl', ['group_id' => 10, 'host_id' => 9601, 'service_id' => null]);

        // Called twice (e.g. the legacy updateACL('DUP') already wrote the same row): ON DUPLICATE KEY
        // UPDATE makes the second call a no-op rather than a duplicate-key failure.
        $this->repository->duplicateHostServiceAccess(sourceHostId: new HostId(9601), newHostId: new HostId(9602));
        $this->repository->duplicateHostServiceAccess(sourceHostId: new HostId(9601), newHostId: new HostId(9602));

        self::assertSame(
            1,
            $this->countRows($this->realTimeConnection, 'SELECT COUNT(*) FROM centreon_acl WHERE host_id = ? AND service_id = ?', [9602, 7901]),
            'the service ACL row is written once and the second call neither throws nor duplicates it',
        );
    }

    public function testUserWithNoAclGroupHasAccessToNoContact(): void
    {
        $contactId = $this->createContact('no-group-contacts-' . uniqid());

        $accessible = $this->repository->findContactAccessScope(new UserId($contactId));

        // contacts are not ACL resources: there is no "all contacts" flag to fall back on, so a
        // user belonging to no Access Group reaches nothing rather than everything
        self::assertSame([], $accessible->allowedIdValues());
    }

    public function testUserSeesTheContactsAttachedToItsAccessGroupDirectlyOrThroughAContactGroup(): void
    {
        $actorId = $this->createContact('actor-contacts-' . uniqid());
        $aclGroupId = $this->createAccessGroupForContact($actorId);

        $directContactId = $this->createContact('direct-' . uniqid());
        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $directContactId,
        ]);

        $indirectContactId = $this->createContact('indirect-' . uniqid());
        $contactGroupId = $this->createContactGroup('cg-' . uniqid());
        $this->connection->insert('contactgroup_contact_relation', [
            'contactgroup_cg_id' => $contactGroupId,
            'contact_contact_id' => $indirectContactId,
        ]);
        $this->connection->insert('acl_group_contactgroups_relations', [
            'acl_group_id' => $aclGroupId,
            'cg_cg_id' => $contactGroupId,
        ]);

        $unrelatedContactId = $this->createContact('unrelated-' . uniqid());

        // a template contact (contact_register = '0') is not a notification target, even when linked
        $templateContactId = $this->createContact('template-' . uniqid());
        $this->connection->update('contact', ['contact_register' => '0'], ['contact_id' => $templateContactId]);
        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $templateContactId,
        ]);

        $accessibleIds = $this->repository->findContactAccessScope(new UserId($actorId))->allowedIdValues();

        self::assertContains($directContactId, $accessibleIds);
        self::assertContains($indirectContactId, $accessibleIds, 'A contact reached through a contact group of the Access Group counts as accessible.');
        self::assertNotContains($unrelatedContactId, $accessibleIds);
        self::assertNotContains($templateContactId, $accessibleIds);
    }

    public function testUserAlwaysSeesTheContactGroupsItBelongsTo(): void
    {
        $actorId = $this->createContact('actor-cg-' . uniqid());
        $ownContactGroupId = $this->createContactGroup('own-cg-' . uniqid());
        $this->connection->insert('contactgroup_contact_relation', [
            'contactgroup_cg_id' => $ownContactGroupId,
            'contact_contact_id' => $actorId,
        ]);

        // no Access Group at all: own membership is the only path, and it must still apply
        $accessibleIds = $this->repository->findContactGroupAccessScope(new UserId($actorId))->allowedIdValues();

        self::assertSame([$ownContactGroupId], $accessibleIds);
    }

    public function testUserSeesTheContactGroupsGrantedByItsAccessGroup(): void
    {
        $actorId = $this->createContact('actor-granted-cg-' . uniqid());
        $aclGroupId = $this->createAccessGroupForContact($actorId);

        $grantedContactGroupId = $this->createContactGroup('granted-cg-' . uniqid());
        $this->connection->insert('acl_group_contactgroups_relations', [
            'acl_group_id' => $aclGroupId,
            'cg_cg_id' => $grantedContactGroupId,
        ]);
        $this->createContactGroup('unrelated-cg-' . uniqid());

        $accessibleIds = $this->repository->findContactGroupAccessScope(new UserId($actorId))->allowedIdValues();

        self::assertSame([$grantedContactGroupId], $accessibleIds);
    }

    public function testAContactGroupRelationWithoutContactGroupIsIgnored(): void
    {
        // `cg_cg_id` and `contactgroup_cg_id` are nullable: a relation left without contact group grants nothing,
        // and must not break the lookup.
        $actorId = $this->createContact('actor-null-cg-' . uniqid());
        $aclGroupId = $this->createAccessGroupForContact($actorId);

        $grantedContactGroupId = $this->createContactGroup('granted-null-cg-' . uniqid());
        $this->connection->insert('acl_group_contactgroups_relations', ['acl_group_id' => $aclGroupId, 'cg_cg_id' => $grantedContactGroupId]);
        $this->connection->insert('acl_group_contactgroups_relations', ['acl_group_id' => $aclGroupId, 'cg_cg_id' => null]);
        $this->connection->insert('contactgroup_contact_relation', ['contactgroup_cg_id' => null, 'contact_contact_id' => $actorId]);

        self::assertSame([$grantedContactGroupId], $this->repository->findContactGroupAccessScope(new UserId($actorId))->allowedIdValues());
    }

    public function testUserWithNoAclGroupHasAccessToNoHost(): void
    {
        $contactId = $this->createContact('no-group-hosts-' . uniqid());

        // Like contacts, hosts carry no "all hosts" fallback: centreon_acl already resolves every
        // resource rule, so a user belonging to no Access Group reaches nothing.
        self::assertSame([], $this->repository->findHostAccessScope(new UserId($contactId))->allowedIdValues());
    }

    public function testUserSeesTheHostsTheRealTimeAclGrantsToItsAccessGroups(): void
    {
        $actorId = $this->createContact('actor-hosts-' . uniqid());
        $aclGroupId = $this->createAccessGroupForContact($actorId);
        $otherAclGroupId = $this->createAccessGroupForContact($this->createContact('other-hosts-' . uniqid()));

        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => 9101]);
        // A service-level row does not grant the host on its own.
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => 9102, 'service_id' => 1]);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $otherAclGroupId, 'host_id' => 9103]);

        $accessibleIds = $this->repository->findHostAccessScope(new UserId($actorId))->allowedIdValues();

        self::assertSame([9101], $accessibleIds);
    }

    public function testOnlyTheHostsAmongTheGivenOnesAreLookedUp(): void
    {
        $actorId = $this->createContact('actor-among-' . uniqid());
        $aclGroupId = $this->createAccessGroupForContact($actorId);

        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => 9201]);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => 9202]);

        $among = new Collection([new HostId(9201), new HostId(9202), new HostId(9203)], HostId::class);
        $scope = $this->repository->findHostAccessScope(new UserId($actorId), new Collection([new HostId(9202), new HostId(9203)], HostId::class));

        self::assertEquals(new Collection([new HostId(9202)], HostId::class), $scope->filter($among), 'Host 9201 is granted, but was not looked up.');
        self::assertEquals(
            new Collection([], HostId::class),
            $this->repository->findHostAccessScope(new UserId($actorId), new Collection([], HostId::class))->filter($among),
        );
    }

    /**
     * @param list<int|string> $params
     */
    private function countRows(Connection $connection, string $sql, array $params): int
    {
        $count = $connection->fetchOne($sql, $params);
        self::assertIsScalar($count);

        return (int) $count;
    }

    private function createAccessGroupForContact(int $contactId): int
    {
        $name = 'contact-acl-group-' . $contactId . '-' . random_int(1, PHP_INT_MAX);
        $this->connection->insert('acl_groups', [
            'acl_group_name' => $name,
            'acl_group_alias' => $name,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        return $aclGroupId;
    }

    private function createContactGroup(string $name): int
    {
        $this->connection->insert('contactgroup', [
            'cg_name' => $name,
            'cg_alias' => $name,
            'cg_type' => 'local',
            'cg_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
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
     * @param list<int|null> $restrictToPollerIds empty means the ACL resource carries no poller restriction at all
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
     * @param list<int|null> $restrictToHostGroupIds host groups explicitly granted through this
     *                                               resource's relations; irrelevant once $allHostGroups is true
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

    /**
     * @param list<int|null> $restrictToImageFolderIds image folders explicitly granted through this
     *                                                 resource's relations; irrelevant once $allImageFolders is true
     */
    private function linkContactToAclResourceForImageFolders(int $contactId, array $restrictToImageFolderIds, bool $allImageFolders): void
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
            'all_image_folders' => $allImageFolders ? '1' : '0',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($restrictToImageFolderIds as $imageFolderId) {
            $this->connection->insert('acl_resources_image_folder_relations', [
                'acl_res_id' => $aclResId,
                'dir_id' => $imageFolderId,
            ]);
        }
    }
}
