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
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ConnectorRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class CreateServiceCategoryCommandHandler
{
    public function __construct(
        private CommandRepository $repository,
        private ConnectorRepository $connectorRepository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(CreateCommandCommand $command): Command
    {
        if ($command->connectorId !== null) {
            $connector = $this->connectorRepository->get($command->connectorId);
        }
        $command = new Command(
            id: null,
            name: $command->name,
            type: $command->type,
            commandLine: $command->commandLine,
            isShellEnabled: $command->isShellEnabled,
            connector: $connector ?? null,
        );

        /* if ($this->repository->findOneByName($command->name) instanceof ServiceCategory) { */
        /*     throw new ServiceCategoryAlreadyExistsException(['name' => $command->name->value]); */
        /* } */

        $this->repository->add($command);

        // Ensure the repository assigned an ID
        $command->id();

        /* $this->eventBus->fire(new ServiceCategoryCreated($command, $command->creatorId)); */

        /* return $command; */
    }
}
