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
use App\MonitoringConfiguration\Domain\Event\CommandCreated;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;
use Webmozart\Assert\InvalidArgumentException;

#[AsCommandHandler]
final readonly class DuplicateCommandsCommandHandler
{
    public function __construct(
        private CommandRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    /**
     * @return array<string, array<Command|int|string>>
     */
    public function __invoke(DuplicateCommandsCommand $duplicateCommandsCommand): array
    {
        $results = [];
        $originalCommands = $this->repository->findByIds($duplicateCommandsCommand->commandIds);

        if (count($originalCommands) !== count($duplicateCommandsCommand->commandIds)) {
            $foundIds = array_map(
                fn (Command $command): int => $command->id()->value,
                $originalCommands->toArray()
            );
            $missingIds = array_diff($duplicateCommandsCommand->commandIds, $foundIds);
            $results['missing'] = $missingIds;
        }
        $commandToDuplicate = [];
        foreach ($originalCommands as $originalCommand) {
            if (! in_array($originalCommand->type->name, $duplicateCommandsCommand->allowedTypes, true)) {
                $results['access_denied'][] = $originalCommand->id()->value;
                continue;
            }

            $commandToDuplicate[] = new Command(
                id: null,
                name: new CommandName($originalCommand->name->value . '_' . uniqid()),
                type: $originalCommand->type,
                commandLine: $originalCommand->commandLine,
                isShellEnabled: $originalCommand->isShellEnabled,
                isActivated: $originalCommand->isActivated,
                isFromMonitoringConnector: $originalCommand->isFromMonitoringConnector,
                connector: $originalCommand->connector(),
                comment: $originalCommand->comment,
            );
        }

        $this->repository->addMultiple($commandToDuplicate);
        $results['duplicated'] = $commandToDuplicate;

        return $results;
    }
}
