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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Exception\ExclusiveNotificationOptionException;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in a service's notification settings.
 *
 * - `NoValue`: the field is not provided, the stored value stays as it is;
 * - `null` (interval, period and delays only): the value is cleared, the service then inherits it;
 * - a value: it replaces the stored one.
 *
 * `enabled` cannot be `null`: "use default" is a value of its own ({@see TriStateEnum::UseDefault}). The three
 * switches cannot be `null` either. The contacts and contact groups are not part of this object: they are
 * relations, changed separately.
 */
final readonly class ServiceNotificationsChanges
{
    /**
     * @param NoValue|ListChange<ServiceNotificationOptionEnum> $options
     */
    public function __construct(
        public NoValue|TriStateEnum $enabled = new NoValue(),
        public NoValue|ListChange $options = new NoValue(),
        public NoValue|int|null $interval = new NoValue(),
        public NoValue|TimePeriodId|null $periodId = new NoValue(),
        public NoValue|int|null $firstDelay = new NoValue(),
        public NoValue|int|null $recoveryDelay = new NoValue(),
        public NoValue|bool $useOnlyContactsFromHost = new NoValue(),
        public NoValue|bool $contactAdditiveInheritance = new NoValue(),
        public NoValue|bool $contactGroupAdditiveInheritance = new NoValue(),
    ) {
    }

    /**
     * @throws ExclusiveNotificationOptionException when the options left combine "none" with another one
     */
    public function applyTo(?ServiceNotifications $current): ServiceNotifications
    {
        $current ??= self::none();
        $options = $this->options instanceof ListChange ? $this->options->applyTo($current->options) : $current->options;
        if (count($options) > 1 && in_array(ServiceNotificationOptionEnum::None, $options, true)) {
            throw new ExclusiveNotificationOptionException();
        }

        return new ServiceNotifications(
            enabled: NoValue::resolve($this->enabled, $current->enabled),
            contactIds: $current->contactIds,
            contactGroupIds: $current->contactGroupIds,
            options: $options,
            interval: NoValue::resolve($this->interval, $current->interval),
            periodId: NoValue::resolve($this->periodId, $current->periodId),
            firstDelay: NoValue::resolve($this->firstDelay, $current->firstDelay),
            recoveryDelay: NoValue::resolve($this->recoveryDelay, $current->recoveryDelay),
            contactAdditiveInheritance: NoValue::resolve($this->contactAdditiveInheritance, $current->contactAdditiveInheritance),
            contactGroupAdditiveInheritance: NoValue::resolve($this->contactGroupAdditiveInheritance, $current->contactGroupAdditiveInheritance),
            useOnlyContactsFromHost: NoValue::resolve($this->useOnlyContactsFromHost, $current->useOnlyContactsFromHost),
        );
    }

    /**
     * The settings of a service that has none of its own.
     */
    public static function none(): ServiceNotifications
    {
        return new ServiceNotifications(
            TriStateEnum::UseDefault,
            new Collection([], NotificationContactId::class),
            new Collection([], ContactGroupId::class),
        );
    }
}
