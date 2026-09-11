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

use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverity;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostSeverityCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Collection;
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
 *   hc_id: int,
 *   hc_name: string,
 * }
 */
final readonly class DbalHostSeverityRepository extends DbalRepository implements HostSeverityRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'hostcategories';

    /**
     * @param TransformerInterface<RowTypeAlias, HostSeverity> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: HostSeverityTransformer::class)]
        private TransformerInterface $transformer,

        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function findAll(?HostSeverityCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('hc.hc_id', 'hc.hc_name')
            ->from(self::TABLE_NAME, 'hc')
            // a HostSeverity is a hostcategories row carrying a severity level; levelless rows are
            // host categories, a distinct concept that must never surface here.
            ->where('hc.level IS NOT NULL')
            ->orderBy('hc.hc_id'); // required for deterministic pagination

        if ($criteria instanceof HostSeverityCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createHostSeverities($rows);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so it is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT hc.hc_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createHostSeverities($rows),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    private function filterByCriteria(QueryBuilder $qb, HostSeverityCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('hc.hc_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        $this->filterByViewer($qb, $criteria);
    }

    private function filterByViewer(QueryBuilder $qb, HostSeverityCriteria $criteria): void
    {
        $viewerId = $criteria->getViewerId();
        if (! $viewerId instanceof UserId) {
            return;
        }

        $accessibleHostSeverities = $this->resourceAccessRepository->findAccessibleHostSeverityIds($viewerId);
        // null means no host-severity restriction applies — the viewer sees every severity.
        if (! $accessibleHostSeverities instanceof Collection) {
            return;
        }

        $accessibleHostSeverityIds = array_map(
            static fn (HostSeverityId $hostSeverityId): int => $hostSeverityId->value,
            $accessibleHostSeverities->toArray()
        );

        // A non-null but empty set means the viewer is restricted on host severities yet granted
        // none (e.g. their ACL points only at levelless categories): they see nothing. Mirrors legacy,
        // where hasRestrictedAccessToHostSeverities is true but findAllByAccessGroups returns no row.
        if ($accessibleHostSeverityIds === []) {
            $qb->andWhere('1 = 0');

            return;
        }

        // Restrict to severities the viewer can access. findAccessibleHostSeverityIds already scopes
        // the set to level-bearing categories, so it never carries a levelless category id.
        $qb->andWhere(
            'hc.hc_id IN (' . $qb->createNamedParameter(
                $accessibleHostSeverityIds,
                ArrayParameterType::INTEGER
            ) . ')'
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<HostSeverity>
     */
    private function createHostSeverities(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): HostSeverity => $this->transformer->transform($row),
                $rows
            ),
            HostSeverity::class
        );
    }

    private function paginate(QueryBuilder $qb, HostSeverityCriteria $criteria): void
    {
        if ($criteria->getPage() === null || $criteria->getItemsPerPage() === null) {
            return;
        }

        $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
            ->setMaxResults($criteria->getItemsPerPage());
    }
}
