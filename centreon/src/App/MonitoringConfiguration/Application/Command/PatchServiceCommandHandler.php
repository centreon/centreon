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
use App\MonitoringConfiguration\Domain\Event\ServiceDisabled;
use App\MonitoringConfiguration\Domain\Event\ServiceEnabled;
use App\MonitoringConfiguration\Domain\Exception\ServiceNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\NoValue;

#[AsCommandHandler]
final readonly class PatchServiceCommandHandler
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(PatchServiceCommand $command): void
    {
        // Actor-scoped: findOne returns null for a service outside the actor's ACL scope, the same as a
        // nonexistent one, so a restricted actor can never tell them apart.
        $service = $this->serviceRepository->findOne($command->id, $command->actor);
        if (! $service instanceof Service) {
            throw new ServiceNotFoundException([$command->id->value], 'id');
        }

        $this->changeActivation($service, $command);
    }

    /**
     * Nothing is written when the activation is not given or is already the requested one. Like for hosts, a
     * plain enable/disable is a narrow write with its own event.
     */
    private function changeActivation(Service $service, PatchServiceCommand $command): void
    {
        if ($command->activated instanceof NoValue || $command->activated === $service->activated) {
            return;
        }

        $command->activated ? $service->enable() : $service->disable();
        $this->serviceRepository->updateActivationStatus($service->id(), $service->activated);
        $this->eventBus->fire(
            $service->activated
                ? new ServiceEnabled($service, $command->actor->userId->value)
                : new ServiceDisabled($service, $command->actor->userId->value),
        );
    }
}
