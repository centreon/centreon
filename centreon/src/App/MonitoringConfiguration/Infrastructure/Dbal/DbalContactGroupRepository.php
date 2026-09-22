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
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ContactGroupCriteria;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
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
 *   cg_id: int,
 *   cg_name: string,
 * }
 */
final readonly class DbalContactGroupRepository extends DbalRepository implements ContactGroupRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'contactgroup';

    /**
     * @param TransformerInterface<RowTypeAlias, ContactGroup> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: ContactGroupTransformer::class)]
        private TransformerInterface $transformer,
        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        $idValues = array_map(static fn (ContactGroupId $id): int => $id->value, $ids->toArray());
        if ($idValues === []) {
            return new Collection([], ContactGroupName::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('cg_id', 'cg_name')
            ->from(self::TABLE_NAME)
            ->where("cg_name IS NOT NULL AND cg_name != ''")
            ->andWhere($qb->expr()->in('cg_id', $qb->createNamedParameter($idValues, ArrayParameterType::INTEGER)));

        /** @var list<array{cg_id: int|string, cg_name: string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['cg_id']] = new ContactGroupName($row['cg_name']);
        }

        return new Collection($names, ContactGroupName::class);
    }

    public function findAll(ContactGroupCriteria $criteria): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'cg')
            // cg_name is nullable/emptyable in DB (varchar(200) DEFAULT NULL) while
            // ContactGroupName requires a non-empty value: excluding such rows here keeps the
            // read projection honest instead of letting one malformed row 500 the whole listing.
            ->andWhere("cg.cg_name IS NOT NULL AND cg.cg_name != ''")
            ->orderBy('cg.cg_id'); // required for deterministic pagination

        // ACL data-scoping: a non-admin viewer only sees the contact groups reachable through
        // their access groups or their own membership. An admin passes a null viewerId (no scoping).
        if (($viewerId = $criteria->getViewerId()) instanceof UserId) {
            $accessibleIds = array_map(
                static fn (ContactGroupId $id): int => $id->value,
                $this->resourceAccessRepository->findAccessibleContactGroupIds($viewerId)->toArray(),
            );
            if ($accessibleIds === []) {
                // No accessible contact group: match nothing, but keep the normal (paginator) shape.
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere($qb->expr()->in(
                    'cg.cg_id',
                    $qb->createNamedParameter($accessibleIds, ArrayParameterType::INTEGER)
                ));
            }
        }
        $this->filterByCriteria($qb, $criteria);

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return new Collection(
                array_map(fn (array $row): ContactGroup => $this->transformer->transform($row), $rows),
                ContactGroup::class
            );
        }

        $this->paginate($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so it is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT cg.cg_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: new Collection(
                array_map(fn (array $row): ContactGroup => $this->transformer->transform($row), $rows),
                ContactGroup::class
            ),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
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

    private function filterByCriteria(QueryBuilder $qb, ContactGroupCriteria $criteria): void
    {
        if ($names = $criteria->getNames()) {
            $qb->andWhere($qb->expr()->or(...array_map(
                static fn (string $name): string => $qb->expr()->like(
                    'cg.cg_name',
                    $qb->createNamedParameter('%' . $name . '%')
                ),
                $names
            )));
        }
    }

    private function paginate(QueryBuilder $qb, Pagination $pagination): void
    {
        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->itemsPerPage);
    }
}
