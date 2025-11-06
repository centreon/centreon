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
use App\MonitoringConfiguration\Domain\Event\CommandDeleted;
use App\MonitoringConfiguration\Domain\Exception\CommandCanNotBeDeletedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class DeleteCommandCommandHandler
{
    public function __construct(
        private CommandRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(DeleteCommandCommand $command): Command
    {
        $existingCommand = $this->repository->getById($command->id);

        if ($existingCommand->isFromMonitoringConnector) {
            throw new CommandCanNotBeDeletedException(['id' => $existingCommand->id()->value]);
        }

        $this->repository->delete($existingCommand);

        $this->eventBus->fire(new CommandDeleted($existingCommand, $command->updatedBy));

        return $existingCommand;
    }
}
