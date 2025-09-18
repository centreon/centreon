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
use App\MonitoringConfiguration\Domain\Repository\CommandArgumentRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-import-type RowTypeAlias from DbalCommandArgumentRepository as CommandArgumentRowTypeAlias
 *
 * @phpstan-type RowTypeAlias = array{
 *   cmd_id: int,
 *   macro_name: string,
 *   macro_description: string,
 * } */
final readonly class DbalCommandArgumentRepository extends DbalRepository implements CommandArgumentRepository
{
    public const TABLE_NAME = 'command_arg_description';

    /**
     * @param TransformerInterface<RowTypeAlias, CommandArgument> $commandArgumentTransformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalCommandArgumentTransformer::class)]
        private TransformerInterface $commandArgumentTransformer,
    ) {
    }

    public function findAllByCommand(Command $command): Collection
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('cmd_id', 'macro_name', 'macro_description')
            ->from(self::TABLE_NAME)
            ->where('cmd_id  = :command_id')
            ->setParameter('command_id', $command->id()->value);

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $this->createCommandArguments($rows);
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<CommandArgument>
     */
    private function createCommandArguments(array $rows): Collection
    {
        $commandArguments = [];
        foreach ($rows as $commandArgumentRow) {
            $commandArguments[] = $this->commandArgumentTransformer->transform($commandArgumentRow);
        }

        return new Collection($commandArguments, CommandArgument::class);
    }
}
