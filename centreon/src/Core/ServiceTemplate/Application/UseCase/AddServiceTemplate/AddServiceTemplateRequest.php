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

namespace Core\ServiceTemplate\Application\UseCase\AddServiceTemplate;

final class AddServiceTemplateRequest
{
    public string $name = '';

    public string $alias = '';

    /** @var list<string> */
    public array $commandArguments = [];

    /** @var list<string> */
    public array $eventHandlerArguments = [];

    public int $notificationTypes = 0;

    public bool $isContactAdditiveInheritance = false;

    public bool $isContactGroupAdditiveInheritance = false;

    public bool $isActivated = false;

    public bool $isLocked = false;

    public int $activeChecks = 0;

    public int $passiveCheck = 0;

    public int $volatility = 0;

    public int $checkFreshness = 0;

    public int $eventHandlerEnabled = 0;

    public int $flapDetectionEnabled = 0;

    public int $notificationsEnabled = 0;

    public string|null $comment = null;

    public string|null $note;

    public string|null $noteUrl;

    public string|null $actionUrl;

    public string|null $iconAlternativeText;

    public int|null $graphTemplateId;

    public int|null $serviceTemplateParentId;

    public int|null $commandId;

    public int|null $eventHandlerId;

    public int|null $notificationTimePeriodId;

    public int|null $checkTimePeriodId;

    public int|null $iconId;

    public int|null $severityId;

    public int|null $maxCheckAttempts;

    public int|null $normalCheckInterval;

    public int|null $retryCheckInterval;

    public int|null $freshnessThreshold;

    public int|null $lowFlapThreshold;

    public int|null $highFlapThreshold;

    public int|null $notificationInterval;

    public int|null $recoveryNotificationDelay;

    public int|null $firstNotificationDelay;

    public int|null $acknowledgementTimeout;
}
