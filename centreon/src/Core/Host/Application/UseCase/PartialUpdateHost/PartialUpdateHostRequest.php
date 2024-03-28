<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Host\Application\UseCase\PartialUpdateHost;

use Core\Common\Application\Type\NoValue;

final class PartialUpdateHostRequest
{
    /**
     * @param NoValue|string $name
     * @param NoValue|string $address
     * @param NoValue|int $monitoringServerId
     * @param NoValue|null|string $alias
     * @param NoValue|null|string $snmpVersion
     * @param NoValue|null|string $snmpCommunity
     * @param NoValue|null|string $noteUrl
     * @param NoValue|null|string $note
     * @param NoValue|null|string $actionUrl
     * @param NoValue|null|string $iconAlternative
     * @param NoValue|null|string $comment
     * @param NoValue|null|string $geoCoordinates
     * @param NoValue|string[] $checkCommandArgs
     * @param NoValue|string[] $eventHandlerCommandArgs
     * @param NoValue|int $activeCheckEnabled
     * @param NoValue|int $passiveCheckEnabled
     * @param NoValue|int $notificationEnabled
     * @param NoValue|int $freshnessChecked
     * @param NoValue|int $flapDetectionEnabled
     * @param NoValue|int $eventHandlerEnabled
     * @param NoValue|null|int $timezoneId
     * @param NoValue|null|int $severityId
     * @param NoValue|null|int $checkCommandId
     * @param NoValue|null|int $checkTimeperiodId
     * @param NoValue|null|int $notificationTimeperiodId
     * @param NoValue|null|int $eventHandlerCommandId
     * @param NoValue|null|int $iconId
     * @param NoValue|null|int $maxCheckAttempts
     * @param NoValue|null|int $normalCheckInterval
     * @param NoValue|null|int $retryCheckInterval
     * @param NoValue|null|int $notificationOptions
     * @param NoValue|null|int $notificationInterval
     * @param NoValue|bool $addInheritedContactGroup
     * @param NoValue|bool $addInheritedContact
     * @param NoValue|null|int $firstNotificationDelay
     * @param NoValue|null|int $recoveryNotificationDelay
     * @param NoValue|null|int $acknowledgementTimeout
     * @param NoValue|null|int $freshnessThreshold
     * @param NoValue|null|int $lowFlapThreshold
     * @param NoValue|null|int $highFlapThreshold
     * @param NoValue|int[] $categories
     * @param NoValue|int[] $groups
     * @param NoValue|int[] $templates
     * @param NoValue|array<array{name:string,value:null|string,is_password:bool,description:null|string}> $macros
     * @param NoValue|bool $isActivated
     */
    public function __construct(
        public NoValue|string $name = new NoValue(),
        public NoValue|string $address = new NoValue(),
        public NoValue|int $monitoringServerId = new NoValue(),
        public NoValue|null|string $alias = new NoValue(),
        public NoValue|null|string $snmpVersion = new NoValue(),
        public NoValue|null|string $snmpCommunity = new NoValue(),
        public NoValue|null|string $noteUrl = new NoValue(),
        public NoValue|null|string $note = new NoValue(),
        public NoValue|null|string $actionUrl = new NoValue(),
        public NoValue|null|string $iconAlternative = new NoValue(),
        public NoValue|null|string $comment = new NoValue(),
        public NoValue|null|string $geoCoordinates = new NoValue(),
        public NoValue|array $checkCommandArgs = new NoValue(),
        public NoValue|array $eventHandlerCommandArgs = new NoValue(),
        public NoValue|int $activeCheckEnabled = new NoValue(),
        public NoValue|int $passiveCheckEnabled = new NoValue(),
        public NoValue|int $notificationEnabled = new NoValue(),
        public NoValue|int $freshnessChecked = new NoValue(),
        public NoValue|int $flapDetectionEnabled = new NoValue(),
        public NoValue|int $eventHandlerEnabled = new NoValue(),
        public NoValue|null|int $timezoneId = new NoValue(),
        public NoValue|null|int $severityId = new NoValue(),
        public NoValue|null|int $checkCommandId = new NoValue(),
        public NoValue|null|int $checkTimeperiodId = new NoValue(),
        public NoValue|null|int $notificationTimeperiodId = new NoValue(),
        public NoValue|null|int $eventHandlerCommandId = new NoValue(),
        public NoValue|null|int $iconId = new NoValue(),
        public NoValue|null|int $maxCheckAttempts = new NoValue(),
        public NoValue|null|int $normalCheckInterval = new NoValue(),
        public NoValue|null|int $retryCheckInterval = new NoValue(),
        public NoValue|null|int $notificationOptions = new NoValue(),
        public NoValue|null|int $notificationInterval = new NoValue(),
        public NoValue|bool $addInheritedContactGroup = new NoValue(),
        public NoValue|bool $addInheritedContact = new NoValue(),
        public NoValue|null|int $firstNotificationDelay = new NoValue(),
        public NoValue|null|int $recoveryNotificationDelay = new NoValue(),
        public NoValue|null|int $acknowledgementTimeout = new NoValue(),
        public NoValue|null|int $freshnessThreshold = new NoValue(),
        public NoValue|null|int $lowFlapThreshold = new NoValue(),
        public NoValue|null|int $highFlapThreshold = new NoValue(),
        public NoValue|array $categories = new NoValue(),
        public NoValue|array $groups = new NoValue(),
        public NoValue|array $templates = new NoValue(),
        public NoValue|array $macros = new NoValue(),
        public NoValue|bool $isActivated = new NoValue(),
    ) {
    }
}
