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

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\Connector;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\CommandResourceCount;
use App\MonitoringConfiguration\Domain\Repository\Criteria\CommandCriteria;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type RowTypeAlias = array{
 *   cm_command_id: int,
 *   cm_command_name: string,
 *   cm_command_line: string,
 *   cm_command_type: int,
 *   cm_enable_shell: bool,
 *   cm_command_activate: bool,
 *   cm_command_locked: bool,
 *   cm_command_comment: string|null,
 *   cm_connector_id: int|null,
 * }
 */
final readonly class DbalCommandRepository extends DbalRepository implements CommandRepository
{
    public const TABLE_NAME = 'command';
    public const CONNECTOR_JOIN_TABLE_NAME = 'connector';

    /**
     * @param TransformerInterface<RowTypeAlias, Command> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalCommandTransformer::class)]
        private TransformerInterface $transformer,

        private DbalConnectorRepository $connectorRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getById(CommandId $id): Command
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns(), ...DbalConnectorRepository::getSelectColumns())
            ->from(self::TABLE_NAME, 'cm')
            ->leftJoin('cm', self::CONNECTOR_JOIN_TABLE_NAME, 'c', 'cm.connector_id = c.id')
            ->where('command_id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);
        /** @var RowTypeAlias $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if (! $row) {
            throw new CommandNotFoundException(['id' => $id->value], 'Command resource not found');
        }

        return $this->createCommand($row);
    }

    public function findOneByName(CommandName $name): ?Command
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'cm')
            ->where('command_name = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if (! $row) {
            return null;
        }

        return $this->createCommand($row);
    }

    /**
     * @inheritDoc
     */
    public function findAll(?CommandCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'cm');

        // if we have a criteria, filter the query
        if ($criteria instanceof CommandCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }
        // if no pagination
        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return new Collection(array_map(fn (array $row): Command => $this->createCommand($row), $rows), Command::class);
        }

        $this->paginate($qb, $criteria);

        $count = $this->countOnQueryBuilder($qb); // must be done before fetching all rows

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: new Collection(array_map(fn (array $row): Command => $this->createCommand($row), $rows), Command::class),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    public function add(Command $command): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->insert(self::TABLE_NAME)
            ->values([
                'command_name' => ':command_name',
                'command_line' => ':command_line',
                'command_type' => ':command_type',
                'enable_shell' => ':enable_shell',
                'command_activate' => ':command_activate',
                'command_locked' => ':command_locked',
                'command_comment' => ':command_comment',
                'connector_id' => ':connector_id',
            ])
            ->setParameter('command_name', $command->name->value)
            ->setParameter('command_line', $command->commandLine->value)
            ->setParameter('command_type', $command->type->value)
            ->setParameter('enable_shell', $command->isShellEnabled ? '1' : '0')
            ->setParameter('command_activate', $command->isActivated ? '1' : '0')
            ->setParameter('command_locked', $command->isFromMonitoringConnector ? '1' : '0')
            ->setParameter('command_comment', $command->comment?->value)
            ->setParameter('connector_id', $command->connector?->id()->value)
            ->executeStatement();

        $id = (int) $this->connection->lastInsertId();

        if ($id === 0) {
            throw new \RuntimeException(\sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($command, new CommandId($id));
    }

    public function countLinkedResources(CommandId $id): CommandResourceCount
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(
            "(SELECT COUNT(host_id) FROM host WHERE (command_command_id = cm.command_id OR command_command_id2 = cm.command_id) AND host_register = '1') AS cm_used_hosts_count",
            "(SELECT COUNT(host_id) FROM host WHERE (command_command_id = cm.command_id OR command_command_id2 = cm.command_id) AND host_register = '0') AS cm_used_host_templates_count",
            "(SELECT COUNT(service_id) FROM service WHERE (command_command_id = cm.command_id OR command_command_id2 = cm.command_id) AND service_register = '1') AS cm_used_services_count",
            "(SELECT COUNT(service_id) FROM service WHERE (command_command_id = cm.command_id OR command_command_id2 = cm.command_id) AND service_register = '0') AS cm_used_service_templates_count"
        )
            ->from(self::TABLE_NAME, 'cm')
            ->where('cm.command_id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        /** @var array{
         *   cm_used_hosts_count: string,
         *   cm_used_host_templates_count: string,
         *   cm_used_services_count: string,
         *   cm_used_service_templates_count: string
         *   }|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if (! $row) {
            throw new \RuntimeException(sprintf('Unable to retrieve resource counts for command #%d.', $id->value));
        }

        return new CommandResourceCount(
            usedHosts: (int) $row['cm_used_hosts_count'],
            usedHostTemplates: (int) $row['cm_used_host_templates_count'],
            usedServices: (int) $row['cm_used_services_count'],
            usedServiceTemplates: (int) $row['cm_used_service_templates_count'],
        );
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'cm'): array
    {
        return [
            "{$alias}.command_id AS cm_command_id",
            "{$alias}.command_name AS cm_command_name",
            "{$alias}.command_line AS cm_command_line",
            "{$alias}.command_type AS cm_command_type",
            "{$alias}.enable_shell AS cm_enable_shell",
            "{$alias}.command_activate AS cm_command_activate",
            "{$alias}.command_locked AS cm_command_locked",
            "{$alias}.command_comment AS cm_command_comment",
        ];
    }

    public function update(Command $command): void
    {
        $commandId = $command->id();
        Assert::isInstanceOf($commandId, CommandId::class);

        $qb = $this->connection->createQueryBuilder();

        $qb->update(self::TABLE_NAME)
            ->set('command_name', ':name')
            ->set('command_line', ':line')
            ->set('command_type', ':type')
            ->set('enable_shell', ':enable_shell')
            ->set('command_activate', ':activate')
            ->set('command_comment', ':comment')
            ->set('connector_id', ':connector_id')
            ->where('command_id = :id')
            ->setParameter('id', $command->id()->value)
            ->setParameter('name', $command->name->value)
            ->setParameter('line', $command->commandLine->value)
            ->setParameter('type', $command->type->value)
            ->setParameter('enable_shell', $command->isShellEnabled ? 1 : 0)
            ->setParameter('activate', $command->isActivated ? 1 : 0)
            ->setParameter('comment', $command->comment->value ?? null)
            ->setParameter('connector_id', $command->connector instanceof Connector ? $command->connector->id()->value : null);

        $qb->executeStatement();
    }

    public function delete(Command $command): void
    {
        $commandId = $command->id();
        Assert::isInstanceOf($commandId, CommandId::class);

        $qb = $this->connection->createQueryBuilder();

        $qb->delete(self::TABLE_NAME)
            ->where('command_id = :id')
            ->setParameter('id', $command->id()->value);

        $qb->executeStatement();
    }

    public function filterByCriteria(QueryBuilder $qb, CommandCriteria $criteria): void
    {
        if ($nameCriteria = $criteria->getNames()) {
            foreach ($nameCriteria as $operator => $names) {
                if ($operator === CommandCriteria::OPERATOR_LIKE) {
                    $qb->andWhere($qb->expr()->or(...array_map(
                        static fn (string $name): string => $qb->expr()->like('cm.command_name', '"%' . $name . '%"'),
                        $names
                    )));

                    continue;
                }
                $qb->andWhere($qb->expr()->in(
                    'cm.command_name',
                    array_map(static fn (string $name): string => '"' . $name . '"', $names)
                ));
            }
        }

        if ($criteria->getTypes() !== []) {
            $qb->andWhere($qb->expr()->in(
                'cm.command_type',
                array_map(static fn (CommandTypeEnum $type): string => '"' . $type->value . '"', $criteria->getTypes())
            ));
        }

        if ($criteria->getStatus() !== null) {
            $qb->andWhere('cm.command_activate = :command_activate');
            $qb->setParameter('command_activate', $criteria->getStatus() ? '1' : '0');
        }
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createCommand(array $row): Command
    {
        $command = $this->transformer->transform($row);
        if (($connector = $this->connectorRepository->findByCommand($command)) instanceof Connector) {
            $command->addConnector($connector);

            return $command;
        }

        return $command;
    }

    private function countOnQueryBuilder(QueryBuilder $qb): int
    {
        $qb = clone $qb; // avoid modifying the initial query builder

        $count = $qb
            ->select('COUNT(DISTINCT cm.command_id)')
            ->setFirstResult(0) // reset any pagination
            ->setMaxResults(null)
            ->executeQuery()
            ->fetchOne();

        Assert::integer($count);

        return $count;
    }

    private function paginate(QueryBuilder $qb, CommandCriteria $criteria): void
    {
        if ($criteria->getPage() === null || $criteria->getItemsPerPage() === null) {
            return;
        }

        $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
            ->setMaxResults($criteria->getItemsPerPage());
    }
}
