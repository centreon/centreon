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

namespace Core\Service\Application\UseCase\DeployServices;

use Core\Common\Domain\YesNoDefault;
use Core\Service\Domain\Model\NotificationType;

final class DeployServiceDto
{
    public int $id = 0;

    public string $name = '';

    public int $hostId = 0;

    public ?string $geoCoords = null;

    public ?string $comment = null;

    public ?int $serviceTemplateId = null;

    public ?int $commandId = null;

    /** @var string[] */
    public array $commandArguments = [];

    public ?int $checkTimePeriodId = null;

    public ?int $maxCheckAttempts = null;

    public ?int $normalCheckInterval = null;

    public ?int $retryCheckInterval = null;

    public YesNoDefault $activeChecksEnabled = YesNoDefault::Default;

    public YesNoDefault $passiveChecksEnabled = YesNoDefault::Default;

    public YesNoDefault $volatilityEnabled = YesNoDefault::Default;

    public YesNoDefault $notificationsEnabled = YesNoDefault::Default;

    public bool $isContactAdditiveInheritance = false;

    public bool $isContactGroupAdditiveInheritance = false;

    public ?int $notificationInterval = null;

    public ?int $notificationTimePeriodId = null;

    /** @var NotificationType[] */
    public array $notificationTypes = [];

    public ?int $firstNotificationDelay = null;

    public ?int $recoveryNotificationDelay = null;

    public ?int $acknowledgementTimeout;

    public YesNoDefault $checkFreshness = YesNoDefault::Default;

    public ?int $freshnessThreshold = null;

    public YesNoDefault $flapDetectionEnabled = YesNoDefault::Default;

    public ?int $lowFlapThreshold = null;

    public ?int $highFlapThreshold = null;

    public YesNoDefault $eventHandlerEnabled = YesNoDefault::Default;

    public ?int $eventHandlerCommandId = null;

    /** @var string[] */
    public array $eventHandlerArguments = [];

    public ?int $graphTemplateId = null;

    public ?string $note = null;

    public ?string $noteUrl = null;

    public ?string $actionUrl;

    public ?int $iconId = null;

    public ?string $iconAlternative = null;

    public ?int $severityId = null;

    public bool $isActivated = false;
}
