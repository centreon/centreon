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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

final class FakeHostRepository implements HostRepository
{
    /** @var array<int, Host> */
    public array $hosts = [];

    /**
     * When set, {@see getById()} with a non-null viewer only returns ids listed here; any other
     * reads as not found. Null means the viewer sees everything — used to simulate ACL scoping.
     *
     * @var list<int>|null
     */
    public ?array $accessibleHostIds = null;

    /** @var list<array{id: int, activated: bool}> */
    public array $activationUpdates = [];

    /**
     * Host id to its parents, mirroring `host_hostparent_relation`, which the real repository
     * writes from both sides: a host created with children becomes their parent in the graph.
     *
     * @var array<int, list<int>>
     */
    private array $parentIds = [];

    /**
     * Store a host under a fixed id, so a test can target it by a known id.
     */
    public function seed(Host $host, int $id): Host
    {
        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($host, new HostId($id));

        $this->hosts[$id] = $host;

        return $host;
    }

    public function add(Host $host): void
    {
        do {
            $id = mt_rand();
        } while (isset($this->hosts[$id]));

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($host, new HostId($id));

        $this->hosts[$id] = $host;

        foreach ($host->parentHostIds as $parentId) {
            $this->parentIds[$id][] = $parentId->value;
        }

        foreach ($host->childHostIds as $childId) {
            $this->parentIds[$childId->value][] = $id;
        }
    }

    public function getById(HostId $id, ?UserId $viewerId = null): Host
    {
        $host = $this->hosts[$id->value] ?? null;
        if ($host === null) {
            throw new HostNotFoundException([$id->value], 'id');
        }

        if (
            $viewerId instanceof UserId
            && $this->accessibleHostIds !== null
            && ! in_array($id->value, $this->accessibleHostIds, true)
        ) {
            throw new HostNotFoundException([$id->value], 'id');
        }

        return $host;
    }

    public function updateActivationStatus(HostId $id, bool $activated): void
    {
        $this->activationUpdates[] = ['id' => $id->value, 'activated' => $activated];

        if (isset($this->hosts[$id->value])) {
            $this->hosts[$id->value]->activated = $activated;
        }
    }

    public function isNameUsedByHostOrTemplate(HostName $name): bool
    {
        foreach ($this->hosts as $host) {
            if ($host->name->value === $name->value) {
                return true;
            }
        }

        return false;
    }

    public function findAll(?HostCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        return new Collection(array_values($this->hosts), Host::class);
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        $names = [];
        foreach ($ids as $id) {
            if (isset($this->hosts[$id->value])) {
                $names[$id->value] = $this->hosts[$id->value]->name;
            }
        }

        return new Collection($names, HostName::class);
    }

    public function findAncestorIds(Collection $ids): Collection
    {
        $seen = [];
        $queue = array_map(static fn (HostId $id): int => $id->value, $ids->toArray());

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            foreach ($this->parentIds[$current] ?? [] as $parentId) {
                $queue[] = $parentId;
            }
        }

        return new Collection(
            array_map(static fn (int $id): HostId => new HostId($id), array_keys($seen)),
            HostId::class,
        );
    }
}
