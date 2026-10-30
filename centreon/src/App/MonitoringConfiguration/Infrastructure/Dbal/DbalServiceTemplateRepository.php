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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
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
 *   service_id: int,
 *   service_description: string,
 * }
 */
final readonly class DbalServiceTemplateRepository extends DbalRepository implements ServiceTemplateRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'service';

    /**
     * @param TransformerInterface<RowTypeAlias, ServiceTemplate> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: ServiceTemplateTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findAll(?ServiceTemplateCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('s.service_id', 's.service_description')
            ->from(self::TABLE_NAME, 's')
            // service_register '0' is a template; '1' a service, '2'/'3' meta and BA services.
            // Locked and disabled templates are kept on purpose: the legacy form offers them too.
            ->where("s.service_register = '0'")
            // service_description is nullable while ServiceTemplateName requires a non-empty value:
            // excluding such rows keeps one malformed row from failing the whole listing.
            ->andWhere("s.service_description IS NOT NULL AND s.service_description != ''")
            ->orderBy('s.service_id'); // the selector order, also required for deterministic pagination

        if (($name = $criteria?->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('s.service_description', $qb->createNamedParameter('%' . $name . '%')));
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createServiceTemplates($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination
        $count = $this->countMatching($qb, 'COUNT(s.service_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createServiceTemplates($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<ServiceTemplate>
     */
    private function createServiceTemplates(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): ServiceTemplate => $this->transformer->transform($row),
                $rows
            ),
            ServiceTemplate::class
        );
    }
}
