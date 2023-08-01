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

namespace Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate;

use Core\Common\Application\Type\NoValue;

final class PartialUpdateServiceTemplateRequest
{
    public string|NoValue $name;

    public string|NoValue $alias;

    /** @var list<string> */
    public array|NoValue $commandArguments;

    /** @var list<string> */
    public array|NoValue $eventHandlerArguments;

    public int|NoValue $notificationTypes;

    public bool|NoValue $isContactAdditiveInheritance;

    public bool|NoValue $isContactGroupAdditiveInheritance;

    public bool|NoValue $isActivated;

    public int|NoValue $activeChecksEnabled;

    public int|NoValue $passiveCheckEnabled;

    public int|NoValue $volatility;

    public int|NoValue $checkFreshness;

    public int|NoValue $eventHandlerEnabled;

    public int|NoValue $flapDetectionEnabled;

    public int|NoValue $notificationsEnabled;

    public string|null|NoValue $comment;

    public string|null|NoValue $note;

    public string|null|NoValue $noteUrl;

    public string|null|NoValue $actionUrl;

    public string|null|NoValue $iconAlternativeText;

    public int|null|NoValue $graphTemplateId;

    public int|null|NoValue $serviceTemplateParentId;

    public int|null|NoValue $commandId;

    public int|null|NoValue $eventHandlerId;

    public int|NoValue $notificationTimePeriodId;

    public int|NoValue $checkTimePeriodId;

    public int|null|NoValue $iconId;

    public int|null|NoValue $severityId;

    public int|null|NoValue $maxCheckAttempts;

    public int|null|NoValue $normalCheckInterval;

    public int|null|NoValue $retryCheckInterval;

    public int|null|NoValue $freshnessThreshold;

    public int|null|NoValue $lowFlapThreshold;

    public int|null|NoValue $highFlapThreshold;

    public int|null|NoValue $notificationInterval;

    public int|null|NoValue $recoveryNotificationDelay;

    public int|null|NoValue $firstNotificationDelay;

    public int|null|NoValue $acknowledgementTimeout;

    /** @var array<int>|NoValue */
    public array|NoValue $hostTemplates;

    /** @var array<int>|NoValue */
    public array|NoValue $serviceCategories;

    /** @var MacroDto[]|NoValue */
    public array|NoValue $macros;

    public function __construct(public int $id)
    {
        $this->name = new NoValue();
        $this->alias = new NoValue();
        $this->commandArguments = new NoValue();
        $this->eventHandlerArguments = new NoValue();
        $this->notificationTypes = new NoValue();
        $this->isContactAdditiveInheritance = new NoValue();
        $this->isContactGroupAdditiveInheritance = new NoValue();
        $this->isActivated = new NoValue();
        $this->activeChecksEnabled = new NoValue();
        $this->passiveCheckEnabled = new NoValue();
        $this->volatility = new NoValue();
        $this->checkFreshness = new NoValue();
        $this->eventHandlerEnabled = new NoValue();
        $this->flapDetectionEnabled = new NoValue();
        $this->notificationsEnabled = new NoValue();
        $this->comment = new NoValue();
        $this->note = new NoValue();
        $this->noteUrl = new NoValue();
        $this->actionUrl = new NoValue();
        $this->iconAlternativeText = new NoValue();
        $this->graphTemplateId = new NoValue();
        $this->serviceTemplateParentId = new NoValue();
        $this->commandId = new NoValue();
        $this->eventHandlerId = new NoValue();
        $this->notificationTimePeriodId = new NoValue();
        $this->checkTimePeriodId = new NoValue();
        $this->iconId = new NoValue();
        $this->severityId = new NoValue();
        $this->maxCheckAttempts = new NoValue();
        $this->normalCheckInterval = new NoValue();
        $this->retryCheckInterval = new NoValue();
        $this->freshnessThreshold = new NoValue();
        $this->lowFlapThreshold = new NoValue();
        $this->highFlapThreshold = new NoValue();
        $this->notificationInterval = new NoValue();
        $this->recoveryNotificationDelay = new NoValue();
        $this->firstNotificationDelay = new NoValue();
        $this->acknowledgementTimeout = new NoValue();
        $this->hostTemplates = new NoValue();
        $this->serviceCategories = new NoValue();
        $this->macros = new NoValue();
    }
}
