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

namespace App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\HostMassChanged;
use App\MonitoringConfiguration\Domain\Event\HostUpdated;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\PollerScopedInterface;
use App\Shared\Domain\Event\AggregateCreated;
use App\Shared\Domain\Event\AggregateDeleted;
use App\Shared\Domain\Event\AggregateDuplicated;
use App\Shared\Domain\Event\AggregateUpdated;
use App\Shared\Domain\Event\AsEventHandler;

/**
 * Reacts to the creation, update, deletion or duplication of any poller-scoped resource (see
 * {@see PollerScopedInterface}): the owning poller's configuration just changed, so its
 * `nagios_server.updated` flag must be raised, or the monitoring engine keeps running on stale
 * configuration until something else touches it. Reacting to the {@see AggregateUpdated} supertype
 * also catches its enable/disable specializations.
 */
#[AsEventHandler]
final readonly class FlagPollerChangedEventHandler
{
    public function __construct(
        private PollerRepository $pollerRepository,
    ) {
    }

    /**
     * @param AggregateCreated|AggregateUpdated|AggregateDeleted|AggregateDuplicated<covariant AggregateRoot<AggregateRootId>> $event
     */
    public function __invoke(AggregateCreated|AggregateUpdated|AggregateDeleted|AggregateDuplicated $event): void
    {
        if (! $event->aggregate instanceof PollerScopedInterface) {
            return;
        }

        $this->pollerRepository->flagAsChanged($event->aggregate);

        // On a move to another poller, the previous one must be regenerated too, or it keeps monitoring a
        // host it no longer owns. Both events carry the previous poller only when it actually changed.
        if (($event instanceof HostMassChanged || $event instanceof HostUpdated)
            && $event->previousPollerId instanceof PollerId
        ) {
            $this->pollerRepository->flagIdAsChanged($event->previousPollerId);
        }
    }
}
