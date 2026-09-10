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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Shared\Domain\Aggregate\PollerScopedInterface;
use App\Shared\Domain\Event\AggregateCreated;
use App\Shared\Domain\Event\AsEventHandler;

/**
 * Reacts to the creation of any poller-scoped resource (see {@see PollerScopedInterface}): the
 * owning poller's configuration just changed, so its `nagios_server.updated` flag must be raised,
 * or the monitoring engine keeps running on stale configuration until something else touches it.
 *
 * Only Created is wired today (Host has no update/delete use case yet in src/App) — extend to
 * AggregateUpdated/Deleted/Duplicated once those exist, mirroring LogActivityEventHandler.
 */
#[AsEventHandler]
final readonly class FlagPollerChangedEventHandler
{
    public function __construct(
        private PollerRepository $pollerRepository,
    ) {
    }

    public function __invoke(AggregateCreated $event): void
    {
        if (! $event->aggregate instanceof PollerScopedInterface) {
            return;
        }

        // Only Host implements PollerScopedInterface today — extend this match when a second
        // poller-scoped resource type needs the same bookkeeping (see PollerScopedInterface).
        if (! $event->aggregate instanceof Host) {
            throw new \LogicException(sprintf('No poller mapping for aggregate %s.', $event->aggregate::class));
        }

        $this->pollerRepository->flagAsChanged($event->aggregate->pollerId);
    }
}
