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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroup;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ContactGroupCriteria;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\AccessGroupRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type RowTypeAlias = array{
 *   cg_id: int,
 *   cg_name: string,
 * }
 */
final readonly class DbalContactGroupRepository extends DbalRepository implements ContactGroupRepository
{
    public const TABLE_NAME = 'contactgroup';

    /**
     * @param TransformerInterface<RowTypeAlias, ContactGroup> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalContactGroupTransformer::class)]
        private TransformerInterface $transformer,
        private AccessGroupRepository $accessGroupRepository,
    ) {
    }

    public function findAll(?ContactGroupCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'cg')
            ->orderBy('cg.cg_id'); // required for deterministic pagination

        if ($criteria instanceof ContactGroupCriteria) {
            // ACL data-scoping: a non-admin viewer only sees the contact groups reachable through
            // their access groups or their own membership. An admin passes a null viewerId.
            if (($viewerId = $criteria->getViewerId()) instanceof UserId) {
                $accessibleIds = $this->findAccessibleContactGroupIds($viewerId);
                if ($accessibleIds === []) {
                    return new Collection([], ContactGroup::class);
                }
                $qb->andWhere($qb->expr()->in(
                    'cg.cg_id',
                    $qb->createNamedParameter($accessibleIds, ArrayParameterType::INTEGER)
                ));
            }
            $this->filterByCriteria($qb, $criteria);
        }

        // if no pagination
        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return new Collection(
                array_map(fn (array $row): ContactGroup => $this->transformer->transform($row), $rows),
                ContactGroup::class
            );
        }

        $this->paginate($qb, $criteria);

        $count = $this->countOnQueryBuilder($qb); // must be done before fetching all rows

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: new Collection(
                array_map(fn (array $row): ContactGroup => $this->transformer->transform($row), $rows),
                ContactGroup::class
            ),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'cg'): array
    {
        return [
            "{$alias}.cg_id AS cg_id",
            "{$alias}.cg_name AS cg_name",
        ];
    }

    public function filterByCriteria(QueryBuilder $qb, ContactGroupCriteria $criteria): void
    {
        if ($nameCriteria = $criteria->getNames()) {
            foreach ($nameCriteria as $operator => $names) {
                if ($operator === ContactGroupCriteria::OPERATOR_LIKE) {
                    $qb->andWhere($qb->expr()->or(...array_map(
                        static fn (string $name): string => $qb->expr()->like(
                            'cg.cg_name',
                            $qb->createNamedParameter('%' . $name . '%')
                        ),
                        $names
                    )));

                    continue;
                }
                $qb->andWhere($qb->expr()->in(
                    'cg.cg_name',
                    $qb->createNamedParameter($names, ArrayParameterType::STRING)
                ));
            }
        }
        if ($idCriteria = $criteria->getIds()) {
            foreach ($idCriteria as $operator => $ids) {
                if ($operator === ContactGroupCriteria::OPERATOR_LIKE) {
                    $qb->andWhere($qb->expr()->or(...array_map(
                        static fn (int $id): string => $qb->expr()->like(
                            'cg.cg_id',
                            $qb->createNamedParameter('%' . $id . '%')
                        ),
                        $ids
                    )));

                    continue;
                }
                $qb->andWhere($qb->expr()->in(
                    'cg.cg_id',
                    $qb->createNamedParameter($ids, ArrayParameterType::INTEGER)
                ));
            }
        }
    }

    /**
     * Reproduce the legacy ACL data-scoping: a non-admin sees the contact groups reachable through
     * their active access groups (`acl_group_contactgroups_relations`) union the ones they belong
     * to directly (`contactgroup_contact_relation`, registered contacts only).
     *
     * @return list<int>
     */
    private function findAccessibleContactGroupIds(UserId $userId): array
    {
        $accessGroupIds = array_map(
            static fn (AccessGroupId $id): int => $id->value,
            iterator_to_array($this->accessGroupRepository->findActiveGroupIdsForUser($userId)),
        );

        $ids = [];

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
                $ids[] = (int) $row['id'];
            }
        }

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
            $ids[] = (int) $row['id'];
        }

        return array_values(array_unique($ids));
    }

    private function paginate(QueryBuilder $qb, ContactGroupCriteria $criteria): void
    {
        if ($criteria->getPage() === null || $criteria->getItemsPerPage() === null) {
            return;
        }

        $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
            ->setMaxResults($criteria->getItemsPerPage());
    }

    private function countOnQueryBuilder(QueryBuilder $qb): int
    {
        $qb = clone $qb; // avoid modifying the initial query builder

        $count = $qb
            ->select('COUNT(DISTINCT cg.cg_id)')
            ->setFirstResult(0) // reset any pagination
            ->setMaxResults(null)
            ->executeQuery()
            ->fetchOne();

        Assert::integer($count);

        return $count;
    }
}
