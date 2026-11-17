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

use App\MonitoringConfiguration\Application\Service\HostServicesDeployer;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Event\ServiceCreated;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\InvalidServiceTemplateAliasException;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;

/**
 * One event per created service, never one for all: the ACL and poller listeners handle one service each. They are
 * fired once the deployer has written every service, so a failed write leaves no real-time side effect behind.
 */
#[AsCommandHandler]
final readonly class DeployHostServicesFromTemplatesCommandHandler
{
    public function __construct(
        private HostServicesDeployer $deployer,
        private EventBus $eventBus,
    ) {
    }

    /**
     * @throws HostNotFoundException
     * @throws InvalidServiceTemplateAliasException
     *
     * @return Collection<Service> the created services, possibly none
     */
    public function __invoke(DeployHostServicesFromTemplatesCommand $command): Collection
    {
        $services = $this->deployer->deploy($command->hostId, $command->actor);

        foreach ($services as $service) {
            $this->eventBus->fire(new ServiceCreated($service, $command->actor->userId->value));
        }

        return $services;
    }
}
