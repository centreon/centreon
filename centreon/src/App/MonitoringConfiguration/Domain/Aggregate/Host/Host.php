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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\PollerScopedInterface;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * @extends AggregateRoot<HostId>
 */
final class Host extends AggregateRoot implements AclScopedInterface, PollerScopedInterface
{
    /**
     * @param Collection<HostTemplateId> $templateIds ordered: the position is the persisted
     *                                                inheritance order
     * @param Collection<HostGroupId> $hostGroupIds
     * @param Collection<HostCategoryId> $categoryIds
     * @param Collection<HostId> $parentHostIds
     * @param Collection<HostId> $childHostIds
     */
    public function __construct(
        ?HostId $id,
        public readonly HostName $name,
        public readonly ?HostAlias $alias,
        public readonly HostAddress $address,
        public readonly bool $activated,
        public readonly PollerId $pollerId,
        public readonly Collection $templateIds,
        public readonly Collection $hostGroupIds,
        public readonly Collection $categoryIds = new Collection([], HostCategoryId::class),
        public readonly Collection $parentHostIds = new Collection([], HostId::class),
        public readonly Collection $childHostIds = new Collection([], HostId::class),
        public readonly ?SnmpVersionEnum $snmpVersion = null,
        public readonly ?SnmpCommunity $snmpCommunity = null,
        public readonly ?TimezoneId $timezoneId = null,
        public readonly ?HostSeverityId $severityId = null,
        public readonly ?ExtendedInformations $extendedInformations = null,
        public readonly SchedulingOptions $schedulingOptions = new SchedulingOptions(),
    ) {
        parent::__construct($id);

        // The only loop visible without the stored graph; the longer ones are the handler's.
        Assert::same(
            array_intersect($this->idValues($parentHostIds), $this->idValues($childHostIds)),
            [],
            'A host cannot be both a parent and a child of this host.',
        );
    }

    /**
     * @param Collection<HostId> $hostIds
     *
     * @return array<int>
     */
    private function idValues(Collection $hostIds): array
    {
        return array_map(static fn (HostId $hostId): int => $hostId->value, $hostIds->toArray());
    }
}
