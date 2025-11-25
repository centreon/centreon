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

namespace App\MonitoringConfiguration\Domain\Repository;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\Criteria\CommandCriteria;
use App\Shared\Domain\Collection;

interface CommandRepository
{
    /**
     * @throws CommandNotFoundException
     */
    public function getById(CommandId $id): Command;

    public function findOneByName(CommandName $name): ?Command;

    public function add(Command ...$commands): void;

    public function update(Command $command): void;

    public function delete(Command $command): void;

    /**
     * @return \IteratorAggregate<int, Command>&\Countable
     */
    public function findAll(?CommandCriteria $criteria): \IteratorAggregate&\Countable;

    /**
     * @param array<int> $ids
     * @return Collection<Command>
     */
    public function findByIds(array $ids): Collection;

    /**
     * @param array<CommandId> $commandIds
     *
     * @return array<int, CommandResourceCount>
     */
    public function countLinkedResources(array $commandIds): array;
}
