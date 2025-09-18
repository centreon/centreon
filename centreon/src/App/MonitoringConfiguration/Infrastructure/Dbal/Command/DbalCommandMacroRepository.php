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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Repository\CommandMacroRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   command_macro_id: int,
 *   command_macro_name: string,
 *   command_macro_type: string,
 *   command_macro_desciption: string,
 *   command_command_id: int
 * } */
final readonly class DbalCommandMacroRepository extends DbalRepository implements CommandMacroRepository
{
    public const TABLE_NAME = 'on_demand_macro_command';

    /**
     * @param TransformerInterface<RowTypeAlias, CommandMacro> $commandMacroTransformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalCommandMacroTransformer::class)]
        private TransformerInterface $commandMacroTransformer,
    ) {
    }

    public function findAllByCommand(Command $command): Collection
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('command_macro_id', 'command_macro_name', 'command_macro_type', 'command_macro_desciption', 'command_command_id')
            ->from(self::TABLE_NAME)
            ->where('command_command_id = :command_id')
            ->setParameter('command_id', $command->id()->value);

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $this->createCommandMacros($rows);
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<CommandMacro>
     */
    private function createCommandMacros(array $rows): Collection
    {
        $commandMacros = [];
        foreach ($rows as $commandMacroRow) {
            $commandMacros[] = $this->commandMacroTransformer->transform($commandMacroRow);
        }

        return new Collection($commandMacros, CommandMacro::class);
    }
}
