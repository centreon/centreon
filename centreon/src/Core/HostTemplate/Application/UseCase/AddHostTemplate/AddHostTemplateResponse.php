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

namespace Core\HostTemplate\Application\UseCase\AddHostTemplate;

use Core\Common\Domain\YesNoDefault;

final class AddHostTemplateResponse
{
    public int $id = 0;

    public string $name;

    public string $alias;

    public ?string $snmpVersion = null;

    public string $snmpCommunity = '';

    public ?int $timezoneId = null;

    public ?int $severityId = null;

    public ?int $checkCommandId = null;

    public string $checkCommandArgs = '';

    public ?int $checkTimeperiodId = null;

    public ?int $maxCheckAttempts = null;

    public ?int $normalCheckInterval = null;

    public ?int $retryCheckInterval = null;

    public int $isActiveCheckEnabled;

    public int $isPassiveCheckEnabled;

    public int $isNotificationEnabled;

    public ?int $notificationOptions = null;

    public ?int $notificationInterval = null;

    public ?int $notificationTimeperiodId = null;

    public bool $addInheritedContactGroup = false;

    public bool $addInheritedContact = false;

    public ?int $firstNotificationDelay = null;

    public ?int $recoveryNotificationDelay = null;

    public ?int $acknowledgementTimeout = null;

    public int $isFreshnessChecked;

    public ?int $freshnessThreshold = null;

    public int $isFlapDetectionEnabled;

    public ?int $lowFlapThreshold = null;

    public ?int $highFlapThreshold = null;

    public int $isEventHandlerEnabled;

    public ?int $eventHandlerCommandId = null;

    public string $eventHandlerCommandArgs = '';

    public string $noteUrl = '';

    public string $note = '';

    public string $actionUrl = '';

    public ?int $iconId = null;

    public string $iconAlternative = '';

    public string $comment = '';

    public bool $isActivated = true;

    public bool $isLocked = false;

    public function __construct() {
        $this->isActiveCheckEnabled = YesNoDefault::Default->toInt();
        $this->isPassiveCheckEnabled = YesNoDefault::Default->toInt();
        $this->isNotificationEnabled = YesNoDefault::Default->toInt();
        $this->isFreshnessChecked = YesNoDefault::Default->toInt();
        $this->isFlapDetectionEnabled = YesNoDefault::Default->toInt();
        $this->isEventHandlerEnabled = YesNoDefault::Default->toInt();
    }
}
