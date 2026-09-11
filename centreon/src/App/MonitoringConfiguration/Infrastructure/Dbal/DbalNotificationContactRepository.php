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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContact;
use App\MonitoringConfiguration\Domain\Repository\Criteria\NotificationContactCriteria;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\AccessGroupRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   id: int,
 *   name: string,
 * }
 */
final readonly class DbalNotificationContactRepository extends DbalRepository implements NotificationContactRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'contact';

    /**
     * @param TransformerInterface<RowTypeAlias, NotificationContact> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: NotificationContactTransformer::class)]
        private TransformerInterface $transformer,

        private AccessGroupRepository $accessGroupRepository,
    ) {
    }

    public function findAll(?NotificationContactCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $accessibleContactIds = null;
        if (($viewerId = $criteria?->getViewerId()) instanceof UserId) {
            $accessibleContactIds = $this->findAccessibleContactIds($viewerId);
            if ($accessibleContactIds === []) {
                return new Collection([], NotificationContact::class);
            }
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('c.contact_id AS id', 'c.contact_name AS name')
            ->from(self::TABLE_NAME, 'c')
            ->andWhere("c.contact_register = '1'")
            ->orderBy('c.contact_name') // matches legacy CentreonACL::getContactAclConf ordering
            ->addOrderBy('c.contact_id'); // deterministic pagination if two contacts share a name

        if ($accessibleContactIds !== null) {
            $qb->andWhere($qb->expr()->in(
                'c.contact_id',
                $qb->createNamedParameter($accessibleContactIds, ArrayParameterType::INTEGER)
            ));
        }

        if ($criteria instanceof NotificationContactCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createNotificationContacts($rows);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so it is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(c.contact_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createNotificationContacts($rows),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    /**
     * Mirrors legacy CentreonACL::getContactAclConf() for a non-admin: the union of (a) contacts
     * directly linked to one of the viewer's Access Groups, and (b) contacts belonging to a
     * contact group that is itself linked to one of the viewer's Access Groups. Each branch is its
     * own simple, obviously-correct query, merged here rather than joined into one query with an
     * OR: a single multi-join query would fan out one row per (direct-link × contact-group-link)
     * combination for a contact in both, relying on the caller's own DISTINCT/GROUP BY to collapse
     * it back down instead of being correct on its own.
     *
     * @return list<int>
     */
    private function findAccessibleContactIds(UserId $viewerId): array
    {
        $groupIds = array_values(array_map(
            static fn (AccessGroupId $id): int => $id->value,
            iterator_to_array($this->accessGroupRepository->findActiveGroupIdsForUser($viewerId)),
        ));

        if ($groupIds === []) {
            return [];
        }

        return array_values(array_unique([
            ...$this->findContactIdsDirectlyLinkedToAccessGroups($groupIds),
            ...$this->findContactIdsLinkedViaContactGroupToAccessGroups($groupIds),
        ]));
    }

    /**
     * @param list<int> $groupIds
     *
     * @return list<int>
     */
    private function findContactIdsDirectlyLinkedToAccessGroups(array $groupIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('DISTINCT c.contact_id')
            ->from(self::TABLE_NAME, 'c')
            ->innerJoin('c', 'acl_group_contacts_relations', 'agcr', 'agcr.contact_contact_id = c.contact_id')
            ->where("c.contact_register = '1'")
            ->andWhere($qb->expr()->in('agcr.acl_group_id', $qb->createNamedParameter($groupIds, ArrayParameterType::INTEGER)));

        /** @var list<array{contact_id: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): int => (int) $row['contact_id'], $rows);
    }

    /**
     * @param list<int> $groupIds
     *
     * @return list<int>
     */
    private function findContactIdsLinkedViaContactGroupToAccessGroups(array $groupIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('DISTINCT c.contact_id')
            ->from(self::TABLE_NAME, 'c')
            ->innerJoin('c', 'contactgroup_contact_relation', 'ccr', 'ccr.contact_contact_id = c.contact_id')
            ->innerJoin('ccr', 'acl_group_contactgroups_relations', 'agccgr', 'agccgr.cg_cg_id = ccr.contactgroup_cg_id')
            ->where("c.contact_register = '1'")
            ->andWhere($qb->expr()->in('agccgr.acl_group_id', $qb->createNamedParameter($groupIds, ArrayParameterType::INTEGER)));

        /** @var list<array{contact_id: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): int => (int) $row['contact_id'], $rows);
    }

    private function filterByCriteria(QueryBuilder $qb, NotificationContactCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $likeValue = $qb->createNamedParameter('%' . $name . '%');
            $qb->andWhere($qb->expr()->or(
                $qb->expr()->like('c.contact_name', $likeValue),
                $qb->expr()->like('c.contact_alias', $likeValue),
            ));
        }
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<NotificationContact>
     */
    private function createNotificationContacts(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): NotificationContact => $this->transformer->transform($row),
                $rows
            ),
            NotificationContact::class
        );
    }

    private function paginate(QueryBuilder $qb, NotificationContactCriteria $criteria): void
    {
        if ($criteria->getPage() === null || $criteria->getItemsPerPage() === null) {
            return;
        }

        $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
            ->setMaxResults($criteria->getItemsPerPage());
    }
}
