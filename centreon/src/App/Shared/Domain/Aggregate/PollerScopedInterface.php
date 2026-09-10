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

namespace App\Shared\Domain\Aggregate;

/**
 * Marks an aggregate whose creation, update, deletion or duplication changes the effective
 * configuration of a poller: the poller's `nagios_server.updated` flag must be raised, or the
 * monitoring engine keeps running on stale configuration until something else touches that
 * poller. Purely a marker — {@see \App\MonitoringConfiguration\Application\EventHandler\FlagPollerChangedEventHandler}
 * checks `$event->aggregate instanceof PollerScopedInterface` to decide whether to act.
 *
 * This PR only wires the AggregateCreated case (Host creation); AggregateUpdated/Deleted/Duplicated
 * will extend the same handler once those Host use cases exist.
 */
interface PollerScopedInterface
{
}
