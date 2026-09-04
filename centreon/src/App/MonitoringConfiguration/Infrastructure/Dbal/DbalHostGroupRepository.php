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

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroup;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostGroupCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type RowTypeAlias = array{
 *   hg_id: int,
 *   hg_name: string,
 * }
 */
final readonly class DbalHostGroupRepository extends DbalRepository implements HostGroupRepository
{
    public const TABLE_NAME = 'hostgroup';

    /**
     * @param TransformerInterface<RowTypeAlias, HostGroup> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalHostGroupTransformer::class)]
        private TransformerInterface $transformer,
        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function findAll(?HostGroupCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'hg')
            ->orderBy('hg.hg_id'); // required for deterministic pagination

        if ($criteria instanceof HostGroupCriteria) {
            $this->filterByHostGroupCriteria($qb, $criteria);
        }

        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return new Collection(array_map(fn (array $row): HostGroup => $this->createHostGroup($row), $rows), HostGroup::class);
        }

        $this->paginate($qb, $criteria);

        $count = $this->countOnQueryBuilder($qb); // must be done before fetching all rows

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: new Collection(array_map(fn (array $row): HostGroup => $this->createHostGroup($row), $rows), HostGroup::class),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'hg'): array
    {
        return [
            "{$alias}.hg_id AS hg_id",
            "{$alias}.hg_name AS hg_name",
        ];
    }

    private function filterByHostGroupCriteria(QueryBuilder $qb, HostGroupCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('hg.hg_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        if (($viewerId = $criteria->getViewerId()) instanceof UserId) {
            $accessibleHostGroupIds = $this->resourceAccessRepository->findAccessibleHostGroupIds($viewerId);
            if ($accessibleHostGroupIds !== null) {
                $qb->andWhere($qb->expr()->in(
                    'hg.hg_id',
                    $qb->createNamedParameter(
                        array_map(static fn (HostGroupId $id): int => $id->value, $accessibleHostGroupIds),
                        ArrayParameterType::INTEGER
                    )
                ));
            }
        }
    }

    private function paginate(QueryBuilder $qb, HostGroupCriteria $criteria): void
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
            ->select('COUNT(DISTINCT hg.hg_id)')
            ->setFirstResult(0) // reset any pagination
            ->setMaxResults(null)
            ->executeQuery()
            ->fetchOne();

        Assert::integer($count);

        return $count;
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createHostGroup(array $row): HostGroup
    {
        return $this->transformer->transform($row);
    }
}
