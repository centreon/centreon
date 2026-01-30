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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Event\CommandDuplicated;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\CommandCriteria;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class DuplicateCommandsCommandHandler
{
    public function __construct(
        private CommandRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    /**
     * @return array{
     *     duplicated?: array<Command>,
     *     missing?: array<int>,
     *     access_denied?: array<int>
     * }
     */
    public function __invoke(DuplicateCommandsCommand $duplicateCommandsCommand): array
    {
        $criteria  = new CommandCriteria();
        foreach ($duplicateCommandsCommand->commandIds as $id) {
            $criteria = $criteria->withId($id->value);
        }

        $originalCommands = $this->repository->findAll($criteria);
        $results = [];

        if (count($originalCommands) !== count($duplicateCommandsCommand->commandIds)) {
            $foundIds = [];
            foreach ($originalCommands as $command) {
                $foundIds[] = $command->id()->value;
            }
            $requestedIds = [];
            foreach ($duplicateCommandsCommand->commandIds as $id) {
                $requestedIds[] = $id->value;
            }
            $results['missing'] = array_diff($requestedIds, $foundIds);
        }

        $commandsToDuplicate = [];
        foreach ($originalCommands as $originalCommand) {
            if (! in_array($originalCommand->type->name, $duplicateCommandsCommand->allowedTypes, true)) {
                $results['access_denied'][] = $originalCommand->id()->value;
                continue;
            }

            $commandsToDuplicate[] = new Command(
                id: null,
                name: new CommandName($originalCommand->name->value . '_' . uniqid()),
                type: $originalCommand->type,
                commandLine: $originalCommand->commandLine,
                isShellEnabled: $originalCommand->isShellEnabled,
                isActivated: $originalCommand->isActivated,
                isFromMonitoringConnector: false,
                connector: $originalCommand->connector(),
                comment: $originalCommand->comment,
            );
        }

        if ($commandsToDuplicate === []) {
            return $results;
        }

        $this->repository->add(...$commandsToDuplicate);
        $this->eventBus->fire(
            new CommandDuplicated($commandsToDuplicate, $duplicateCommandsCommand->duplicatedBy)
        );
        $results['duplicated'] = $commandsToDuplicate;

        return $results;
    }
}
