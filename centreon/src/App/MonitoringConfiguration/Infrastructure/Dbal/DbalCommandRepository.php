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
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorId;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

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

        $qb->select(
            'command_id',
            'command_name',
            'command_line',
            'command_type',
            'enable_shell',
            'command_activate',
            'command_locked',
            'command_comment',
            'connector_id',
        )
            ->from(self::TABLE_NAME)
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

        $qb->select(
            'command_id',
            'command_name',
            'command_line',
            'command_type',
            'enable_shell',
            'command_activate',
            'command_locked',
            'command_comment',
            'connector_id',
        )
            ->from(self::TABLE_NAME)
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

    public function exists(CommandId $id): bool
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('1')
            ->from(self::TABLE_NAME)
            ->where('command_id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        return (bool) $qb->executeQuery()->fetchOne();
    }

    public function update(Command $command): void
    {
        $commandId = $command->id();
        Assert::isInstanceOf($commandId, CommandId::class);

        if (! $this->exists($commandId)) {
            throw new CommandNotFoundException(['id' => $commandId->value]);
        }

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
            ->setParameter('connector_id', $command->connector instanceof Connector ? $command->connector->id->value : null);

        $qb->executeStatement();
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createCommand(array $row): Command
    {
        $command = $this->transformer->transform($row);

        if ($row['connector_id'] !== null) {
            $connectorId = $row['connector_id'];
            $connector = $this->connectorRepository->findById(new ConnectorId($connectorId));
            if ($connector instanceof Connector) {
                $command->addConnector($connector);
            }
        }

        return $command;
    }
}
