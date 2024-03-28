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

namespace Core\Service\Application\UseCase\AddService;

final class AddServiceRequest
{
    public string $name = '';

    public int $hostId = 0; // Only one host for SaaS platform

    /** @var int|null */
    public ?int $commandId = null;

    /** @var list<string> */
    public array $commandArguments = [];

    /** @var list<string> */
    public array $eventHandlerArguments = [];

    /** @var list<int> */
    public array $serviceCategories = [];

    /** @var list<int> */
    public array $serviceGroups = [];

    public int|null $notificationTypes = null;

    public bool $isContactAdditiveInheritance = false;

    public bool $isContactGroupAdditiveInheritance = false;

    public bool $isActivated = false;

    public int $activeChecks = 0;

    public int $passiveCheck = 0;

    public int $volatility = 0;

    public int $checkFreshness = 0;

    public int $eventHandlerEnabled = 0;

    public int $flapDetectionEnabled = 0;

    public int $notificationsEnabled = 0;

    public string|null $comment = null;

    public string|null $note = null;

    public string|null $noteUrl = null;

    public string|null $actionUrl = null;

    public string|null $iconAlternativeText = null;

    public int|null $graphTemplateId = null;

    public int|null $serviceTemplateParentId = null;

    public int|null $eventHandlerId = null;

    public int|null $notificationTimePeriodId = null;

    public int|null $checkTimePeriodId = null;

    public int|null $iconId = null;

    public int|null $severityId = null;

    public int|null $maxCheckAttempts = null;

    public int|null $normalCheckInterval = null;

    public int|null $retryCheckInterval = null;

    public int|null $freshnessThreshold = null;

    public int|null $lowFlapThreshold = null;

    public int|null $highFlapThreshold = null;

    public int|null $notificationInterval = null;

    public int|null $recoveryNotificationDelay = null;

    public int|null $firstNotificationDelay = null;

    public int|null $acknowledgementTimeout = null;

    /** @var MacroDto[] */
    public array $macros = [];

    public string $geoCoords = '';
}
