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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class PatchHostCommandHandler
{
    public function __construct(
        private HostRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(PatchHostCommand $command): Host
    {
        // Viewer-scoped: an out-of-scope host reads as not found (see HostRepository::getById).
        $host = $this->repository->getById($command->id, $command->viewerId);

        // Skip when unchanged: avoids a spurious activity log line and poller reload.
        if ($host->activated === $command->activated) {
            return $host;
        }

        $command->activated ? $host->enable() : $host->disable();

        $this->repository->updateActivationStatus($command->id, $command->activated);

        // Drives the poller + ACL side effects, inside the transaction. The event carries the in-memory
        // aggregate, so a disable keeps the poller to reload even though the row is now off.
        $this->eventBus->fire(
            $command->activated
                ? new HostEnabled($host, $command->updatedBy)
                : new HostDisabled($host, $command->updatedBy),
        );

        return $host;
    }
}
