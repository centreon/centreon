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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\Shared\Domain\Event\AggregateUpdated;

/**
 * Fired on a full update of a service (PUT). When the service moved to another host, it carries that previous
 * host, so the acting user keeps seeing the service on its new host, and the poller of the previous host when
 * that host was enabled, so both it and the new one are flagged for a configuration reload (see
 * FlagPollerChangedEventHandler). Both are null when the host did not change.
 */
final readonly class ServiceUpdated extends AggregateUpdated
{
    public function __construct(
        Service $service,
        int $creatorId,
        public ?PollerId $previousPollerId = null,
        public ?HostId $previousHostId = null,
        bool $loggable = true,
    ) {
        parent::__construct($service, $creatorId, loggable: $loggable);
    }
}
