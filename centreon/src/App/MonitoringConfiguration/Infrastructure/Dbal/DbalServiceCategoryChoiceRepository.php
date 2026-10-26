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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Model\ServiceCategoryChoice;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCategoryChoiceCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryChoiceRepository;
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
final readonly class DbalServiceCategoryChoiceRepository extends DbalRepository implements ServiceCategoryChoiceRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'service_categories';

    /**
     * @param TransformerInterface<RowTypeAlias, ServiceCategoryChoice> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: ServiceCategoryChoiceTransformer::class)]
        private TransformerInterface $transformer,

        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function findAll(?ServiceCategoryChoiceCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('sc.sc_id', 'sc.sc_name')
            ->from(self::TABLE_NAME, 'sc')
            // A service category carrying a level is a service severity, offered by its own selector.
            // Only the exposed columns are read: the nullable description is never selected.
            ->where('sc.level IS NULL')
            // sc_name is nullable while the name value object requires a non-empty value: excluding
            // such rows keeps one malformed row from failing the whole listing. Disabled rows are
            // listed: sc_activate is not read.
            ->andWhere("sc.sc_name IS NOT NULL AND sc.sc_name != ''")
            ->orderBy('sc.sc_id'); // the selector order, also required for deterministic pagination

        if (($name = $criteria?->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('sc.sc_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        // ACL data-scoping: a restricted viewer only sees the service categories its active ACL resources
        // grant. A null accessible set means no service category restriction applies to that viewer.
        if (($viewerId = $criteria?->getViewerId()) instanceof UserId) {
            $accessibleCategoryIds = $this->resourceAccessRepository->findAccessibleServiceCategoryIds($viewerId);
            if ($accessibleCategoryIds instanceof Collection) {
                $accessibleIds = array_map(
                    static fn (ServiceCategoryId $id): int => $id->value,
                    $accessibleCategoryIds->toArray(),
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

            return $this->createChoices($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination
        $count = $this->countMatching($qb, 'COUNT(sc.sc_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createChoices($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<ServiceCategoryChoice>
     */
    private function createChoices(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): ServiceCategoryChoice => $this->transformer->transform($row),
                $rows
            ),
            ServiceCategoryChoice::class
        );
    }
}
