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
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Domain\NoValue;

/**
 * A partial update: every field left to {@see NoValue} is untouched, null clears the ones that can be cleared,
 * anything else replaces the current value.
 */
final readonly class PatchServiceCommand
{
    /**
     * @param ActingUser $actor the acting user: the action-log actor, whose ACL scope also bounds the
     *                          service lookup, so a service outside it is reported as not found rather
     *                          than leaking its existence
     * @param NoValue|ListChange<ServiceGroupId> $serviceGroupIds
     * @param NoValue|ListChange<ServiceCategoryId> $categoryIds
     * @param NoValue|ListChange<TrapId> $trapIds
     * @param NoValue|ListChange<NotificationContactId> $contactIds
     * @param NoValue|ListChange<ContactGroupId> $contactGroupIds
     */
    public function __construct(
        public ServiceId $id,
        public ActingUser $actor,
        public NoValue|bool $activated = new NoValue(),
        public NoValue|ServiceName $name = new NoValue(),
        public NoValue|HostId $hostId = new NoValue(),
        public NoValue|ServiceTemplateId|null $templateId = new NoValue(),
        public NoValue|ServiceSeverityId|null $severityId = new NoValue(),
        public NoValue|GraphTemplateId|null $graphTemplateId = new NoValue(),
        public NoValue|ListChange $serviceGroupIds = new NoValue(),
        public NoValue|ListChange $categoryIds = new NoValue(),
        public NoValue|ListChange $trapIds = new NoValue(),
        public NoValue|ServiceCheckOptionsChanges $checkOptions = new NoValue(),
        // A change may carry a password macro's plaintext.
        #[Sensitive]
        public NoValue|ServiceMacroChanges $macros = new NoValue(),
        public NoValue|ServiceSchedulingOptionsChanges $schedulingOptions = new NoValue(),
        public NoValue|ServiceExtendedInformationsChanges $extendedInformations = new NoValue(),
        public NoValue|ServiceNotificationsChanges $notifications = new NoValue(),
        public NoValue|ListChange $contactIds = new NoValue(),
        public NoValue|ListChange $contactGroupIds = new NoValue(),
        public NoValue|ServiceDataProcessingChanges $dataProcessing = new NoValue(),
    ) {
    }
}
