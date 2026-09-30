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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Double;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

final class FakeServiceRepository implements ServiceRepository
{
    /** @var array<int, Service> */
    public array $services = [];

    public function add(Service $service): void
    {
        do {
            $id = mt_rand();
        } while (isset($this->services[$id]));

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($service, new ServiceId($id));

        $this->services[$id] = $service;
    }

    public function findExclusivelyLinkedToHostId(HostId $hostId): Collection
    {
        $services = array_values(array_filter(
            $this->services,
            static fn (Service $service): bool => $service->hostId->value === $hostId->value,
        ));

        return new Collection($services, Service::class);
    }

    public function remove(Service $service): void
    {
        unset($this->services[$service->id()->value]);
    }
}
