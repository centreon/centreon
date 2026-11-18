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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Model\HostChoice;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostChoiceCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostChoiceRepository;
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
 *   host_id: int,
 *   host_name: string,
 * }
 */
final readonly class DbalHostChoiceRepository extends DbalRepository implements HostChoiceRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'host';

    /**
     * @param TransformerInterface<RowTypeAlias, HostChoice> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: HostChoiceTransformer::class)]
        private TransformerInterface $transformer,

        private AccessScopeResolver $accessScopes,
    ) {
    }

    public function findAll(HostChoiceCriteria $criteria): \IteratorAggregate&\Countable
    {
        // The acting user's scope: unrestricted for a user escaping ACL scoping, otherwise what their
        // ACL grants. Nothing granted gives an empty result that keeps the paginator envelope.
        $scope = $criteria->accessScope($this->accessScopes->hosts(...), HostId::class);
        if ($scope->allowsNothing()) {
            return $this->emptyResult($criteria, HostChoice::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('h.host_id', 'h.host_name')
            ->from(self::TABLE_NAME, 'h')
            // host_register '1' is a host; '0' a template, '2' a meta host. No poller join on purpose:
            // a host not yet attached to a poller is still offered, and disabled hosts are kept too.
            ->where("h.host_register = '1'")
            // host_name is nullable while HostChoice requires a non-empty value: excluding such rows
            // keeps one malformed row from failing the whole listing.
            ->andWhere("h.host_name IS NOT NULL AND h.host_name != ''")
            ->orderBy('h.host_id'); // the selector order, also required for deterministic pagination

        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('h.host_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        if (! $scope->isUnrestricted()) {
            $qb->andWhere($qb->expr()->in(
                'h.host_id',
                $qb->createNamedParameter($scope->allowedIdValues(), ArrayParameterType::INTEGER)
            ));
        }

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createHostChoices($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination
        $count = $this->countMatching($qb, 'COUNT(h.host_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createHostChoices($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<HostChoice>
     */
    private function createHostChoices(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): HostChoice => $this->transformer->transform($row),
                $rows
            ),
            HostChoice::class
        );
    }
}
