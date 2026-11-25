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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroup;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceGroupCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceGroupRepository;
use App\Security\Domain\Service\AccessScopeResolver;
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
 *   sg_id: int,
 *   sg_name: string,
 * }
 */
final readonly class DbalServiceGroupRepository extends DbalRepository implements ServiceGroupRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'servicegroup';

    /**
     * @param TransformerInterface<RowTypeAlias, ServiceGroup> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: ServiceGroupTransformer::class)]
        private TransformerInterface $transformer,

        private AccessScopeResolver $accessScopes,
    ) {
    }

    public function findAll(ServiceGroupCriteria $criteria): \IteratorAggregate&\Countable
    {
        // The acting user's scope: unrestricted for a user escaping ACL scoping or granted every service
        // group, otherwise what their ACL grants. Nothing granted gives an empty result that keeps the
        // paginator envelope.
        $scope = $criteria->accessScope($this->accessScopes->serviceGroups(...), ServiceGroupId::class);
        if ($scope->allowsNothing()) {
            return $this->emptyResult($criteria, ServiceGroup::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('sg.sg_id', 'sg.sg_name')
            ->from(self::TABLE_NAME, 'sg')
            // sg_name is nullable while ServiceGroupName requires a non-empty value: excluding such
            // rows keeps one malformed row from failing the whole listing. Disabled groups are listed
            // for every user, restricted ones included, whereas legacy hides them from restricted
            // users unless an ACL resource grants all service groups.
            ->where("sg.sg_name IS NOT NULL AND sg.sg_name != ''")
            ->orderBy('sg.sg_id'); // the selector order, also required for deterministic pagination

        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('sg.sg_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        if (! $scope->isUnrestricted()) {
            $qb->andWhere($qb->expr()->in(
                'sg.sg_id',
                $qb->createNamedParameter($scope->allowedIdValues(), ArrayParameterType::INTEGER)
            ));
        }

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createServiceGroups($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination
        $count = $this->countMatching($qb, 'COUNT(sg.sg_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createServiceGroups($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<ServiceGroup>
     */
    private function createServiceGroups(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): ServiceGroup => $this->transformer->transform($row),
                $rows
            ),
            ServiceGroup::class
        );
    }
}
