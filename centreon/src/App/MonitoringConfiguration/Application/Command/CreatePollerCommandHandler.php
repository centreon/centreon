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
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUuid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Exception\PollerAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Collection;
use Symfony\Component\Uid\Uuid;

#[AsCommandHandler]
final readonly class CreatePollerCommandHandler
{
    public function __construct(
        private PollerRepository $repository,
    ) {
    }

    public function __invoke(CreatePollerCommand $command): Poller
    {
        if ($this->repository->findOneByName($command->name) instanceof Poller) {
            throw new PollerAlreadyExistsException(['name' => $command->name->value]);
        }

        $address = $command->address ?? new PollerAddress($command->name->value);

        if ($this->repository->findOneByAddress($address) instanceof Poller) {
            throw new PollerAlreadyExistsException(['address' => $address->value]);
        }
        $uuid = new PollerUuid(Uuid::v7()->toRfc4122());

        $poller = new Poller(
            id: null,
            name: $command->name,
            address: $address,
            isCentral: false,
            isDefault: false,
            isActivated: true,
            pollerType: $command->pollerType,
            uuid: $uuid,
            globalMacros: new Collection([], GlobalMacro::class),
            gorgoneConfiguration: new GorgoneConfiguration(),
            engineConfiguration: new EngineConfiguration(),
            brokerConfiguration: new BrokerConfiguration(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
        );

        $this->repository->add($poller);

        return $poller;
    }
}
