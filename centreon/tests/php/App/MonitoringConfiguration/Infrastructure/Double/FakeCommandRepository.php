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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Double;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class FakeCommandRepository implements CommandRepository
{

    /** @var array<int, Command> */
    public array $commands = [];

    public function getById(CommandId $id): Command
    {
        return $this->commands[$id->value] ?? throw new CommandNotFoundException(['id' => $id->value]);
    }

    public function findOneByName(CommandName $name): ?Command
    {
        foreach ($this->commands as $command) {
            if ($command->name->value === $name->value) {
                return $command;
            }
        }

        return null;
    }

    public function add(Command $command): void
    {
        do {
            $id = mt_rand();
        } while (isset($this->commands[$id]));

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($command, new CommandId($id));

        $this->commands[$id] = $command;
    }
}
