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

use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\GraphTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\GraphTemplateRepository;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   graph_id: int,
 *   name: string,
 * }
 */
final readonly class DbalGraphTemplateRepository extends DbalRepository implements GraphTemplateRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'giv_graphs_template';

    /**
     * @param TransformerInterface<RowTypeAlias, GraphTemplate> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: GraphTemplateTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findAll(?GraphTemplateCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('g.graph_id', 'g.name')
            ->from(self::TABLE_NAME, 'g')
            // name is nullable while GraphTemplateName requires a non-empty value:
            // excluding such rows keeps one malformed row from failing the whole listing.
            ->where("g.name IS NOT NULL AND g.name != ''")
            ->orderBy('g.graph_id'); // the selector order, also required for deterministic pagination

        if (($name = $criteria?->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('g.name', $qb->createNamedParameter('%' . $name . '%')));
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createGraphTemplates($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination
        $count = $this->countMatching($qb, 'COUNT(g.graph_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createGraphTemplates($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        $idValues = array_values(array_map(static fn (GraphTemplateId $id): int => $id->value, $ids->toArray()));
        if ($idValues === []) {
            return new Collection([], GraphTemplateName::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('g.graph_id', 'g.name')
            ->from(self::TABLE_NAME, 'g')
            ->where($qb->expr()->in('g.graph_id', $qb->createNamedParameter($idValues, ArrayParameterType::INTEGER)));

        /** @var list<array{graph_id: int|string, name: string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        // `name` is nullable; a nameless row reads as "not found".
        $names = [];
        foreach ($rows as $row) {
            if (($name = (string) $row['name']) !== '') {
                $names[(int) $row['graph_id']] = new GraphTemplateName($name);
            }
        }

        return new Collection($names, GraphTemplateName::class);
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<GraphTemplate>
     */
    private function createGraphTemplates(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): GraphTemplate => $this->transformer->transform($row),
                $rows
            ),
            GraphTemplate::class
        );
    }
}
