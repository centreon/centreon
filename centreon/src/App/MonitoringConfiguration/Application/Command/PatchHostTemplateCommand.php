<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Domain\NoValue;

/**
 * A partial update: NoValue leaves a field untouched, null clears a clearable one. The lists are
 * replaced, added to or removed from.
 */
final readonly class PatchHostTemplateCommand
{
    /**
     * @param NoValue|ListChange<HostTemplateId> $templateIds
     * @param NoValue|ListChange<HostCategoryId> $categoryIds
     * @param NoValue|ListChange<ServiceTemplateId> $linkedServiceTemplateIds
     * @param NoValue|ListChange<NotificationContactId> $contactIds
     * @param NoValue|ListChange<ContactGroupId> $contactGroupIds
     * @param NoValue|ListChange<HostTemplateNotificationOptionEnum> $notificationOptions
     */
    public function __construct(
        public HostTemplateId $id,
        public ActingUser $actor,
        public HostTemplateChange $change,
        #[Sensitive]
        public NoValue|string|null $snmpCommunity = new NoValue(),
        public NoValue|ListChange $templateIds = new NoValue(),
        public NoValue|ListChange $categoryIds = new NoValue(),
        public NoValue|ListChange $linkedServiceTemplateIds = new NoValue(),
        public NoValue|ListChange $contactIds = new NoValue(),
        public NoValue|ListChange $contactGroupIds = new NoValue(),
        public NoValue|ListChange $notificationOptions = new NoValue(),
        public NoValue|HostTemplateMacroChanges $macros = new NoValue(),
    ) {
    }
}
