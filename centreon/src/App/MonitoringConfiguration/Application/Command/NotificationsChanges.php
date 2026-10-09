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

use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in a host's notification settings (on/off, events to notify on, delays,
 * period, additive inheritance of contacts).
 *
 * - `NoValue`: the caller did not provide the field, the stored value stays as it is;
 * - `null` (interval, period and delays only): the value is cleared, the host relies on its
 *   templates or on the engine default;
 * - a value: it replaces the stored one. For the event list, an empty list clears it.
 *
 * `enabled` cannot be `null`: "use default" is a value of its own (`TriStateEnum::UseDefault`).
 * The two additive-inheritance flags cannot be `null` either, they are plain yes/no switches.
 * The contacts and contact groups are not part of this object: they are relations, changed separately.
 */
final readonly class NotificationsChanges
{
    /**
     * @param NoValue|list<NotificationOptionEnum> $options
     */
    public function __construct(
        public NoValue|TriStateEnum $enabled = new NoValue(),
        public NoValue|array $options = new NoValue(),
        public NoValue|int|null $interval = new NoValue(),
        public NoValue|TimePeriodId|null $periodId = new NoValue(),
        public NoValue|int|null $firstDelay = new NoValue(),
        public NoValue|int|null $recoveryDelay = new NoValue(),
        public NoValue|bool $contactAdditiveInheritance = new NoValue(),
        public NoValue|bool $contactGroupAdditiveInheritance = new NoValue(),
    ) {
    }

    public function applyTo(?Notifications $current): Notifications
    {
        return ($current ?? Notifications::default())->with(
            enabled: $this->enabled,
            options: $this->options,
            interval: $this->interval,
            periodId: $this->periodId,
            firstDelay: $this->firstDelay,
            recoveryDelay: $this->recoveryDelay,
            contactAdditiveInheritance: $this->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $this->contactGroupAdditiveInheritance,
        );
    }
}
