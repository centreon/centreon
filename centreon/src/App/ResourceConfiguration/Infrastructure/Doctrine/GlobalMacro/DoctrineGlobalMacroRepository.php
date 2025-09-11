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

namespace App\ResourceConfiguration\Infrastructure\Doctrine\GlobalMacro;

use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\ResourceConfiguration\Domain\Repository\GlobalMacro\GlobalMacroCriteria;
use App\ResourceConfiguration\Domain\Repository\GlobalMacro\GlobalMacroRepository;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *  gm_resource_id: int,
 *  gm_resource_name: string,
 *  gm_resource_line: string,
 *  gm_resource_comment: string|null,
 *  gm_resource_activate: '0'|'1',
 *  gm_is_password: 0|1,
 * }
 */
final readonly class DoctrineGlobalMacroRepository extends DoctrineRepository implements GlobalMacroRepository
{
    public const TABLE_NAME = 'cfg_resource';

    /**
     * @param TransformerInterface<RowTypeAlias, GlobalMacro> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DoctrineGlobalMacroTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findAll(?GlobalMacroCriteria $criteria = null): Paginator|array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'gm')
            ->orderBy('gm.resource_id'); // required for deterministic pagination

        // if we have a criteria, filter the query
        if ($criteria instanceof \App\ResourceConfiguration\Domain\Repository\GlobalMacro\GlobalMacroCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        // if no pagination
        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createGlobalMacros($rows);
        }

        $this->paginate($qb, $criteria);

        $count = $this->countOnQueryBuilder($qb); // must be done before fetching all rows

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createGlobalMacros($rows),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'gm'): array
    {
        return [
            "{$alias}.resource_id AS gm_resource_id",
            "{$alias}.resource_name AS gm_resource_name",
            "{$alias}.resource_line AS gm_resource_line",
            "{$alias}.resource_comment AS gm_resource_comment",
            "{$alias}.resource_activate AS gm_resource_activate",
            "{$alias}.is_password AS gm_is_password",
        ];
    }

    public function filterByCriteria(QueryBuilder $qb, GlobalMacroCriteria $criteria): void
    {
        if ($nameCriteria = $criteria->getNames()) {
            foreach ($nameCriteria as $operator => $names) {
                if ($operator === GlobalMacroCriteria::OPERATOR_LIKE) {
                    $qb->andWhere($qb->expr()->or(...array_map(
                        static fn (string $name): string => $qb->expr()->like('gm.resource_name', '"%' . $name . '%"'),
                        $names
                    )));

                    continue;
                }
                $qb->andWhere($qb->expr()->in(
                    'gm.resource_name',
                    array_map(static fn (string $name): string => '"' . $name . '"', $names)
                ));
            }
        }
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return array<GlobalMacro>
     */
    private function createGlobalMacros(array $rows): array
    {
        return array_map($this->createGlobalMacro(...), $rows);
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createGlobalMacro(array $row): GlobalMacro
    {
        return $this->transformer->transform($row);
    }

    private function paginate(QueryBuilder $qb, GlobalMacroCriteria $criteria): void
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

        /** @var int $count */
        $count = $qb
            ->select('COUNT(DISTINCT gm.resource_id)')
            ->setFirstResult(0) // reset any pagination
            ->setMaxResults(null)
            ->executeQuery()
            ->fetchOne();

        return $count;
    }
}
