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

namespace Core\Service\Application\UseCase\PartialUpdateService;

use Core\Common\Application\Type\NoValue;

final class PartialUpdateServiceRequest
{
    /**
     * @param NoValue|string $name
     * @param NoValue|int $hostId
     * @param NoValue|null|int $template
     * @param NoValue|int $activeChecks
     * @param NoValue|int $passiveCheck
     * @param NoValue|int $volatility
     * @param NoValue|int $checkFreshness
     * @param NoValue|int $eventHandlerEnabled
     * @param NoValue|int $flapDetectionEnabled
     * @param NoValue|int $notificationsEnabled
     * @param NoValue|null|string $comment
     * @param NoValue|null|string $note
     * @param NoValue|null|string $noteUrl
     * @param NoValue|null|string $actionUrl
     * @param NoValue|null|string $iconAlternativeText
     * @param NoValue|null|string $geoCoords
     * @param NoValue|null|int $commandId
     * @param NoValue|null|int $graphTemplateId
     * @param NoValue|null|int $eventHandlerId
     * @param NoValue|null|int $notificationTimePeriodId
     * @param NoValue|null|int $checkTimePeriodId
     * @param NoValue|null|int $iconId
     * @param NoValue|null|int $severityId
     * @param NoValue|null|int $maxCheckAttempts
     * @param NoValue|null|int $normalCheckInterval
     * @param NoValue|null|int $retryCheckInterval
     * @param NoValue|null|int $freshnessThreshold
     * @param NoValue|null|int $lowFlapThreshold
     * @param NoValue|null|int $highFlapThreshold
     * @param NoValue|null|int $notificationTypes
     * @param NoValue|null|int $notificationInterval
     * @param NoValue|null|int $recoveryNotificationDelay
     * @param NoValue|null|int $firstNotificationDelay
     * @param NoValue|null|int $acknowledgementTimeout
     * @param NoValue|string[] $commandArguments
     * @param NoValue|string[] $eventHandlerArguments
     * @param NoValue|MacroDto[] $macros
     * @param NoValue|int[] $categories
     * @param NoValue|int[] $groups
     * @param NoValue|bool $isContactAdditiveInheritance
     * @param NoValue|bool $isContactGroupAdditiveInheritance
     * @param NoValue|bool $isActivated
     */
    public function __construct(
        public NoValue|string $name = new NoValue(),
        public NoValue|int $hostId = new NoValue(),
        public NoValue|null|int $template = new NoValue(),
        public NoValue|int $activeChecks = new NoValue(),
        public NoValue|int $passiveCheck = new NoValue(),
        public NoValue|int $volatility = new NoValue(),
        public NoValue|int $checkFreshness = new NoValue(),
        public NoValue|int $eventHandlerEnabled = new NoValue(),
        public NoValue|int $flapDetectionEnabled = new NoValue(),
        public NoValue|int $notificationsEnabled = new NoValue(),
        public NoValue|null|string $comment = new NoValue(),
        public NoValue|null|string $note = new NoValue(),
        public NoValue|null|string $noteUrl = new NoValue(),
        public NoValue|null|string $actionUrl = new NoValue(),
        public NoValue|null|string $iconAlternativeText = new NoValue(),
        public NoValue|null|string $geoCoords = new NoValue(),
        public NoValue|null|int $commandId = new NoValue(),
        public NoValue|null|int $graphTemplateId = new NoValue(),
        public NoValue|null|int $eventHandlerId = new NoValue(),
        public NoValue|null|int $notificationTimePeriodId = new NoValue(),
        public NoValue|null|int $checkTimePeriodId = new NoValue(),
        public NoValue|null|int $iconId = new NoValue(),
        public NoValue|null|int $severityId = new NoValue(),
        public NoValue|null|int $maxCheckAttempts = new NoValue(),
        public NoValue|null|int $normalCheckInterval = new NoValue(),
        public NoValue|null|int $retryCheckInterval = new NoValue(),
        public NoValue|null|int $freshnessThreshold = new NoValue(),
        public NoValue|null|int $lowFlapThreshold = new NoValue(),
        public NoValue|null|int $highFlapThreshold = new NoValue(),
        public NoValue|null|int $notificationTypes = new NoValue(),
        public NoValue|null|int $notificationInterval = new NoValue(),
        public NoValue|null|int $recoveryNotificationDelay = new NoValue(),
        public NoValue|null|int $firstNotificationDelay = new NoValue(),
        public NoValue|null|int $acknowledgementTimeout = new NoValue(),
        public NoValue|array $commandArguments = new NoValue(),
        public NoValue|array $eventHandlerArguments = new NoValue(),
        public NoValue|array $macros = new NoValue(),
        public NoValue|array $categories = new NoValue(),
        public NoValue|array $groups = new NoValue(),
        public NoValue|bool $isContactAdditiveInheritance = new NoValue(),
        public NoValue|bool $isContactGroupAdditiveInheritance = new NoValue(),
        public NoValue|bool $isActivated = new NoValue(),
    ) {
    }
}
