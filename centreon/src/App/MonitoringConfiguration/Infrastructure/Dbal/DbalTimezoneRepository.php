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

use App\MonitoringConfiguration\Domain\Aggregate\Timezone\Timezone;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TimezoneCriteria;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
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
 *   id: int,
 *   name: string,
 * }
 */
final readonly class DbalTimezoneRepository extends DbalRepository implements TimezoneRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'timezone';

    /**
     * @param TransformerInterface<RowTypeAlias, Timezone> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: TimezoneTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findAll(?TimezoneCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('t.timezone_id AS id', 't.timezone_name AS name')
            ->from(self::TABLE_NAME, 't')
            ->orderBy('t.timezone_name') // alphabetical order for a name-based selector, matches legacy
            ->addOrderBy('t.timezone_id'); // deterministic pagination (timezone_name is UNIQUE, kept as a defensive tiebreaker)

        if ($criteria instanceof TimezoneCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createTimezones($rows);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so it is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(t.timezone_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createTimezones($rows),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    private function filterByCriteria(QueryBuilder $qb, TimezoneCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('t.timezone_name', $qb->createNamedParameter('%' . $name . '%')));
        }
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<Timezone>
     */
    private function createTimezones(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): Timezone => $this->transformer->transform($row),
                $rows
            ),
            Timezone::class
        );
    }

    private function paginate(QueryBuilder $qb, TimezoneCriteria $criteria): void
    {
        if ($criteria->getPage() === null || $criteria->getItemsPerPage() === null) {
            return;
        }

        $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
            ->setMaxResults($criteria->getItemsPerPage());
    }
}
