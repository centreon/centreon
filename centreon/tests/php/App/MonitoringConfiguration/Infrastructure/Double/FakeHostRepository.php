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
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

final class FakeHostRepository implements HostRepository
{
    /** @var array<int, Host> */
    public array $hosts = [];

    public function add(Host $host): void
    {
        do {
            $id = mt_rand();
        } while (isset($this->hosts[$id]));

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($host, new HostId($id));

        $this->hosts[$id] = $host;
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
}
