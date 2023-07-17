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

use Core\Common\Domain\YesNoDefault;
use Core\ServiceTemplate\Domain\Model\NotificationType;

final class AddServiceTemplateResponse
{
    public int $id = 0;

    public string $name = '';

    public string $alias = '';

    public string|null $comment = null;

    public int|null $acknowledgementTimeout;

    public string|null $actionUrl;

    public bool $isContactAdditiveInheritance = false;

    public bool $isContactGroupAdditiveInheritance = false;

    public int|null $commandId = null;

    /** @var string[] */
    public array $commandArguments = [];

    public int|null $eventHandlerId = null;

    /** @var string[] */
    public array $eventHandlerArguments = [];

    public int|null $checkTimePeriodId = null;

    public int|null $firstNotificationDelay = null;

    public int|null $freshnessThreshold = null;

    public int|null $graphTemplateId = null;

    public int|null $lowFlapThreshold = null;

    public int|null $highFlapThreshold = null;

    public int|null $iconId = null;

    public string|null $iconAlternativeText = null;

    public bool $isActivated = false;

    public bool $isLocked = false;

    public YesNoDefault $activeChecks = YesNoDefault::Default;

    public YesNoDefault $eventHandlerEnabled = YesNoDefault::Default;

    public YesNoDefault $flapDetectionEnabled = YesNoDefault::Default;

    public YesNoDefault $checkFreshness = YesNoDefault::Default;

    public YesNoDefault $notificationsEnabled = YesNoDefault::Default;

    public YesNoDefault $passiveCheck = YesNoDefault::Default;

    public YesNoDefault $volatility = YesNoDefault::Default;

    public int|null $maxCheckAttempts = null;

    public int|null $normalCheckInterval = null;

    public string|null $note = null;

    public string|null $noteUrl = null;

    public int|null $notificationInterval = null;

    public int|null $notificationTimePeriodId = null;

    /** @var NotificationType[] */
    public array $notificationTypes = [];

    /** @var list<int> */
    public array $hostTemplateIds = [];

    public int|null $recoveryNotificationDelay = null;

    public int|null $retryCheckInterval = null;

    public int|null $serviceTemplateId = null;

    public int|null $severityId = null;

    /** @var MacroDto[] */
    public array $macros = [];

    /** @var array<array{id:int,name:string}> */
    public array $categories = [];
}
