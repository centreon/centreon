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

use App\MonitoringConfiguration\Domain\Aggregate\Trap\Trap;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TrapCriteria;
use App\MonitoringConfiguration\Domain\Repository\TrapRepository;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   traps_id: int,
 *   traps_name: string,
 *   vendor_id: int,
 *   vendor_name: string,
 * }
 */
final readonly class DbalTrapRepository extends DbalRepository implements TrapRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'traps';
    public const VENDOR_TABLE_NAME = 'traps_vendor';

    /**
     * @param TransformerInterface<RowTypeAlias, Trap> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: TrapTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findAll(?TrapCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('t.traps_id', 't.traps_name', 'v.id AS vendor_id', 'v.name AS vendor_name')
            ->from(self::TABLE_NAME, 't')
            // the inner join drops traps without a vendor (manufacturer_id IS NULL): a listed trap
            // always carries its vendor.
            ->innerJoin('t', self::VENDOR_TABLE_NAME, 'v', 'v.id = t.manufacturer_id')
            // both names are nullable while TrapName / TrapVendorName require a non-empty value:
            // excluding such rows keeps one malformed row from failing the whole listing.
            ->where("t.traps_name IS NOT NULL AND t.traps_name != ''")
            ->andWhere("v.name IS NOT NULL AND v.name != ''")
            ->orderBy('t.traps_id'); // the selector order, also required for deterministic pagination

        // the name search targets the trap name only, never the vendor name
        if (($name = $criteria?->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('t.traps_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createTraps($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination
        $count = $this->countMatching($qb, 'COUNT(t.traps_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createTraps($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<Trap>
     */
    private function createTraps(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): Trap => $this->transformer->transform($row),
                $rows
            ),
            Trap::class
        );
    }
}
