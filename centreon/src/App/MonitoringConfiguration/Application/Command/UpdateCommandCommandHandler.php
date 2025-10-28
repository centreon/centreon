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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\Connector;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorId;
use App\MonitoringConfiguration\Domain\Event\CommandUpdated;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ConnectorRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class UpdateCommandCommandHandler
{
    public function __construct(
        private CommandRepository $commandRepository,
        private ConnectorRepository $connectorRepository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(UpdateCommandCommand $command): Command
    {
        $existingCommand = $this->commandRepository->getById($command->id);

        if ($command->name instanceof CommandName) {
            $existingCommand->updateName($command->name);
        }

        if ($command->type instanceof CommandTypeEnum) {
            $existingCommand->updateType($command->type);
        }

        if ($command->commandLine instanceof CommandLine) {
            $existingCommand->updateCommandLine($command->commandLine);
        }

        if ($command->comment instanceof CommandComment) {
            $existingCommand->updateComment($command->comment);
        }

        if ($command->isShellEnabled !== null) {
            $command->isShellEnabled
                ? $existingCommand->enableShell()
                : $existingCommand->disableShell();
        }

        if ($command->isActivated !== null) {
            $command->isActivated
                ? $existingCommand->enable()
                : $existingCommand->disable();
        }

        if ($command->connectorId instanceof ConnectorId) {
            $connector = $this->connectorRepository->findById($command->connectorId);
            if ($connector instanceof Connector) {
                $existingCommand->addConnector($connector);
            }
        } else {
            $existingCommand->removeConnector();
        }

        $this->commandRepository->update($existingCommand);

        $this->eventBus->fire(new CommandUpdated($existingCommand, $command->updatedBy));

        return $existingCommand;
    }
}
