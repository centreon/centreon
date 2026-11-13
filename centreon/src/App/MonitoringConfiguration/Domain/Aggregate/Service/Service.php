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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\PollerScopedInterface;
use App\Shared\Domain\Aggregate\VaultScopedInterface;
use App\Shared\Domain\Collection;
use App\Shared\Domain\VaultInterface;

/**
 * A real service on its one host: own configuration, relations by id, and its host's state as loaded. A listing
 * hydrates only part of it.
 *
 * @extends AggregateRoot<ServiceId>
 */
final class Service extends AggregateRoot implements AclScopedInterface, PollerScopedInterface, VaultScopedInterface
{
    /**
     * @param bool $hostActivated whether its host is enabled, as loaded with the service
     * @param ?PollerId $hostPollerId the poller of its host, as loaded with the service, null when the host has none
     * @param Collection<ServiceGroupId> $serviceGroupIds the groups it belongs to on its host
     * @param Collection<ServiceCategoryId> $categoryIds levelless categories; the levelled one is $severityId
     * @param Collection<TrapId> $trapIds
     */
    public function __construct(
        ?ServiceId $id,
        public ServiceName $name,
        public HostId $hostId,
        public bool $hostActivated,
        public ?PollerId $hostPollerId,
        public bool $activated = true,
        public ?ServiceTemplateId $templateId = null,
        public ServiceCheckOptions $checkOptions = new ServiceCheckOptions(null),
        public ServiceSchedulingOptions $schedulingOptions = new ServiceSchedulingOptions(),
        public ?ServiceNotifications $notifications = null,
        public ServiceDataProcessing $dataProcessing = new ServiceDataProcessing(),
        public ?ServiceExtendedInformations $extendedInformations = null,
        public ?GraphTemplateId $graphTemplateId = null,
        public ?ServiceSeverityId $severityId = null,
        public Collection $serviceGroupIds = new Collection([], ServiceGroupId::class),
        public Collection $categoryIds = new Collection([], ServiceCategoryId::class),
        public Collection $trapIds = new Collection([], TrapId::class),
    ) {
        parent::__construct($id);
    }

    /**
     * The poller whose configuration must be regenerated when this service changes: the one of its host, and
     * only while that host is enabled (a disabled host is not monitored, so its poller has nothing to reload).
     * Read from the host state loaded with the service, so it is still known once the service row is deleted.
     */
    public function pollerToFlag(): ?PollerId
    {
        return $this->hostActivated ? $this->hostPollerId : null;
    }

    public function enable(): void
    {
        $this->activated = true;
    }

    public function disable(): void
    {
        $this->activated = false;
    }

    /**
     * The UUID of this service's vault entry, if it has one, or null when none of its password
     * macros currently hold a `secret::` reference.
     *
     * Unlike {@see \App\MonitoringConfiguration\Domain\Aggregate\Host\Host::getVaultUuid()}, there
     * is no SNMP community to check first — services have no SNMP settings.
     */
    public function getVaultUuid(VaultInterface $vault): ?string
    {
        foreach ($this->checkOptions->macros as $macro) {
            if ($macro->isPassword && $vault->isVaultPath($macro->value)) {
                return $vault->extractUuid($macro->value);
            }
        }

        return null;
    }

    public function rename(ServiceName $name): void
    {
        $this->name = $name;
    }

    public function moveToHost(HostId $hostId, HostState $host): void
    {
        $this->hostId = $hostId;
        $this->hostActivated = $host->activated;
        $this->hostPollerId = $host->pollerId;
    }

    public function changeTemplate(?ServiceTemplateId $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function changeCheckOptions(ServiceCheckOptions $checkOptions): void
    {
        $this->checkOptions = $checkOptions;
    }

    public function changeSchedulingOptions(ServiceSchedulingOptions $schedulingOptions): void
    {
        $this->schedulingOptions = $schedulingOptions;
    }

    public function changeNotifications(?ServiceNotifications $notifications): void
    {
        $this->notifications = $notifications;
    }

    public function changeDataProcessing(ServiceDataProcessing $dataProcessing): void
    {
        $this->dataProcessing = $dataProcessing;
    }

    public function changeExtendedInformations(?ServiceExtendedInformations $extendedInformations): void
    {
        $this->extendedInformations = $extendedInformations;
    }

    public function changeGraphTemplate(?GraphTemplateId $graphTemplateId): void
    {
        $this->graphTemplateId = $graphTemplateId;
    }

    /**
     * The relations {@see hasSameRelationsAs()} compares, changed together.
     *
     * @param Collection<ServiceGroupId> $serviceGroupIds
     * @param Collection<ServiceCategoryId> $categoryIds
     * @param Collection<TrapId> $trapIds
     */
    public function changeRelations(
        ?ServiceSeverityId $severityId,
        Collection $serviceGroupIds,
        Collection $categoryIds,
        Collection $trapIds,
    ): void {
        $this->severityId = $severityId;
        $this->serviceGroupIds = $serviceGroupIds;
        $this->categoryIds = $categoryIds;
        $this->trapIds = $trapIds;
    }

    /**
     * Every field the activity log records, contacts and contact groups included (through
     * ServiceNotifications::equals()), the host among them: everything but the activation, the state of the host
     * as loaded, and the relations, which {@see hasSameRelationsAs()} compares.
     */
    public function hasSameConfigurationAs(self $other): bool
    {
        return $this->name->value === $other->name->value
            && $this->hostId->value === $other->hostId->value
            && $this->templateId?->value === $other->templateId?->value
            && $this->graphTemplateId?->value === $other->graphTemplateId?->value
            && $this->checkOptions->equals($other->checkOptions)
            && $this->schedulingOptions->equals($other->schedulingOptions)
            && $this->dataProcessing->equals($other->dataProcessing)
            && $this->hasSameNotificationsAs($other->notifications)
            && $this->hasSameExtendedInformationsAs($other->extendedInformations);
    }

    /**
     * The relations left out of {@see hasSameConfigurationAs()}, none of which the activity log records: service
     * groups, categories and traps compared as sets, and the severity.
     */
    public function hasSameRelationsAs(self $other): bool
    {
        return $this->severityId?->value === $other->severityId?->value
            && $this->sortedIdValues($this->serviceGroupIds) === $this->sortedIdValues($other->serviceGroupIds)
            && $this->sortedIdValues($this->categoryIds) === $this->sortedIdValues($other->categoryIds)
            && $this->sortedIdValues($this->trapIds) === $this->sortedIdValues($other->trapIds);
    }

    /**
     * Whether saving this service left the vault entry $before kept its secrets in without any secret: $before
     * referenced an entry and none of this service's password macros references it any more. The entry is then
     * empty, or only holds keys no macro points to, and can be deleted once the save is committed.
     */
    public function releasesVaultEntryOf(self $before, VaultInterface $vault): bool
    {
        $uuid = $before->getVaultUuid($vault);

        return $uuid !== null && ! array_any(
            $this->checkOptions->macros,
            static fn (ServiceMacro $macro): bool => $macro->isPassword
                && $vault->isVaultPath($macro->value)
                && $vault->extractUuid($macro->value) === $uuid,
        );
    }

    private function hasSameNotificationsAs(?ServiceNotifications $other): bool
    {
        return $this->notifications instanceof ServiceNotifications && $other instanceof ServiceNotifications
            ? $this->notifications->equals($other)
            : $this->notifications === $other;
    }

    private function hasSameExtendedInformationsAs(?ServiceExtendedInformations $other): bool
    {
        return $this->extendedInformations instanceof ServiceExtendedInformations && $other instanceof ServiceExtendedInformations
            ? $this->extendedInformations->equals($other)
            : $this->extendedInformations === $other;
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int> without duplicates
     */
    private function sortedIdValues(Collection $ids): array
    {
        $values = array_values(array_unique(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray())));
        sort($values);

        return $values;
    }
}
