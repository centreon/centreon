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
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;

final class FakeServiceRepository implements ServiceRepository
{
    /** @var array<int, Service> */
    public array $services = [];

    /** @var array<int, int> the service template of a service, indexed by service id */
    public array $serviceTemplateIdOf = [];

    /** @var list<Service> what {@see findAll()} returns, one per listed (host, service) pair */
    public array $listed = [];

    /** @var list<ServiceCriteria> */
    public array $requestedCriteria = [];

    /** @var list<int> ids of stored services that {@see findOne()} hides from an actor whose access is restricted */
    public array $outOfRestrictedScopeIds = [];

    /** @var list<array{id: int, activated: bool}> */
    public array $activationUpdates = [];

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

    public function findFromServiceTemplates(HostId $hostId, array $serviceTemplateIds): Collection
    {
        $services = array_values(array_filter(
            $this->services,
            fn (Service $service): bool => $service->hostId->value === $hostId->value
                && in_array($this->serviceTemplateIdOf[$service->id()->value] ?? null, $serviceTemplateIds, true),
        ));

        return new Collection($services, Service::class);
    }

    public function remove(Service $service): void
    {
        unset($this->services[$service->id()->value]);
    }

    /**
     * Returns {@see $listed} as it is: filtering, sorting and scoping are SQL, pinned by the integration tests
     * of the real repository. Records the criteria it is asked with, so a caller's mapping can be checked.
     */
    public function findAll(ServiceCriteria $criteria): \IteratorAggregate&\Countable
    {
        $this->requestedCriteria[] = $criteria;

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            return new Collection($this->listed, Service::class);
        }

        return new InMemoryPaginator(
            new Collection(array_slice($this->listed, $pagination->getOffset(), $pagination->itemsPerPage), Service::class),
            count($this->listed),
            $pagination->page,
            $pagination->itemsPerPage,
        );
    }

    /**
     * Visibility is SQL, pinned by the integration tests of the real repository: here a stored service is found
     * unless it is listed in {@see $outOfRestrictedScopeIds} and the actor's access is restricted.
     */
    public function findOne(ServiceId $id, ActingUser $actor): ?Service
    {
        $service = $this->services[$id->value] ?? null;
        if ($service === null) {
            return null;
        }

        if (! $actor->hasUnrestrictedResourceAccess() && in_array($id->value, $this->outOfRestrictedScopeIds, true)) {
            return null;
        }

        return $service;
    }

    /**
     * Only records the write: the stored aggregate is the caller's, which changes it itself, so a caller that
     * forgets to do so is caught.
     */
    public function updateActivationStatus(ServiceId $id, bool $activated): void
    {
        $this->activationUpdates[] = ['id' => $id->value, 'activated' => $activated];
    }
}
