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

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalResourceAccessRepository implements ResourceAccessRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
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
