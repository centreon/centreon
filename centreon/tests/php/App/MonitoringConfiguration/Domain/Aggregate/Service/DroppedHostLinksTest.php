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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\DroppedHostLinks;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class DroppedHostLinksTest extends TestCase
{
    public function testTheHostsTheServiceKeepsOrLeavesAreNotDropped(): void
    {
        $links = $this->links([1 => new HostState(true, new PollerId(1)), 2 => new HostState(true, new PollerId(2))]);

        $dropped = DroppedHostLinks::of($links, $this->service(1, true, 1), $this->service(2, true, 2));

        self::assertSame([], $dropped->hostIds);
        self::assertSame([], $dropped->pollerIds);
    }

    public function testADroppedHostOnAnotherPollerFlagsItOnce(): void
    {
        $links = $this->links([
            1 => new HostState(true, new PollerId(1)),
            3 => new HostState(true, new PollerId(3)),
            4 => new HostState(true, new PollerId(3)),
        ]);

        $dropped = DroppedHostLinks::of($links, $this->service(1, true, 1), $this->service(1, true, 1));

        self::assertSame([3, 4], $this->values($dropped->hostIds));
        self::assertSame([3], $this->values($dropped->pollerIds));
    }

    public function testADroppedHostOnAnAlreadyFlaggedPollerAddsNoPoller(): void
    {
        $links = $this->links([1 => new HostState(true, new PollerId(1)), 3 => new HostState(true, new PollerId(2))]);

        $dropped = DroppedHostLinks::of($links, $this->service(1, true, 1), $this->service(2, true, 2));

        self::assertSame([3], $this->values($dropped->hostIds));
        self::assertSame([], $dropped->pollerIds);
    }

    public function testADisabledDroppedHostAddsNoPoller(): void
    {
        $links = $this->links([1 => new HostState(true, new PollerId(1)), 3 => new HostState(false, new PollerId(3))]);

        $dropped = DroppedHostLinks::of($links, $this->service(1, true, 1), $this->service(1, true, 1));

        self::assertSame([3], $this->values($dropped->hostIds));
        self::assertSame([], $dropped->pollerIds);
    }

    public function testTheCurrentHostsPollerIsFlaggedThroughADroppedHostWhenTheCurrentHostIsDisabled(): void
    {
        $links = $this->links([1 => new HostState(false, new PollerId(1)), 3 => new HostState(true, new PollerId(1))]);

        $dropped = DroppedHostLinks::of($links, $this->service(1, false, 1), $this->service(1, false, 1));

        self::assertSame([1], $this->values($dropped->pollerIds));
    }

    /**
     * @param array<int, HostState> $states
     *
     * @return Collection<HostState>
     */
    private function links(array $states): Collection
    {
        return new Collection($states, HostState::class);
    }

    private function service(int $hostId, bool $hostActivated, int $pollerId): Service
    {
        return new Service(
            id: new ServiceId(1),
            name: new ServiceName('ping'),
            hostId: new HostId($hostId),
            hostActivated: $hostActivated,
            hostPollerId: new PollerId($pollerId),
        );
    }

    /**
     * @param list<HostId|PollerId> $ids
     *
     * @return list<int>
     */
    private function values(array $ids): array
    {
        return array_map(static fn (HostId|PollerId $id): int => $id->value, $ids);
    }
}
