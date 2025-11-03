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
use App\MonitoringConfiguration\Domain\Aggregate\Connector\Connector;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   command_id: int,
 *   command_name: string,
 *   command_line: string,
 *   command_type: int,
 *   enable_shell: bool,
 *   command_activate: bool,
 *   command_locked: bool,
 *   command_comment: string|null,
 *   connector_id: int|null,
 * }
 */
final readonly class DbalCommandRepository extends DbalRepository implements CommandRepository
{
    public const TABLE_NAME = 'command';
    public const CONNECTOR_JON_TABLE_NAME = 'connector';

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
            ->leftJoin('cm', self::CONNECTOR_JON_TABLE_NAME, 'c', 'cm.connector_id = c.id')
            ->where('command_id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);
        /** @var RowTypeAlias $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if (! $row) {
            throw new CommandNotFoundException(['id' => $id->value]);
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

    public function findAllByConnector(Connector $connector): Collection
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns(), ...DbalConnectorRepository::getSelectColumns())
            ->from(self::CONNECTOR_JON_TABLE_NAME, 'c')
            ->rightJoin('c', self::TABLE_NAME, 'cm', 'c.id = cm.connector_id')
            ->where('cm.connector_id = :connector_id')
            ->setParameter('connector_id', $connector->id()->value);

        /**
         * @var array<JoinRowTypeAlias> $rows
         */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $connectorRows = [];
        $commandRows = [];
        foreach ($rows as $row) {
            $connectorId = $row['c_id'];
            $commandId = $row['cm_command_id'];

            $connectorRows[$connectorId] ??= $row;

            if ($commandId !== null) {
                /** @var GlobalMacroRowTypeAlias $commandRow */
                $commandRow = [
                    'cm_command_id' => $row['cm_command_id'],
                    'cm_command_name' => $row['cm_command_name'],
                    'cm_command_line' => $row['cm_command_line'],
                    'cm_command_type' => $row['cm_command_type'],
                    'cm_enable_shell' => $row['cm_enable_shell'],
                    'cm_command_activate' => $row['cm_command_activate'],
                    'cm_command_locked' => $row['cm_command_locked'],
                    'cm_command_comment' => $row['cm_command_comment'],
                ];
                $commandRows[$connectorId][$commandId] = $commandRow;
            }
        }

        return $this->createCommands($connectorRows, $commandRows);
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

    /**
     * @param array<RowTypeAlias> $connectorRows
     * @param array<array<GlobalMacroRowTypeAlias>>|null $commandRowsByConnectorId
     *
     * @return Collection<Command>
     */
    private function createCommands(array $connectorRows, ?array $commandRowsByConnectorId = null): Collection
    {
        // fetch all global macros of given pollers
        if ($commandRowsByConnectorId) {
            $commandQb = $this->connection->createQueryBuilder();
            $commandQb->select('cm.command_id', ...DbalConnectorRepository::getSelectColumns())
                ->from(self::TABLE_NAME, 'cm')
                ->innerJoin('cm', self::CONNECTOR_JON_TABLE_NAME, 'c', 'cm.connector_id = c.id')
                ->where($commandQb->expr()->in('c.id', array_map('strval', array_column($connectorRows, 'c_id'))));

            /**
             * @var array<JoinRowTypeAlias> $commandRows
             */
            $commandRows = $commandQb->executeQuery()->fetchAllAssociative();
            $connectorRowsRowsByCommandId = [];
            foreach ($commandRows as $commandRow) {
                $connectorRowsRowsByCommandId['command_id'][] = $commandRow;
            }
        }
        $commands = [];
        foreach ($connectorRows as $row) {
            $commandId = $row['cm_command_id'];
            $commands[$commandId] ??= $this->createCommand(
                $row,
            );
        }

        return new Collection(array_values($commands), Command::class);
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createCommand(array $row): Command
    {
        $command = $this->transformer->transform($row);
        $connector = $this->connectorRepository->findByCommand($command);

        /** @var CommandId $id */
        $id = $command->id();

        // create a new instance with same values but with the poller collection
        return new Command(
            id: $id,
            name: $command->name,
            type: $command->type,
            commandLine: $command->commandLine,
            isShellEnabled: $command->isShellEnabled,
            isActivated: $command->isActivated,
            isFromMonitoringConnector: $command->isFromMonitoringConnector,
            comment: $command->comment,
            connector: $connector,
        );
    }
}
