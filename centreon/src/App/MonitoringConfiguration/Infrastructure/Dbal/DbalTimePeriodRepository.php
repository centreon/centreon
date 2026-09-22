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

use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriod;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TimePeriodCriteria;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   tp_id: int,
 *   tp_name: string,
 * }
 */
final readonly class DbalTimePeriodRepository extends DbalRepository implements TimePeriodRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'timeperiod';

    /**
     * @param TransformerInterface<RowTypeAlias, TimePeriod> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalTimePeriodTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findAll(?TimePeriodCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('tp.tp_id', 'tp.tp_name')
            ->from(self::TABLE_NAME, 'tp')
            // legacy sorts by tp_id ASC by default; also required for deterministic pagination
            ->orderBy('tp.tp_id');

        if ($criteria instanceof TimePeriodCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createTimePeriods($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so it is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(tp.tp_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createTimePeriods($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    private function filterByCriteria(QueryBuilder $qb, TimePeriodCriteria $criteria): void
    {
        foreach ($criteria->getNames() as $operator => $names) {
            if ($operator === TimePeriodCriteria::OPERATOR_LIKE) {
                $qb->andWhere($qb->expr()->or(...array_map(
                    static fn (string $name): string => $qb->expr()->like(
                        'tp.tp_name',
                        $qb->createNamedParameter('%' . $name . '%')
                    ),
                    $names
                )));

                continue;
            }
        }
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<TimePeriod>
     */
    private function createTimePeriods(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): TimePeriod => $this->transformer->transform($row),
                $rows
            ),
            TimePeriod::class
        );
    }
}
