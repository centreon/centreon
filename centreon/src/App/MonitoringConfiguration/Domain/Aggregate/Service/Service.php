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
use App\Shared\Domain\NoValue;
use App\Shared\Domain\VaultInterface;

/**
 * A real service directly linked to its one host: its own configuration (what it inherits from its templates is
 * not part of it), its relations by id, and the state of its host as loaded with it (activation, poller), which
 * decides the poller to flag when the service changes.
 *
 * A listing reads only part of it (activation, template, own check intervals, own icon); the other fields then
 * keep their defaults.
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
        public readonly ServiceName $name,
        public readonly HostId $hostId,
        public readonly bool $hostActivated,
        public readonly ?PollerId $hostPollerId,
        // Mutable (the other fields are readonly) so enable()/disable() can toggle it.
        public bool $activated = true,
        public readonly ?ServiceTemplateId $templateId = null,
        public readonly ServiceCheckOptions $checkOptions = new ServiceCheckOptions(null),
        public readonly ServiceSchedulingOptions $schedulingOptions = new ServiceSchedulingOptions(),
        public readonly ?ServiceNotifications $notifications = null,
        public readonly ServiceDataProcessing $dataProcessing = new ServiceDataProcessing(),
        public readonly ?ServiceExtendedInformations $extendedInformations = null,
        public readonly ?GraphTemplateId $graphTemplateId = null,
        public readonly ?ServiceSeverityId $severityId = null,
        public readonly Collection $serviceGroupIds = new Collection([], ServiceGroupId::class),
        public readonly Collection $categoryIds = new Collection([], ServiceCategoryId::class),
        public readonly Collection $trapIds = new Collection([], TrapId::class),
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

    /**
     * A copy of this service with the provided properties replaced: NoValue keeps the current one, null clears an
     * optional one. A new host comes with its own state (activation, poller), which decides the poller to flag.
     *
     * @param NoValue|Collection<ServiceGroupId> $serviceGroupIds
     * @param NoValue|Collection<ServiceCategoryId> $categoryIds
     * @param NoValue|Collection<TrapId> $trapIds
     */
    public function with(
        NoValue|ServiceName $name = new NoValue(),
        NoValue|HostId $hostId = new NoValue(),
        NoValue|bool $hostActivated = new NoValue(),
        NoValue|PollerId|null $hostPollerId = new NoValue(),
        NoValue|bool $activated = new NoValue(),
        NoValue|ServiceTemplateId|null $templateId = new NoValue(),
        NoValue|ServiceCheckOptions $checkOptions = new NoValue(),
        NoValue|ServiceSchedulingOptions $schedulingOptions = new NoValue(),
        NoValue|ServiceNotifications|null $notifications = new NoValue(),
        NoValue|ServiceDataProcessing $dataProcessing = new NoValue(),
        NoValue|ServiceExtendedInformations|null $extendedInformations = new NoValue(),
        NoValue|GraphTemplateId|null $graphTemplateId = new NoValue(),
        NoValue|ServiceSeverityId|null $severityId = new NoValue(),
        NoValue|Collection $serviceGroupIds = new NoValue(),
        NoValue|Collection $categoryIds = new NoValue(),
        NoValue|Collection $trapIds = new NoValue(),
    ): self {
        return new self(
            id: $this->id(),
            name: NoValue::resolve($name, $this->name),
            hostId: NoValue::resolve($hostId, $this->hostId),
            hostActivated: NoValue::resolve($hostActivated, $this->hostActivated),
            hostPollerId: NoValue::resolve($hostPollerId, $this->hostPollerId),
            activated: NoValue::resolve($activated, $this->activated),
            templateId: NoValue::resolve($templateId, $this->templateId),
            checkOptions: NoValue::resolve($checkOptions, $this->checkOptions),
            schedulingOptions: NoValue::resolve($schedulingOptions, $this->schedulingOptions),
            notifications: NoValue::resolve($notifications, $this->notifications),
            dataProcessing: NoValue::resolve($dataProcessing, $this->dataProcessing),
            extendedInformations: NoValue::resolve($extendedInformations, $this->extendedInformations),
            graphTemplateId: NoValue::resolve($graphTemplateId, $this->graphTemplateId),
            severityId: NoValue::resolve($severityId, $this->severityId),
            serviceGroupIds: NoValue::resolve($serviceGroupIds, $this->serviceGroupIds),
            categoryIds: NoValue::resolve($categoryIds, $this->categoryIds),
            trapIds: NoValue::resolve($trapIds, $this->trapIds),
        );
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
