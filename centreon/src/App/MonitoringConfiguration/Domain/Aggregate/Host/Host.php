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
use App\Shared\Domain\Aggregate\VaultScopedInterface;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\VaultInterface;
use Webmozart\Assert\Assert;

/**
 * @extends AggregateRoot<HostId>
 */
final class Host extends AggregateRoot implements AclScopedInterface, PollerScopedInterface, VaultScopedInterface
{
    /**
     * @param Collection<HostTemplateId> $templateIds
     * @param Collection<HostGroupId> $hostGroupIds
     * @param Collection<HostId> $parentHostIds hosts this one depends on
     * @param Collection<HostId> $childHostIds hosts that depend on this one
     * @param Collection<HostCategoryId> $categoryIds levelless `hostcategories` rows; the levelled ones are $severityId
     */
    public function __construct(
        ?HostId $id,
        public readonly HostName $name,
        public readonly ?HostAlias $alias,
        public readonly HostAddress $address,
        // Mutable (the other fields are readonly) so enable()/disable() can toggle it.
        public bool $activated,
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
        public readonly DataProcessing $dataProcessing = new DataProcessing(),
        // Always present, empty by default: the create path populates it; the read/list providers
        // do not surface check options yet.
        public readonly CheckOptions $checkOptions = new CheckOptions(null),
        public readonly ?Notifications $notifications = null,
    ) {
        parent::__construct($id);

        // The only loop visible without the stored graph; the longer ones are the handler's.
        Assert::same(
            array_intersect($this->idValues($parentHostIds), $this->idValues($childHostIds)),
            [],
            'A host cannot be both a parent and a child of this host.',
        );
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
     * Builds a configuration copy of this host under $newName, with no id until it is persisted.
     *
     * Every field is carried over verbatim except the name and the secrets: the SNMP community and the
     * check options are supplied by the caller, because re-minting a vaulted secret into the copy's own
     * vault entry needs the vault (infrastructure), which the aggregate must not reach. Notifications
     * are not carried over, matching what the create path models for a host.
     */
    public function duplicate(HostName $newName, ?SnmpCommunity $snmpCommunity, CheckOptions $checkOptions): self
    {
        return new self(
            id: null,
            name: $newName,
            alias: $this->alias,
            address: $this->address,
            activated: $this->activated,
            pollerId: $this->pollerId,
            templateIds: $this->templateIds,
            hostGroupIds: $this->hostGroupIds,
            categoryIds: $this->categoryIds,
            parentHostIds: $this->parentHostIds,
            childHostIds: $this->childHostIds,
            snmpVersion: $this->snmpVersion,
            snmpCommunity: $snmpCommunity,
            timezoneId: $this->timezoneId,
            severityId: $this->severityId,
            extendedInformations: $this->extendedInformations,
            schedulingOptions: $this->schedulingOptions,
            dataProcessing: $this->dataProcessing,
            checkOptions: $checkOptions,
        );
    }

    /**
     * A copy of this host with the provided properties replaced: NoValue keeps the current one, null
     * clears an optional one. Relations are left as they are.
     */
    public function with(
        NoValue|HostName $name = new NoValue(),
        NoValue|HostAddress $address = new NoValue(),
        NoValue|PollerId $pollerId = new NoValue(),
        NoValue|HostAlias|null $alias = new NoValue(),
        NoValue|SnmpVersionEnum|null $snmpVersion = new NoValue(),
        NoValue|SnmpCommunity|null $snmpCommunity = new NoValue(),
        NoValue|TimezoneId|null $timezoneId = new NoValue(),
        NoValue|HostSeverityId|null $severityId = new NoValue(),
        NoValue|ExtendedInformations|null $extendedInformations = new NoValue(),
        NoValue|SchedulingOptions $schedulingOptions = new NoValue(),
        NoValue|DataProcessing $dataProcessing = new NoValue(),
        NoValue|CheckOptions $checkOptions = new NoValue(),
        NoValue|Notifications|null $notifications = new NoValue(),
        NoValue|bool $activated = new NoValue(),
    ): self {
        return new self(
            id: $this->id(),
            name: NoValue::resolve($name, $this->name),
            alias: NoValue::resolve($alias, $this->alias),
            address: NoValue::resolve($address, $this->address),
            activated: NoValue::resolve($activated, $this->activated),
            pollerId: NoValue::resolve($pollerId, $this->pollerId),
            templateIds: $this->templateIds,
            hostGroupIds: $this->hostGroupIds,
            categoryIds: $this->categoryIds,
            parentHostIds: $this->parentHostIds,
            childHostIds: $this->childHostIds,
            snmpVersion: NoValue::resolve($snmpVersion, $this->snmpVersion),
            snmpCommunity: NoValue::resolve($snmpCommunity, $this->snmpCommunity),
            timezoneId: NoValue::resolve($timezoneId, $this->timezoneId),
            severityId: NoValue::resolve($severityId, $this->severityId),
            extendedInformations: NoValue::resolve($extendedInformations, $this->extendedInformations),
            schedulingOptions: NoValue::resolve($schedulingOptions, $this->schedulingOptions),
            dataProcessing: NoValue::resolve($dataProcessing, $this->dataProcessing),
            checkOptions: NoValue::resolve($checkOptions, $this->checkOptions),
            notifications: NoValue::resolve($notifications, $this->notifications),
        );
    }

    /**
     * Everything but the activation and the relations (templates, groups, categories, parents,
     * children), which are tracked on their own. Contacts and contact groups are still compared,
     * through Notifications::equals().
     */
    public function hasSameConfigurationAs(self $other): bool
    {
        return $this->name->value === $other->name->value
            && $this->alias?->value === $other->alias?->value
            && $this->address->value === $other->address->value
            && $this->pollerId->value === $other->pollerId->value
            && $this->snmpVersion === $other->snmpVersion
            && $this->snmpCommunity?->value === $other->snmpCommunity?->value
            && $this->timezoneId?->value === $other->timezoneId?->value
            && $this->severityId?->value === $other->severityId?->value
            && $this->schedulingOptions->equals($other->schedulingOptions)
            && $this->dataProcessing->equals($other->dataProcessing)
            && $this->checkOptions->equals($other->checkOptions)
            && $this->hasSameExtendedInformationsAs($other->extendedInformations)
            && $this->hasSameNotificationsAs($other->notifications);
    }

    /**
     * The UUID of this host's vault entry, if it has one, or null when none of its vault-eligible
     * fields currently hold a `secret::` reference (vault disabled, or nothing vaulted yet).
     *
     * Checked in the same order as legacy (`retrieveHostUuidFromVault`): the SNMP community first,
     * then the first password macro that is vaulted — every vault-eligible field of a given
     * resource shares one entry (one UUID), so the first match found settles it.
     */
    public function getVaultUuid(VaultInterface $vault): ?string
    {
        if ($this->snmpCommunity instanceof SnmpCommunity && $vault->isVaultPath($this->snmpCommunity->value)) {
            return $vault->extractUuid($this->snmpCommunity->value);
        }

        foreach ($this->checkOptions->macros as $macro) {
            if ($macro->isPassword && $vault->isVaultPath($macro->value)) {
                return $vault->extractUuid($macro->value);
            }
        }

        return null;
    }

    /**
     * Whether saving this host left the vault entry $before kept its secrets in without any secret:
     * $before referenced an entry and none of this host's vault-eligible fields (SNMP community,
     * password macros) references it any more. The entry is then empty — or only holds keys no host
     * field points to — and can be deleted once the save is committed.
     */
    public function releasesVaultEntryOf(self $before, VaultInterface $vault): bool
    {
        $uuid = $before->getVaultUuid($vault);

        return $uuid !== null && ! $this->referencesVaultEntry($uuid, $vault);
    }

    private function hasSameExtendedInformationsAs(?ExtendedInformations $other): bool
    {
        return $this->extendedInformations instanceof ExtendedInformations && $other instanceof ExtendedInformations
            ? $this->extendedInformations->equals($other)
            : $this->extendedInformations === $other;
    }

    private function hasSameNotificationsAs(?Notifications $other): bool
    {
        return ($this->notifications ?? Notifications::default())->equals($other ?? Notifications::default());
    }

    private function referencesVaultEntry(string $uuid, VaultInterface $vault): bool
    {
        $values = array_map(
            static fn (HostMacro $macro): string => $macro->value,
            array_filter($this->checkOptions->macros, static fn (HostMacro $macro): bool => $macro->isPassword),
        );
        if ($this->snmpCommunity instanceof SnmpCommunity) {
            $values[] = $this->snmpCommunity->value;
        }

        return array_any(
            $values,
            static fn (string $value): bool => $vault->isVaultPath($value) && $vault->extractUuid($value) === $uuid,
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
