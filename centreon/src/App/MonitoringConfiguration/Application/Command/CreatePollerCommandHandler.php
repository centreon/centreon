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

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Event\PollerCreated;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Service\PollerUidGenerator;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class CreatePollerCommandHandler
{
    public function __construct(
        private PollerRepository $repository,
        private EventBus $eventBus,
        private PollerUidGenerator $uidGenerator,
    ) {
    }

    public function __invoke(CreatePollerCommand $command): Poller
    {
        $uid = $this->uidGenerator->generate();

        $poller = new Poller(
            id: null,
            name: $command->name,
            address: $command->address,
            isCentral: false,
            isDefault: false,
            isActivated: true,
            pollerType: $command->pollerType,
            uid: $uid,
            globalMacros: new Collection([], GlobalMacro::class),
            gorgoneConfiguration: new GorgoneConfiguration(
                communicationType: $command->gorgoneCommunicationType,
            ),
            engineConfiguration: new EngineConfiguration(),
            brokerConfiguration: new BrokerConfiguration(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
        );

        $this->repository->add($poller);

        $this->eventBus->fire(new PollerCreated($poller, $command->creatorId));

        return $poller;
    }
}
