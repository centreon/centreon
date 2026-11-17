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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Collection;

/**
 * The direct host links a saved service loses besides the one to its previous host: a service linked to several
 * hosts keeps only the host it is saved with. Their pollers to flag follow {@see HostState::pollerToFlag()}, each
 * once, without the pollers of the service's previous and current hosts, which are flagged on their own.
 */
final readonly class DroppedHostLinks
{
    /**
     * @param list<HostId> $hostIds
     * @param list<PollerId> $pollerIds
     */
    public function __construct(
        public array $hostIds = [],
        public array $pollerIds = [],
    ) {
    }

    /**
     * @param Collection<HostState> $storedLinks the hosts the service is directly linked to before the save, indexed
     *                                           by host id
     */
    public static function of(Collection $storedLinks, Service $before, Service $after): self
    {
        $keptHostIds = [$before->hostId->value, $after->hostId->value];
        $flaggedPollerIds = array_map(
            static fn (PollerId $id): int => $id->value,
            array_filter([$before->pollerToFlag(), $after->pollerToFlag()]),
        );

        $hostIds = [];
        $pollerIds = [];
        foreach ($storedLinks->toArray() as $hostId => $state) {
            if (in_array((int) $hostId, $keptHostIds, true)) {
                continue;
            }

            $hostIds[] = new HostId((int) $hostId);
            $pollerId = $state->pollerToFlag();
            if ($pollerId instanceof PollerId && ! in_array($pollerId->value, $flaggedPollerIds, true)) {
                $flaggedPollerIds[] = $pollerId->value;
                $pollerIds[] = $pollerId;
            }
        }

        return new self($hostIds, $pollerIds);
    }
}
