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

use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;

final readonly class CreateHostCommand
{
    /**
     * @param Collection<HostGroupId> $hostGroupIds
     * @param ?UserId $viewerId null for an unrestricted viewer. It scopes the poller, host group,
     *                          category and severity checks; templates, timezone and parent/child
     *                          hosts are not scoped, which legacy does not scope either
     * @param Collection<HostTemplateId> $templateIds ordered: the position becomes the persisted
     *                                                inheritance order
     * @param Collection<HostCategoryId> $categoryIds
     * @param Collection<HostId> $parentHostIds
     * @param Collection<HostId> $childHostIds
     */
    public function __construct(
        public HostName $name,
        public HostAddress $address,
        public PollerId $pollerId,
        public Collection $hostGroupIds,
        public int $creatorId,
        public ?UserId $viewerId = null,
        public ?HostAlias $alias = null,
        public Collection $templateIds = new Collection([], HostTemplateId::class),
        public Collection $categoryIds = new Collection([], HostCategoryId::class),
        public Collection $parentHostIds = new Collection([], HostId::class),
        public Collection $childHostIds = new Collection([], HostId::class),
        public ?SnmpVersionEnum $snmpVersion = null,
        public ?string $snmpCommunity = null,
        public ?TimezoneId $timezoneId = null,
        public ?HostSeverityId $severityId = null,
        public bool $deployServicesFromTemplates = true,
        public ?ExtendedInformations $extendedInformations = null,
        public SchedulingOptions $schedulingOptions = new SchedulingOptions(),
    ) {
    }
}
