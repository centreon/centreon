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

namespace App\MonitoringConfiguration\Domain\Event;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Event\AggregateUpdated;

/**
 * Fired on a full update of a host (PUT). Carries the poller the host was on *before* the update
 * so that, when the poller changed, both the previous and the new one can be flagged for a
 * configuration reload (see FlagPollerChangedEventHandler); null when the poller was unchanged.
 */
final readonly class HostUpdated extends AggregateUpdated
{
    public function __construct(
        Host $host,
        int $creatorId,
        public ?PollerId $previousPollerId = null,
    ) {
        parent::__construct($host, $creatorId);
    }
}
