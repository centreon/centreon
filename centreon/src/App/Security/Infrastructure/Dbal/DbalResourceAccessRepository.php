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

namespace App\Security\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectoryId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\AccessGroupRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalResourceAccessRepository implements ResourceAccessRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $realTimeConnection,
        private AccessGroupRepository $accessGroupRepository,
    ) {
    }

    /**
     * @param AggregateRoot<AggregateRootId>&AclScopedInterface $resource
     * @param Collection<AccessGroupId> $accessGroupIds
     */
    public function grantResourceAccess(AggregateRoot&AclScopedInterface $resource, Collection $accessGroupIds): void
    {
        // Only Host implements AclScopedInterface today — extend this match when a second
        // ACL-scoped resource type needs the same bookkeeping (see AclScopedInterface).
        if (! $resource instanceof Host) {
            throw new \LogicException(sprintf('No ACL grant mapping for aggregate %s.', $resource::class));
        }

        $hostId = $resource->id()->value;
        foreach ($accessGroupIds as $accessGroupId) {
            $this->realTimeConnection->executeStatement(
                'INSERT INTO centreon_acl (group_id, host_id, service_id) VALUES (:groupId, :hostId, NULL)',
                ['groupId' => $accessGroupId->value, 'hostId' => $hostId],
            );
        }
    }

    public function flagAllResourcesAsChanged(): void
    {
        $this->connection->executeStatement('UPDATE acl_resources SET changed = 1');
    }

    public function hasAccessToAllPollers(UserId $userId): bool
    {
        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        // No ACL resources means no restrictions apply — the user has access to all pollers.
        if (! $this->connection->fetchOne($accessibleAclResQb->getSQL(), ['contactId' => $userId->value])) {
            return true;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_poller_relations', 'arpr', 'arpr.acl_res_id = accessible_res.acl_res_id')
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        // Legacy (centreonACL::setPollers()) unions the poller relations across every accessible
        // resource and only falls back to "all pollers" when that union is entirely empty — not
        // as soon as a single resource happens to carry no relation. A user holding one resource
        // restricted to a poller and another resource with no poller relation at all must still
        // see only the restricted poller, not everything.
        return ! (bool) $this->connection->fetchOne($qb->getSQL(), ['contactId' => $userId->value]);
    }

    public function hasAccessToPoller(PollerId $pollerId, UserId $userId): bool
    {
        if ($this->hasAccessToAllPollers($userId)) {
            return true;
        }

        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_poller_relations', 'arpr', 'arpr.acl_res_id = accessible_res.acl_res_id')
            ->where('arpr.poller_id = :pollerId')
            ->setParameter('contactId', $userId->value)
            ->setParameter('pollerId', $pollerId->value)
            ->setMaxResults(1);

        return (bool) $this->connection->fetchOne($qb->getSQL(), [
            'contactId' => $userId->value,
            'pollerId' => $pollerId->value,
        ]);
    }

    public function findAccessibleHostCategoryIds(UserId $userId): ?Collection
    {
        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        // Tier 1 — legacy `if ($accessGroups === []) return []`: a user with no accessible ACL resource
        // is fully restricted (fails closed), not unrestricted. Returning an empty Collection keeps the
        // "sees nothing" contract distinct from the "sees everything" null below.
        $hasAccessibleResourceQb = $this->connection->createQueryBuilder();
        $hasAccessibleResourceQb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        if ($this->connection->fetchOne($hasAccessibleResourceQb->getSQL(), ['contactId' => $userId->value]) === false) {
            return new Collection([], HostCategoryId::class);
        }

        // Tier 2 — legacy `hasRestrictedAccessToHostCategories() === false`: accessible resources exist
        // but none carry a host-category ACL relation (of any level), so no category restriction is in
        // force and the user sees every category.
        $hasHostCategoryRelationQb = $this->connection->createQueryBuilder();
        $hasHostCategoryRelationQb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_hc_relations', 'arhcr', 'arhcr.acl_res_id = accessible_res.acl_res_id')
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        if ($this->connection->fetchOne($hasHostCategoryRelationQb->getSQL(), ['contactId' => $userId->value]) === false) {
            return null;
        }

        // Tier 3 — legacy `AND hc.hc_id IN (<granted categories>)` on a `hc.level IS NULL` join: the user
        // is restricted to the regular (levelless) categories their accessible resources grant. A levelless
        // host category is a regular category; one carrying a level is a host "severity", a distinct
        // concept. A user granted only severities yields an empty set and therefore sees no category,
        // matching legacy findAllByAccessGroupIds, which filters level IS NULL over the grant.
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT arhcr.hc_id')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_hc_relations', 'arhcr', 'arhcr.acl_res_id = accessible_res.acl_res_id')
            ->innerJoin('arhcr', 'hostcategories', 'hc', 'hc.hc_id = arhcr.hc_id')
            ->where('hc.level IS NULL')
            ->setParameter('contactId', $userId->value);

        /** @var list<array{hc_id: numeric-string}> $rows */
        $rows = $this->connection->fetchAllAssociative($qb->getSQL(), ['contactId' => $userId->value]);

        return new Collection(
            array_map(static fn (array $row): HostCategoryId => new HostCategoryId((int) $row['hc_id']), $rows),
            HostCategoryId::class,
        );
    }

    public function findAccessibleHostSeverityIds(UserId $userId): ?Collection
    {
        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        // Tier 1 — legacy `if ($accessGroups === []) return []`: a user with no accessible ACL resource
        // is fully restricted (fails closed), not unrestricted. Returning an empty Collection keeps the
        // "sees nothing" contract distinct from the "sees everything" null below.
        $hasAccessibleResourceQb = $this->connection->createQueryBuilder();
        $hasAccessibleResourceQb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        if ($this->connection->fetchOne($hasAccessibleResourceQb->getSQL(), ['contactId' => $userId->value]) === false) {
            return new Collection([], HostSeverityId::class);
        }

        // Tier 2 — legacy `hasRestrictedAccessToHostCategories() === false`: accessible resources exist
        // but none carry a host-category ACL relation (of any level), so no category restriction is in
        // force and the user sees host templates of any severity.
        $hasHostCategoryRelationQb = $this->connection->createQueryBuilder();
        $hasHostCategoryRelationQb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_hc_relations', 'arhcr', 'arhcr.acl_res_id = accessible_res.acl_res_id')
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        if ($this->connection->fetchOne($hasHostCategoryRelationQb->getSQL(), ['contactId' => $userId->value]) === false) {
            return null;
        }

        // Tiers 3 & 4 — legacy `AND hc.hc_id IN (<granted categories>)` on a `hc.level IS NOT NULL`
        // join: the user is restricted to the host severities (level-bearing categories) their
        // accessible resources grant. A user granted only regular (levelless) categories yields an
        // empty set and therefore sees nothing, matching legacy — which never surfaces a severity-less
        // template once a category restriction is in force.
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT arhcr.hc_id')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_hc_relations', 'arhcr', 'arhcr.acl_res_id = accessible_res.acl_res_id')
            ->innerJoin('arhcr', 'hostcategories', 'hc', 'hc.hc_id = arhcr.hc_id')
            ->where('hc.level IS NOT NULL')
            ->setParameter('contactId', $userId->value);

        /** @var list<array{hc_id: numeric-string}> $rows */
        $rows = $this->connection->fetchAllAssociative($qb->getSQL(), ['contactId' => $userId->value]);

        return new Collection(
            array_map(static fn (array $row): HostSeverityId => new HostSeverityId((int) $row['hc_id']), $rows),
            HostSeverityId::class,
        );
    }

    public function findAccessiblePollerIds(UserId $userId): ?Collection
    {
        if ($this->hasAccessToAllPollers($userId)) {
            return null;
        }

        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT arpr.poller_id')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_poller_relations', 'arpr', 'arpr.acl_res_id = accessible_res.acl_res_id')
            ->setParameter('contactId', $userId->value);

        /** @var list<array{poller_id: numeric-string}> $rows */
        $rows = $this->connection->fetchAllAssociative($qb->getSQL(), ['contactId' => $userId->value]);

        return new Collection(
            array_map(static fn (array $row): PollerId => new PollerId((int) $row['poller_id']), $rows),
            PollerId::class,
        );
    }

    public function findAccessibleHostGroupIds(UserId $userId): ?Collection
    {
        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        // Unlike pollers, host groups carry an explicit "all host groups" flag on the ACL resource
        // itself (acl_resources.all_hostgroups). A resource simply carrying no host-group relation
        // row is NOT the same thing as this flag — it means that resource grants zero host groups,
        // not every host group. Legacy (HostGroupRepositoryTrait::hasAccessToAllHostGroups()) only
        // treats the user as unrestricted when at least one accessible resource has the flag set.
        $allHostGroupsQb = $this->connection->createQueryBuilder();
        $allHostGroupsQb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources', 'res', "res.acl_res_id = accessible_res.acl_res_id AND res.all_hostgroups = '1'")
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        if ($this->connection->fetchOne($allHostGroupsQb->getSQL(), ['contactId' => $userId->value]) !== false) {
            return null;
        }

        // No "all host groups" flag anywhere: the accessible set is the union of host-group
        // relations across every accessible resource (legacy findAllByAccessGroupIds()). This is
        // naturally empty — not "everything" — for a user with no accessible resource at all, or
        // whose accessible resources carry no host-group relation.
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT arhr.hg_hg_id')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_hg_relations', 'arhr', 'arhr.acl_res_id = accessible_res.acl_res_id')
            ->setParameter('contactId', $userId->value);

        /** @var list<array{hg_hg_id: numeric-string}> $rows */
        $rows = $this->connection->fetchAllAssociative($qb->getSQL(), ['contactId' => $userId->value]);

        return new Collection(
            array_map(static fn (array $row): HostGroupId => new HostGroupId((int) $row['hg_hg_id']), $rows),
            HostGroupId::class,
        );
    }

    public function findAccessibleImageFolderIds(UserId $userId): ?Collection
    {
        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        // Unlike pollers, image folders carry an explicit "all image folders" flag on the ACL
        // resource itself (acl_resources.all_image_folders). A resource simply carrying no
        // image-folder relation row is NOT the same thing as this flag — it means that resource
        // grants zero folders, not every folder. Legacy (DbReadImageFolderRepository::
        // hasAccessToAllImageFolders()) only treats the user as unrestricted when at least one
        // accessible resource has the flag set.
        $allImageFoldersQb = $this->connection->createQueryBuilder();
        $allImageFoldersQb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources', 'res', "res.acl_res_id = accessible_res.acl_res_id AND res.all_image_folders = '1'")
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        if ($this->connection->fetchOne($allImageFoldersQb->getSQL(), ['contactId' => $userId->value]) !== false) {
            return null;
        }

        // No "all image folders" flag anywhere: the accessible set is the union of image-folder
        // relations across every accessible resource (legacy findByRequestParametersAndAccessGroups()).
        // This is naturally empty — not "everything" — for a user with no accessible resource at
        // all, or whose accessible resources carry no image-folder relation.
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT arifr.dir_id')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_image_folder_relations', 'arifr', 'arifr.acl_res_id = accessible_res.acl_res_id')
            ->setParameter('contactId', $userId->value);

        /** @var list<array{dir_id: numeric-string}> $rows */
        $rows = $this->connection->fetchAllAssociative($qb->getSQL(), ['contactId' => $userId->value]);

        return new Collection(
            array_map(static fn (array $row): MediaDirectoryId => new MediaDirectoryId((int) $row['dir_id']), $rows),
            MediaDirectoryId::class,
        );
    }

    public function findAccessibleContactIds(UserId $userId): Collection
    {
        $accessGroupIds = $this->findActiveAccessGroupIdValues($userId);
        if ($accessGroupIds === []) {
            return new Collection([], NotificationContactId::class);
        }

        // Two bounded queries unioned in PHP rather than one query with an OR across both join
        // paths: the OR variant multiplies the two relation tables together for contacts matching
        // both, which the DISTINCT then has to collapse.
        $contactIds = array_unique([
            ...$this->findContactIdsDirectlyLinkedToAccessGroups($accessGroupIds),
            ...$this->findContactIdsLinkedViaContactGroupToAccessGroups($accessGroupIds),
        ]);

        return new Collection(
            array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), array_values($contactIds)),
            NotificationContactId::class,
        );
    }

    public function findAccessibleContactGroupIds(UserId $userId): Collection
    {
        $accessGroupIds = $this->findActiveAccessGroupIdValues($userId);

        $contactGroupIds = [];
        if ($accessGroupIds !== []) {
            $qb = $this->connection->createQueryBuilder();
            $qb->select('DISTINCT gcgr.cg_cg_id AS id')
                ->from('acl_group_contactgroups_relations', 'gcgr')
                ->where($qb->expr()->in(
                    'gcgr.acl_group_id',
                    $qb->createNamedParameter($accessGroupIds, ArrayParameterType::INTEGER)
                ));

            /** @var list<array{id: int|string}> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();
            foreach ($rows as $row) {
                $contactGroupIds[] = (int) $row['id'];
            }
        }

        // A user always reaches the contact groups they are themselves a member of, even when no
        // Access Group grants them any.
        $qb = $this->connection->createQueryBuilder();
        $qb->select('DISTINCT ccr.contactgroup_cg_id AS id')
            ->from('contactgroup_contact_relation', 'ccr')
            ->innerJoin('ccr', 'contact', 'c', 'c.contact_id = ccr.contact_contact_id')
            ->where('ccr.contact_contact_id = :userId')
            ->andWhere($qb->expr()->eq('c.contact_register', $qb->createNamedParameter('1')))
            ->setParameter('userId', $userId->value);

        /** @var list<array{id: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();
        foreach ($rows as $row) {
            $contactGroupIds[] = (int) $row['id'];
        }

        return new Collection(
            array_map(
                static fn (int $id): ContactGroupId => new ContactGroupId($id),
                array_values(array_unique($contactGroupIds)),
            ),
            ContactGroupId::class,
        );
    }

    /**
     * @return list<int>
     */
    private function findActiveAccessGroupIdValues(UserId $userId): array
    {
        return array_values(array_map(
            static fn (AccessGroupId $id): int => $id->value,
            iterator_to_array($this->accessGroupRepository->findActiveGroupIdsForUser($userId)),
        ));
    }

    /**
     * @param list<int> $accessGroupIds
     *
     * @return list<int>
     */
    private function findContactIdsDirectlyLinkedToAccessGroups(array $accessGroupIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('DISTINCT c.contact_id')
            ->from('contact', 'c')
            ->innerJoin('c', 'acl_group_contacts_relations', 'agcr', 'agcr.contact_contact_id = c.contact_id')
            ->where("c.contact_register = '1'")
            ->andWhere($qb->expr()->in('agcr.acl_group_id', $qb->createNamedParameter($accessGroupIds, ArrayParameterType::INTEGER)));

        /** @var list<array{contact_id: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): int => (int) $row['contact_id'], $rows);
    }

    /**
     * @param list<int> $accessGroupIds
     *
     * @return list<int>
     */
    private function findContactIdsLinkedViaContactGroupToAccessGroups(array $accessGroupIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('DISTINCT c.contact_id')
            ->from('contact', 'c')
            ->innerJoin('c', 'contactgroup_contact_relation', 'ccr', 'ccr.contact_contact_id = c.contact_id')
            ->innerJoin('ccr', 'acl_group_contactgroups_relations', 'agccgr', 'agccgr.cg_cg_id = ccr.contactgroup_cg_id')
            ->where("c.contact_register = '1'")
            ->andWhere($qb->expr()->in('agccgr.acl_group_id', $qb->createNamedParameter($accessGroupIds, ArrayParameterType::INTEGER)));

        /** @var list<array{contact_id: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): int => (int) $row['contact_id'], $rows);
    }

    private function getAccessibleAclResourcesQueryBuilder(): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT ar.acl_res_id')
            ->from('acl_resources', 'ar')
            ->innerJoin('ar', 'acl_res_group_relations', 'argr', 'argr.acl_res_id = ar.acl_res_id')
            ->innerJoin('argr', 'acl_groups', 'ag', "ag.acl_group_id = argr.acl_group_id AND ag.acl_group_activate = '1'")
            ->leftJoin('ag', 'acl_group_contacts_relations', 'agcr', 'agcr.acl_group_id = ag.acl_group_id AND agcr.contact_contact_id = :contactId')
            ->leftJoin('ag', 'acl_group_contactgroups_relations', 'agcgr', 'agcgr.acl_group_id = ag.acl_group_id')
            ->leftJoin('agcgr', 'contactgroup_contact_relation', 'cgcr', 'cgcr.contactgroup_cg_id = agcgr.cg_cg_id AND cgcr.contact_contact_id = :contactId')
            ->where("ar.acl_res_activate = '1'")
            ->andWhere(
                $qb->expr()->or(
                    'agcr.contact_contact_id IS NOT NULL',
                    'cgcr.contact_contact_id IS NOT NULL'
                )
            );

        return $qb;
    }
}
