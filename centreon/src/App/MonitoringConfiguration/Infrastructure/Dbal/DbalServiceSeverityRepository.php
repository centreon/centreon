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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverity;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceSeverityCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceSeverityRepository;
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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   sc_id: int,
 *   sc_name: string,
 * }
 */
final readonly class DbalServiceSeverityRepository extends DbalRepository implements ServiceSeverityRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'service_categories';

    /**
     * @param TransformerInterface<RowTypeAlias, ServiceSeverity> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: ServiceSeverityTransformer::class)]
        private TransformerInterface $transformer,

        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function findAll(?ServiceSeverityCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('sc.sc_id', 'sc.sc_name')
            ->from(self::TABLE_NAME, 'sc')
            // A service severity is a service category carrying a level; levelless ones are regular
            // categories, offered by their own selector.
            ->where('sc.level IS NOT NULL')
            // sc_name is nullable while the name value object requires a non-empty value: excluding
            // such rows keeps one malformed row from failing the whole listing. Disabled rows are
            // listed: sc_activate is not read.
            ->andWhere("sc.sc_name IS NOT NULL AND sc.sc_name != ''")
            ->orderBy('sc.sc_id'); // the selector order, also required for deterministic pagination

        if (($name = $criteria?->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('sc.sc_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        // ACL data-scoping: a restricted viewer only sees the service severities its active ACL resources
        // grant. A null accessible set means no service severity restriction applies to that viewer.
        if (($viewerId = $criteria?->getViewerId()) instanceof UserId) {
            $accessibleSeverityIds = $this->resourceAccessRepository->findAccessibleServiceSeverityIds($viewerId);
            if ($accessibleSeverityIds instanceof Collection) {
                $accessibleIds = array_map(
                    static fn (ServiceSeverityId $id): int => $id->value,
                    $accessibleSeverityIds->toArray(),
                );
                if ($accessibleIds === []) {
                    // Nothing accessible: match nothing, but keep the normal (paginator) shape.
                    $qb->andWhere('1 = 0');
                } else {
                    $qb->andWhere($qb->expr()->in(
                        'sc.sc_id',
                        $qb->createNamedParameter($accessibleIds, ArrayParameterType::INTEGER)
                    ));
                }
            }
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createSeverities($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination
        $count = $this->countMatching($qb, 'COUNT(sc.sc_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createSeverities($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<ServiceSeverity>
     */
    private function createSeverities(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): ServiceSeverity => $this->transformer->transform($row),
                $rows
            ),
            ServiceSeverity::class
        );
    }
}
