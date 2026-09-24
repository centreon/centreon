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
        // Scoped to the requester: a restricted viewer targeting a host outside their ACL scope
        // gets the same not-found error as a truly nonexistent host, never a leak of its existence.
        $host = $this->repository->getById($command->id, $command->viewerId);

        // No-op when already in the requested state: no write, no activity log, no reload flag.
        // Toggling to the current value must not produce noise or a spurious poller reload.
        if ($host->activated === $command->activated) {
            return $host;
        }

        $command->activated ? $host->enable() : $host->disable();

        $this->repository->updateActivationStatus($command->id, $command->activated);

        // The poller and ACL side effects are driven off this event, inside the same transaction.
        // The event carries the in-memory aggregate, which still holds its poller: a disable never
        // loses track of the poller that must reload, even though the row is now deactivated.
        $this->eventBus->fire(
            $command->activated
                ? new HostEnabled($host, $command->updatedBy)
                : new HostDisabled($host, $command->updatedBy),
        );

        return $host;
    }
}
