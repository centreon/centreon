<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate;

use Core\Common\Application\Type\NoValue;

final class PartialUpdateHostTemplateRequest
{
    /**
     * @param NoValue|array<array{name:string,value:string|null,is_password:bool,description:null|string}> $macros
     * @param NoValue|int[] $categories
     * @param NoValue|int[] $templates
     * @param NoValue|string $name
     * @param NoValue|string $alias
     * @param NoValue|string $snmpVersion
     * @param NoValue|string $snmpCommunity
     * @param NoValue|null|int $timezoneId
     * @param NoValue|null|int $severityId
     * @param NoValue|null|int $checkCommandId
     * @param NoValue|string[] $checkCommandArgs
     * @param NoValue|null|int $checkTimeperiodId
     * @param NoValue|null|int $maxCheckAttempts
     * @param NoValue|null|int $normalCheckInterval
     * @param NoValue|null|int $retryCheckInterval
     * @param NoValue|int $activeCheckEnabled
     * @param NoValue|int $passiveCheckEnabled
     * @param NoValue|int $notificationEnabled
     * @param NoValue|null|int $notificationOptions
     * @param NoValue|null|int $notificationInterval
     * @param NoValue|null|int $notificationTimeperiodId
     * @param NoValue|bool $addInheritedContactGroup
     * @param NoValue|bool $addInheritedContact
     * @param NoValue|null|int $firstNotificationDelay
     * @param NoValue|null|int $recoveryNotificationDelay
     * @param NoValue|null|int $acknowledgementTimeout
     * @param NoValue|int $freshnessChecked
     * @param NoValue|null|int $freshnessThreshold
     * @param NoValue|int $flapDetectionEnabled
     * @param NoValue|null|int $lowFlapThreshold
     * @param NoValue|null|int $highFlapThreshold
     * @param NoValue|int $eventHandlerEnabled
     * @param NoValue|null|int $eventHandlerCommandId
     * @param NoValue|string[] $eventHandlerCommandArgs
     * @param NoValue|string $noteUrl
     * @param NoValue|string $note
     * @param NoValue|string $actionUrl
     * @param NoValue|null|int $iconId
     * @param NoValue|string $iconAlternative
     * @param NoValue|string $comment
     * @param NoValue|bool $isActivated
     */
    public function __construct(
        public NoValue|array $macros = new NoValue(),
        public NoValue|array $categories = new NoValue(),
        public NoValue|array $templates = new NoValue(),
        public NoValue|string $name = new NoValue(),
        public NoValue|string $alias = new NoValue(),
        public NoValue|string $snmpVersion = new NoValue(),
        public NoValue|string $snmpCommunity = new NoValue(),
        public NoValue|null|int $timezoneId = new NoValue(),
        public NoValue|null|int $severityId = new NoValue(),
        public NoValue|null|int $checkCommandId = new NoValue(),
        public NoValue|array $checkCommandArgs = new NoValue(),
        public NoValue|null|int $checkTimeperiodId = new NoValue(),
        public NoValue|null|int $maxCheckAttempts = new NoValue(),
        public NoValue|null|int $normalCheckInterval = new NoValue(),
        public NoValue|null|int $retryCheckInterval = new NoValue(),
        public NoValue|int $activeCheckEnabled = new NoValue(),
        public NoValue|int $passiveCheckEnabled = new NoValue(),
        public NoValue|int $notificationEnabled = new NoValue(),
        public NoValue|null|int $notificationOptions = new NoValue(),
        public NoValue|null|int $notificationInterval = new NoValue(),
        public NoValue|null|int $notificationTimeperiodId = new NoValue(),
        public NoValue|bool $addInheritedContactGroup = new NoValue(),
        public NoValue|bool $addInheritedContact = new NoValue(),
        public NoValue|null|int $firstNotificationDelay = new NoValue(),
        public NoValue|null|int $recoveryNotificationDelay = new NoValue(),
        public NoValue|null|int $acknowledgementTimeout = new NoValue(),
        public NoValue|int $freshnessChecked = new NoValue(),
        public NoValue|null|int $freshnessThreshold = new NoValue(),
        public NoValue|int $flapDetectionEnabled = new NoValue(),
        public NoValue|null|int $lowFlapThreshold = new NoValue(),
        public NoValue|null|int $highFlapThreshold = new NoValue(),
        public NoValue|int $eventHandlerEnabled = new NoValue(),
        public NoValue|null|int $eventHandlerCommandId = new NoValue(),
        public NoValue|array $eventHandlerCommandArgs = new NoValue(),
        public NoValue|string $noteUrl = new NoValue(),
        public NoValue|string $note = new NoValue(),
        public NoValue|string $actionUrl = new NoValue(),
        public NoValue|null|int $iconId = new NoValue(),
        public NoValue|string $iconAlternative = new NoValue(),
        public NoValue|string $comment = new NoValue(),
        public NoValue|bool $isActivated = new NoValue(),
    ) {
    }
}
