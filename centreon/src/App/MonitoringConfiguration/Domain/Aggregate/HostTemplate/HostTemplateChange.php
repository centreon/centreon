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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\Shared\Domain\NoValue;

/**
 * What a write changes on a template: NoValue keeps the current value, null clears a clearable one.
 */
final readonly class HostTemplateChange
{
    public function __construct(
        public NoValue|HostTemplateName $name = new NoValue(),
        public NoValue|HostTemplateAlias $alias = new NoValue(),
        public NoValue|HostTemplateSnmpVersionEnum|null $snmpVersion = new NoValue(),
        public NoValue|HostTemplateSnmpCommunity|null $snmpCommunity = new NoValue(),
        public NoValue|TimezoneId|null $timezoneId = new NoValue(),
        public NoValue|HostSeverityId|null $severityId = new NoValue(),
        public NoValue|HostTemplateCheckOptionsChange $checkOptions = new NoValue(),
        public NoValue|HostTemplateExtendedInformationsChange $extendedInformations = new NoValue(),
        public NoValue|HostTemplateSchedulingOptionsChange $schedulingOptions = new NoValue(),
        public NoValue|HostTemplateDataProcessingChange $dataProcessing = new NoValue(),
        public NoValue|HostTemplateNotificationsChange $notifications = new NoValue(),
    ) {
    }

    public function withSnmpCommunity(NoValue|HostTemplateSnmpCommunity|null $snmpCommunity): self
    {
        return new self(
            $this->name,
            $this->alias,
            $this->snmpVersion,
            $snmpCommunity,
            $this->timezoneId,
            $this->severityId,
            $this->checkOptions,
            $this->extendedInformations,
            $this->schedulingOptions,
            $this->dataProcessing,
            $this->notifications,
        );
    }

    public function withSeverityId(NoValue|HostSeverityId|null $severityId): self
    {
        return new self(
            $this->name,
            $this->alias,
            $this->snmpVersion,
            $this->snmpCommunity,
            $this->timezoneId,
            $severityId,
            $this->checkOptions,
            $this->extendedInformations,
            $this->schedulingOptions,
            $this->dataProcessing,
            $this->notifications,
        );
    }

    public function withDataProcessing(HostTemplateDataProcessingChange $dataProcessing): self
    {
        return new self(
            $this->name,
            $this->alias,
            $this->snmpVersion,
            $this->snmpCommunity,
            $this->timezoneId,
            $this->severityId,
            $this->checkOptions,
            $this->extendedInformations,
            $this->schedulingOptions,
            $dataProcessing,
            $this->notifications,
        );
    }

    public function withNotifications(HostTemplateNotificationsChange $notifications): self
    {
        return new self(
            $this->name,
            $this->alias,
            $this->snmpVersion,
            $this->snmpCommunity,
            $this->timezoneId,
            $this->severityId,
            $this->checkOptions,
            $this->extendedInformations,
            $this->schedulingOptions,
            $this->dataProcessing,
            $notifications,
        );
    }
}
