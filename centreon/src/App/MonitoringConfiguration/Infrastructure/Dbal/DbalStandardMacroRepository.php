<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacro;
use App\MonitoringConfiguration\Domain\Repository\Criteria\StandardMacroCriteria;
use App\MonitoringConfiguration\Domain\Repository\StandardMacroRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type RowTypeAlias = array{
 *   macro_id: int,
 *   macro_name: string,
 * }
 */
final readonly class DbalStandardMacroRepository extends DbalRepository implements StandardMacroRepository
{
    public const TABLE_NAME = 'nagios_macro';

    /**
     * @param TransformerInterface<RowTypeAlias, StandardMacro> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalStandardMacroTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findAll(?StandardMacroCriteria $criteria = null): \IteratorAggregate&\Countable
    {

        $qb = $this->connection->createQueryBuilder();
        $qb->select('macro_id', 'macro_name')
            ->from(self::TABLE_NAME, 'sm')
            ->orderBy('sm.macro_id'); // required for deterministic pagination

        // if we have a criteria, filter the query
        if ($criteria instanceof StandardMacroCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        // if no pagination
        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createStandardMacros($rows);
        }

        $this->paginate($qb, $criteria);

        $count = $this->countOnQueryBuilder($qb); // must be done before fetching all rows

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createStandardMacros($rows),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    private function filterByCriteria(QueryBuilder $qb, StandardMacroCriteria $criteria): void
    {
        if ($nameCriteria = $criteria->getNames()) {
            foreach ($nameCriteria as $operator => $names) {
                if ($operator === StandardMacroCriteria::OPERATOR_LIKE) {
                    $qb->andWhere($qb->expr()->or(...array_map(
                        static fn (string $name): string => $qb->expr()->like('sm.macro_name', '"%' . $name . '%"'),
                        $names
                    )));

                    continue;
                }
                $qb->andWhere($qb->expr()->in(
                    'sm.macro_name',
                    array_map(static fn (string $name): string => '"' . $name . '"', $names)
                ));
            }
        }
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<StandardMacro>
     */
    private function createStandardMacros(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): StandardMacro => $this->transformer->transform($row),
                $rows
            ),
            StandardMacro::class
        );
    }

    private function paginate(QueryBuilder $qb, StandardMacroCriteria $criteria): void
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
            ->select('COUNT(DISTINCT sm.macro_id)')
            ->setFirstResult(0) // reset any pagination
            ->setMaxResults(null)
            ->executeQuery()
            ->fetchOne();

        Assert::integer($count);

        return $count;
    }
}
