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

namespace App\MonitoringConfiguration\Infrastructure\Dbal\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandArgument;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandArgumentDescription;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandArgumentName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalCommandRepository extends DbalRepository implements CommandRepository
{
    public const TABLE_NAME = 'command';
    public const CONNECTOR_TABLE_NAME = 'connector';
    public const GRAPH_TEMPLATE_TABLE_NAME = 'giv_graphs_template';

    /**
     * @param TransformerInterface<RowTypeAlias, Command> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalCommandTransformer::class)]
        private TransformerInterface $commandTransformer,

        private DbalCommandMacroRepository $commandMacroRepository,

        private DbalCommandArgumentRepository $commandArgumentRepository,
    ) {
    }

    public function getById(CommandId $id): Command
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(
            'c.command_id',
            'c.command_name',
            'c.command_line',
            'c.command_example',
            'c.command_type',
            'c.enable_shell',
            'c.command_activate',
            'c.command_locked',
            'c.command_comment',
            'c.connector_id',
            'connector.name AS connector_name',
            'NULLIF(c.graph_id, 0) AS graph_template_id', //should fix the create for the null graph template that is 0 in db + upgrade script too
            'graph.name AS graph_template_name'
        )
            ->from(self::TABLE_NAME, 'c')
            ->leftJoin('c', self::CONNECTOR_TABLE_NAME, 'connector', 'c.connector_id = connector.id')
            ->leftJoin('c', self::GRAPH_TEMPLATE_TABLE_NAME, 'graph', 'c.graph_id = graph.graph_id')
            ->where('c.command_id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        $row = $qb->executeQuery()->fetchAssociative();

        if (! $row) {
            return null;
        }

        return $this->createCommand($row);
    }

    private function createCommand(array $row): Command
    {
        $command = $this->commandTransformer->transform($row);
        $commandMacros = $this->commandMacroRepository->findAllByCommand($command);
        $commandArguments = $this->commandArgumentRepository->findAllByCommand($command);

        foreach ($commandMacros as $commandMacro) {
            $command->addCommandMacro(
                $commandMacro
            );
        }

        foreach ($commandArguments as $commandArgument) {
            $command->addCommandArgument(
                $commandArgument
            );
        }

        return $command;
    }
}
