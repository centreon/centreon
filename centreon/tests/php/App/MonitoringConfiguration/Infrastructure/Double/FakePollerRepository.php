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

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

final class FakePollerRepository implements PollerRepository
{
    /** @var array<int, Poller> */
    public array $pollers = [];

    public function add(Poller $poller): void
    {
        do {
            $id = mt_rand();
        } while (isset($this->pollers[$id]));

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($poller, new PollerId($id));

        $this->pollers[$id] = $poller;
    }

    public function findOneByName(PollerName $name): ?Poller
    {
        foreach ($this->pollers as $poller) {
            if ($poller->name->value === $name->value) {
                return $poller;
            }
        }

        return null;
    }

    public function findOneByAddress(PollerAddress $address): ?Poller
    {
        foreach ($this->pollers as $poller) {
            if ($poller->address->value === $address->value) {
                return $poller;
            }
        }

        return null;
    }

    public function findAllByGlobalMacro(GlobalMacro $globalMacro): Collection
    {
        return new Collection([], Poller::class);
    }

    public function get(PollerId $pollerId): Poller
    {
        return $this->pollers[$pollerId->value] ?? throw new PollerNotFoundException(['id' => $pollerId->value]);
    }

    public function withCmaCertificates(): self
    {
        return clone $this;
    }

    public function getCentralAddress(): PollerAddress
    {
        return new PollerAddress('192.168.1.100');
    }
}
