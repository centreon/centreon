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

use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Event\ServiceDeleted;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class DeleteHostTemplateServiceCommandHandler
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private EventBus $eventBus,
    ) {
    }

    /**
     * Loaded first: the deletion event needs the host's poller, unreadable once the row is gone. A service
     * no longer on the host has nothing left to clean. The purge is best effort: the host update that
     * removed the template is already saved, and an orphan secret is harmless.
     */
    public function __invoke(DeleteHostTemplateServiceCommand $command): void
    {
        $service = $this->serviceRepository->findOneOnHost($command->serviceId, $command->hostId);
        if (! $service instanceof Service) {
            return;
        }

        $this->serviceRepository->remove($service);

        $this->eventBus->fire(new ServiceDeleted($service, $command->deletedBy->value));
        $this->eventBus->fire(new ServiceVaultPurgeRequested($service, bestEffort: true));
    }
}
