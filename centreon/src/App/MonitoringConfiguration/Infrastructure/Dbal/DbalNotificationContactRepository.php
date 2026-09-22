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

use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContact;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\NotificationContactCriteria;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
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
 *   id: int,
 *   name: string,
 * }
 */
final readonly class DbalNotificationContactRepository extends DbalRepository implements NotificationContactRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'contact';

    /**
     * @param TransformerInterface<RowTypeAlias, NotificationContact> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: NotificationContactTransformer::class)]
        private TransformerInterface $transformer,

        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        $idValues = array_map(static fn (NotificationContactId $id): int => $id->value, $ids->toArray());
        if ($idValues === []) {
            return new Collection([], NotificationContactName::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('contact_id', 'contact_name')
            ->from(self::TABLE_NAME)
            ->where("contact_register = '1'")
            ->andWhere($qb->expr()->in('contact_id', $qb->createNamedParameter($idValues, ArrayParameterType::INTEGER)));

        /** @var list<array{contact_id: int|string, contact_name: string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['contact_id']] = new NotificationContactName($row['contact_name']);
        }

        return new Collection($names, NotificationContactName::class);
    }

    public function findAll(?NotificationContactCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $accessibleContactIds = null;
        if (($viewerId = $criteria?->getViewerId()) instanceof UserId) {
            $accessibleContactIds = array_map(
                static fn (NotificationContactId $id): int => $id->value,
                $this->resourceAccessRepository->findAccessibleContactIds($viewerId)->toArray(),
            );
            if ($accessibleContactIds === []) {
                return $this->emptyResult($criteria);
            }
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('c.contact_id AS id', 'c.contact_name AS name')
            ->from(self::TABLE_NAME, 'c')
            ->andWhere("c.contact_register = '1'")
            // contact_name is nullable/emptyable in DB (varchar(200) DEFAULT NULL) while
            // NotificationContactName requires a non-empty value: excluding such rows here keeps
            // the read projection honest instead of letting the VO invariant turn into a 500.
            ->andWhere("c.contact_name IS NOT NULL AND c.contact_name != ''")
            ->orderBy('c.contact_name') // alphabetical order for a name-based selector
            ->addOrderBy('c.contact_id'); // deterministic pagination if two contacts share a name

        if ($accessibleContactIds !== null) {
            $qb->andWhere($qb->expr()->in(
                'c.contact_id',
                $qb->createNamedParameter($accessibleContactIds, ArrayParameterType::INTEGER)
            ));
        }

        if ($criteria instanceof NotificationContactCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createNotificationContacts($rows);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so it is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(c.contact_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createNotificationContacts($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * A restricted viewer with no accessible contact short-circuits before any query runs. When
     * pagination was requested, the response must still carry the pagination envelope (page, page
     * size, totalItems: 0) instead of degrading to a bare array — the client can't otherwise tell
     * "zero matches" from "pagination metadata omitted".
     */
    private function emptyResult(?NotificationContactCriteria $criteria): \IteratorAggregate&\Countable
    {
        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            return new Collection([], NotificationContact::class);
        }

        return new InMemoryPaginator(
            items: new Collection([], NotificationContact::class),
            totalItems: 0,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    private function filterByCriteria(QueryBuilder $qb, NotificationContactCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $likeValue = $qb->createNamedParameter('%' . $name . '%');
            $qb->andWhere($qb->expr()->or(
                $qb->expr()->like('c.contact_name', $likeValue),
                $qb->expr()->like('c.contact_alias', $likeValue),
            ));
        }
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<NotificationContact>
     */
    private function createNotificationContacts(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): NotificationContact => $this->transformer->transform($row),
                $rows
            ),
            NotificationContact::class
        );
    }

    private function paginate(QueryBuilder $qb, NotificationContactCriteria $criteria): void
    {
        $pagination = $criteria->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            return;
        }

        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->itemsPerPage);
    }
}
