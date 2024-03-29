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

namespace Core\Host\Application\UseCase\AddHost;

final class AddHostRequest
{
    public string $name = '';

    public string $address = '';

    public int $monitoringServerId = 0;

    public string $alias = '';

    public string $snmpVersion = '';

    public string $snmpCommunity = '';

    public string $noteUrl = '';

    public string $note = '';

    public string $actionUrl = '';

    public string $iconAlternative = '';

    public string $comment = '';

    public string $geoCoordinates = '';

    /** @var string[] */
    public array $checkCommandArgs = [];

    /** @var string[] */
    public array $eventHandlerCommandArgs = [];

    public int $activeCheckEnabled = 2;

    public int $passiveCheckEnabled = 2;

    public int $notificationEnabled = 2;

    public int $freshnessChecked = 2;

    public int $flapDetectionEnabled = 2;

    public int $eventHandlerEnabled = 2;

    public ?int $timezoneId = null;

    public ?int $severityId = null;

    public ?int $checkCommandId = null;

    public ?int $checkTimeperiodId = null;

    public ?int $notificationTimeperiodId = null;

    public ?int $eventHandlerCommandId = null;

    public ?int $iconId = null;

    public ?int $maxCheckAttempts = null;

    public ?int $normalCheckInterval = null;

    public ?int $retryCheckInterval = null;

    public ?int $notificationOptions = null;

    public ?int $notificationInterval = null;

    public bool $addInheritedContactGroup = false;

    public bool $addInheritedContact = false;

    public ?int $firstNotificationDelay = null;

    public ?int $recoveryNotificationDelay = null;

    public ?int $acknowledgementTimeout = null;

    public ?int $freshnessThreshold = null;

    public ?int $lowFlapThreshold = null;

    public ?int $highFlapThreshold = null;

    /** @var int[] */
    public array $categories = [];

    /** @var int[] */
    public array $groups = [];

    /** @var int[] */
    public array $templates = [];

    /** @var array<array{name:string,value:null|string,is_password:bool,description:null|string}> */
    public array $macros = [];

    public bool $isActivated = true;
}
