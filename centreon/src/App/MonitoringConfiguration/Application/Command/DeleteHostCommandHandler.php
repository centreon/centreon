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
use App\MonitoringConfiguration\Domain\Event\HostDeleted;
use App\MonitoringConfiguration\Domain\Event\ServiceDeleted;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class DeleteHostCommandHandler
{
    public function __construct(
        private HostRepository $hostRepository,
        private ServiceRepository $serviceRepository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(DeleteHostCommand $command): DeleteHostResult
    {
        $host = $this->hostRepository->findOne($command->id, $command->viewerId);
        if (! $host instanceof Host) {
            throw new HostNotFoundException([$command->id->value], 'id');
        }

        $deletedServices = $this->serviceRepository->findExclusivelyLinkedToHostId($command->id);
        foreach ($deletedServices as $service) {
            $this->serviceRepository->remove($service);
            $this->eventBus->fire(new ServiceDeleted($service, $command->deletedBy));
        }

        $this->hostRepository->remove($host);

        $this->eventBus->fire(new HostDeleted($host, $command->deletedBy));

        return new DeleteHostResult($host, $deletedServices);
    }
}
