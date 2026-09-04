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
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
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
            ->leftJoin('accessible_res', 'acl_resources_poller_relations', 'arpr', 'arpr.acl_res_id = accessible_res.acl_res_id')
            ->where('arpr.acl_res_id IS NULL')
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        return (bool) $this->connection->fetchOne($qb->getSQL(), ['contactId' => $userId->value]);
    }

    public function hasAccessToPoller(PollerId $pollerId, UserId $userId): bool
    {
        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->leftJoin('accessible_res', 'acl_resources_poller_relations', 'arpr', 'arpr.acl_res_id = accessible_res.acl_res_id')
            ->where(
                $qb->expr()->or(
                    'arpr.acl_res_id IS NULL',
                    'arpr.poller_id = :pollerId'
                )
            )
            ->setParameter('contactId', $userId->value)
            ->setParameter('pollerId', $pollerId->value)
            ->setMaxResults(1);

        return (bool) $this->connection->fetchOne($qb->getSQL(), [
            'contactId' => $userId->value,
            'pollerId' => $pollerId->value,
        ]);
    }

    public function findAccessibleHostGroupIds(UserId $userId): ?array
    {
        $accessibleAclResQb = $this->getAccessibleAclResourcesQueryBuilder();

        // No ACL resources means no restrictions apply — the user has access to all host groups.
        if (! $this->connection->fetchOne($accessibleAclResQb->getSQL(), ['contactId' => $userId->value])) {
            return null;
        }

        $unrestrictedQb = $this->connection->createQueryBuilder();
        $unrestrictedQb
            ->select('1')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->leftJoin('accessible_res', 'acl_resources_hg_relations', 'arhr', 'arhr.acl_res_id = accessible_res.acl_res_id')
            ->where('arhr.acl_res_id IS NULL')
            ->setParameter('contactId', $userId->value)
            ->setMaxResults(1);

        // At least one accessible ACL resource carries no host group restriction at all — access to all host groups.
        if ($this->connection->fetchOne($unrestrictedQb->getSQL(), ['contactId' => $userId->value])) {
            return null;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT arhr.hg_hg_id')
            ->from('(' . $accessibleAclResQb->getSQL() . ')', 'accessible_res')
            ->innerJoin('accessible_res', 'acl_resources_hg_relations', 'arhr', 'arhr.acl_res_id = accessible_res.acl_res_id')
            ->setParameter('contactId', $userId->value);

        /** @var list<array{hg_hg_id: numeric-string}> $rows */
        $rows = $this->connection->fetchAllAssociative($qb->getSQL(), ['contactId' => $userId->value]);

        return array_map(static fn (array $row): HostGroupId => new HostGroupId((int) $row['hg_hg_id']), $rows);
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
