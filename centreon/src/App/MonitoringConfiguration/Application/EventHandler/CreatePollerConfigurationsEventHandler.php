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

namespace App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Application\Command\CreateBrokerConfigurationCommand;
use App\MonitoringConfiguration\Application\Command\CreateEngineConfigurationCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\PollerCreated;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Event\AsEventHandler;

#[AsEventHandler]
final readonly class CreatePollerConfigurationsEventHandler
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(PollerCreated $event): void
    {
        /** @var Poller $poller */
        $poller = $event->aggregate;

        /** @var PollerId $pollerId */
        $pollerId = $poller->id();

        $this->commandBus->execute(
            new CreateEngineConfigurationCommand(
                pollerId: $pollerId,
                pollerName: $poller->name->value,
            ),
        );

        // The broker output must dial the Central; its address is the value the operator supplied
        // at poller creation (persisted in platform_topology.central_address), so it is always set
        // here. The aggregate only allows it to be null when hydrating pre-existing pollers, and an
        // empty address would persist a broker output unable to reach the Central. It is forwarded
        // as the value object so the broker output can take its bare host (on-prem) or derive the
        // gateway host from host + platform path (cloud).
        if (! $poller->centralAddress instanceof CentralAddress) {
            throw new \LogicException('A newly created poller must carry a central address.');
        }

        $this->commandBus->execute(
            new CreateBrokerConfigurationCommand(
                pollerId: $pollerId,
                pollerName: $poller->name->value,
                centralAddress: $poller->centralAddress,
            ),
        );
    }
}
