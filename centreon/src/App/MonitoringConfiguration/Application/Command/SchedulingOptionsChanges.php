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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in a host's scheduling options (check period, attempts, intervals, active and
 * passive checks).
 *
 * Every property can mean three different things, which is why most types read
 * `NoValue|<type>|null`:
 * - `NoValue`: the caller did not provide the field, the stored value stays as it is;
 * - `null`: the caller provided the field empty, the value is cleared (the host then relies on its
 *   templates or on the engine default);
 * - a value: it replaces the stored one.
 *
 * The two check switches cannot be `null`: "use default" is a value of their own
 * (`TriStateEnum::UseDefault`), not an absence.
 */
final readonly class SchedulingOptionsChanges
{
    public function __construct(
        public NoValue|TimePeriodId|null $checkTimeperiodId = new NoValue(),
        public NoValue|int|null $maxCheckAttempts = new NoValue(),
        public NoValue|int|null $normalCheckInterval = new NoValue(),
        public NoValue|int|null $retryCheckInterval = new NoValue(),
        public NoValue|TriStateEnum $activeCheckEnabled = new NoValue(),
        public NoValue|TriStateEnum $passiveCheckEnabled = new NoValue(),
    ) {
    }

    public function applyTo(SchedulingOptions $current): SchedulingOptions
    {
        return $current->with(
            checkTimeperiodId: $this->checkTimeperiodId,
            maxCheckAttempts: $this->maxCheckAttempts,
            normalCheckInterval: $this->normalCheckInterval,
            retryCheckInterval: $this->retryCheckInterval,
            activeCheckEnabled: $this->activeCheckEnabled,
            passiveCheckEnabled: $this->passiveCheckEnabled,
        );
    }
}
