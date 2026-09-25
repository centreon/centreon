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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class HostTest extends TestCase
{
    public function testItRejectsAHostNamedAsBothParentAndChild(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->host(parentHostIds: [7], childHostIds: [7]);
    }

    public function testItAcceptsUnrelatedParentsAndChildren(): void
    {
        $host = $this->host(parentHostIds: [7], childHostIds: [8]);

        self::assertCount(1, $host->parentHostIds);
        self::assertCount(1, $host->childHostIds);
    }

    public function testItEnablesTheHost(): void
    {
        $host = $this->host(activated: false);

        $host->enable();

        self::assertTrue($host->activated);
    }

    public function testItDisablesTheHost(): void
    {
        $host = $this->host(activated: true);

        $host->disable();

        self::assertFalse($host->activated);
    }

    /**
     * @param list<int> $parentHostIds
     * @param list<int> $childHostIds
     */
    private function host(array $parentHostIds = [], array $childHostIds = [], bool $activated = true): Host
    {
        return new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: $activated,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            parentHostIds: $this->hostIds($parentHostIds),
            childHostIds: $this->hostIds($childHostIds),
        );
    }

    /**
     * @param list<int> $ids
     *
     * @return Collection<HostId>
     */
    private function hostIds(array $ids): Collection
    {
        return new Collection(array_map(static fn (int $id): HostId => new HostId($id), $ids), HostId::class);
    }
}
