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

use App\MonitoringConfiguration\Domain\Aggregate\Connector\Connector;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorId;
use App\MonitoringConfiguration\Domain\Exception\ConnectorNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ConnectorRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ConnectorCriteria;
use App\MonitoringConfiguration\Domain\Repository\Criteria\GlobalMacroCriteria;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Command;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type RowTypeAlias = array{
 *   c_id: int,
 *   c_name: string,
 *   c_command_line: string,
 *   c_description: string,
 *   c_activate: 0|1,
 * }
 */
final readonly class DbalConnectorRepository extends DbalRepository implements ConnectorRepository
{
    public const TABLE_NAME = 'connector';

    /**
     * @param TransformerInterface<RowTypeAlias, Connector> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalConnectorTransformer::class)]
        private TransformerInterface $transformer,
        private CommandRepository $commandRepository,
    ) {
    }

    public function findById(ConnectorId $id): ?Connector
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(
            'id',
            'name',
            'command_line',
            'description',
            'enabled',
        )
            ->from(self::TABLE_NAME)
            ->where('id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {

            return null;
        }

        return $this->transformer->transform($row);
    }

    public function get(ConnectorId $id): Connector
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'c')
            ->where('id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            throw new ConnectorNotFoundException(['id' => $id->value]);
        }

        return $this->createConnector($row);
    }

    public function findAll(?ConnectorCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $lazyRelations = $criteria?->hasLazyRelations() ?? false;

        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'c')
            ->orderBy('c.id'); // required for deterministic pagination

        // if we have a criteria, filter the query
        if ($criteria instanceof ConnectorCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }
        // if no pagination
        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createConnectors($rows, $lazyRelations);
        }
        $this->paginate($qb, $criteria);

        $count = $this->countOnQueryBuilder($qb); // must be done before fetching all rows

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createConnectors($rows, $lazyRelations),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }


    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'c'): array
    {
        return [
            "{$alias}.id AS c_id",
            "{$alias}.name AS c_name",
            "{$alias}.command_line AS c_command_line",
            "{$alias}.description AS c_description",
            "{$alias}.enabled AS c_activate",
        ];
    }

    public function filterByCriteria(QueryBuilder $qb, ConnectorCriteria $criteria): void
    {
        if ($nameCriteria = $criteria->getNames()) {
            foreach ($nameCriteria as $operator => $names) {
                if ($operator === GlobalMacroCriteria::OPERATOR_LIKE) {
                    $qb->andWhere($qb->expr()->or(...array_map(
                        static fn (string $name): string => $qb->expr()->like('c.name', '"%' . $name . '%"'),
                        $names
                    )));

                    continue;
                }
                $qb->andWhere($qb->expr()->in(
                    'c.name',
                    array_map(static fn (string $name): string => '"' . $name . '"', $names)
                ));
            }
        }
        if ($idCriteria = $criteria->getIds()) {
            foreach ($idCriteria as $operator => $ids) {
                if ($operator === GlobalMacroCriteria::OPERATOR_LIKE) {
                    $qb->andWhere($qb->expr()->or(...array_map(
                        static fn (int $id): string => $qb->expr()->like('c.id', '"%' . $id . '%"'),
                        $ids
                    )));

                    continue;
                }
                $qb->andWhere($qb->expr()->in(
                    'c.id',
                    array_map(static fn (int $id): string => '"' . $id . '"', $ids)
                ));
            }
        }
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<GlobalMacro>
     */
    private function createConnectors(array $rows, bool $lazyRelations = true): Collection
    {
        return new Collection(array_map(fn (array $row): Connector => $this->createConnector($row, $lazyRelations), $rows), Connector::class);
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createConnector(array $row, bool $lazyRelations = true): Connector
    {
        $connector = $this->transformer->transform($row);
        $commands = $lazyRelations
            ? new Collection(fn (): array => $this->commandRepository->findAllByConnector($connector)->toArray(), Command::class)
            : $this->commandRepository->findAllByConnector($connector);
        /** @var ConnectorId $id */
        $id = $connector->id();

        // create a new instance with same values but with the poller collection
        return new Connector(
            id: $id,
            name: $connector->name,
            commandLine: $connector->commandLine,
            description: $connector->description,
            commands: $commands,
            isActivated: $connector->isActivated,
        );
    }

    private function paginate(QueryBuilder $qb, ConnectorCriteria $criteria): void
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
            ->select('COUNT(DISTINCT c.id)')
            ->setFirstResult(0) // reset any pagination
            ->setMaxResults(null)
            ->executeQuery()
            ->fetchOne();

        Assert::integer($count);

        return $count;
    }
}
