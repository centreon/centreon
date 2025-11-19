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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Event\CommandCreated;
use App\MonitoringConfiguration\Domain\Exception\CommandAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;
use Symfony\Component\Console\Exception\CommandNotFoundException;

#[AsCommandHandler]
final readonly class DuplicateCommandCommandHandler
{
    public function __construct(
        private CommandRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(DuplicateCommandCommand $duplicateCommandCommand): iterable
    {
        $originalCommand = $this->repository->getById(new CommandId($duplicateCommandCommand->commandId));

        for ($i = 1; $i <= $duplicateCommandCommand->nbDuplicates; $i++) {
            $newName = $this->generateDuplicateName($originalCommand->name->value);
            $duplicateName = new CommandName($newName);

            $duplicatedCommand = new Command(
                id: null,
                name: $duplicateName,
                type: $originalCommand->type,
                commandLine: $originalCommand->commandLine,
                isShellEnabled: $originalCommand->isShellEnabled,
                isActivated: $originalCommand->isActivated,
                isFromMonitoringConnector: $originalCommand->isFromMonitoringConnector,
                connector: $originalCommand->connector,
                comment: $originalCommand->comment,
            );

            $this->repository->add($duplicatedCommand);
            $this->eventBus->fire(new CommandCreated($duplicatedCommand, $duplicateCommandCommand->duplicatedBy));

            // Store both original and duplicated command info
            $result = [
                'originalCommand' => $originalCommand,
                'duplicatedCommand' => $duplicatedCommand,
            ];
        }

        return $result ?? [];
    }

    private function generateDuplicateName(string $originalName): string
    {
        $basePattern = $originalName . '_%d';
        $counter = 1;

        do {
            $candidateName = sprintf($basePattern, $counter);
            $duplicateName = new CommandName($candidateName);

            if ($this->repository->findOneByName($duplicateName) === null) {
                return $candidateName;
            }

            $counter++;
        } while ($counter <= 1000); // Safety limit to prevent infinite loops

        // If we can't find a unique name after 1000 attempts, add timestamp
        return $originalName . '_copy_' . time();
    }
}
