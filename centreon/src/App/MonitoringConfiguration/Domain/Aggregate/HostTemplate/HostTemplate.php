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

use App\MonitoringConfiguration\Domain\Aggregate\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Exception\DuplicateHostTemplateMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\VaultInterface;
use Webmozart\Assert\Assert;

/**
 * @extends AggregateRoot<HostTemplateId>
 */
final class HostTemplate extends AggregateRoot
{
    // the int(11) columns
    public const MAX_INTEGER = 2147483647;

    /**
     * @param Collection<CommandId> $serviceTemplateCheckCommandIds linked service templates' check commands, then their templates', in priority order
     * @param Collection<HostTemplateId> $parentTemplateIds direct parents, in relation order
     * @param ?SnmpCommunity $snmpCommunity plaintext, or the `secret::` reference once vaulted
     * @param ?HostSeverityId $severityId the levelled `hostcategories` row; the levelless ones are $categoryIds
     * @param Collection<HostCategoryId> $categoryIds
     * @param Collection<ServiceTemplateId> $linkedServiceTemplateIds in relation order
     */
    public function __construct(
        ?HostTemplateId $id,
        public readonly HostTemplateName $name,
        public readonly CheckOptions $checkOptions = new CheckOptions(null),
        public readonly Collection $serviceTemplateCheckCommandIds = new Collection([], CommandId::class),
        public readonly ?HostAlias $alias = null,
        public readonly bool $isLocked = false,
        public readonly Collection $parentTemplateIds = new Collection([], HostTemplateId::class),
        public readonly ?SnmpCommunity $snmpCommunity = null,
        public readonly ?SnmpVersionEnum $snmpVersion = null,
        public readonly ?TimezoneId $timezoneId = null,
        public readonly ?HostSeverityId $severityId = null,
        public readonly ?ExtendedInformations $extendedInformations = null,
        public readonly SchedulingOptions $schedulingOptions = new SchedulingOptions(),
        public readonly DataProcessing $dataProcessing = new DataProcessing(),
        public readonly ?Notifications $notifications = null,
        public readonly Collection $categoryIds = new Collection([], HostCategoryId::class),
        public readonly Collection $linkedServiceTemplateIds = new Collection([], ServiceTemplateId::class),
    ) {
        parent::__construct($id);
        Assert::null($extendedInformations?->geoCoordinates, 'A host template has no geographic coordinates.');
    }

    /**
     * A new template, not yet persisted, whose numbers must fit their int(11) columns.
     *
     * @param Collection<HostTemplateId> $parentTemplateIds in inheritance order
     * @param Collection<HostCategoryId> $categoryIds
     * @param Collection<ServiceTemplateId> $linkedServiceTemplateIds in relation order
     *
     * @throws \InvalidArgumentException when a number does not fit its column
     */
    public static function create(
        HostTemplateName $name,
        HostAlias $alias,
        CheckOptions $checkOptions = new CheckOptions(null),
        Collection $parentTemplateIds = new Collection([], HostTemplateId::class),
        ?SnmpCommunity $snmpCommunity = null,
        ?SnmpVersionEnum $snmpVersion = null,
        ?TimezoneId $timezoneId = null,
        ?HostSeverityId $severityId = null,
        ?ExtendedInformations $extendedInformations = null,
        SchedulingOptions $schedulingOptions = new SchedulingOptions(),
        DataProcessing $dataProcessing = new DataProcessing(),
        ?Notifications $notifications = null,
        Collection $categoryIds = new Collection([], HostCategoryId::class),
        Collection $linkedServiceTemplateIds = new Collection([], ServiceTemplateId::class),
    ): self {
        self::assertSchedulingOptionsBounds($schedulingOptions);
        self::assertDataProcessingBounds($dataProcessing);
        if ($notifications instanceof Notifications) {
            self::assertNotificationsBounds($notifications);
        }

        return new self(
            id: null,
            name: $name,
            checkOptions: $checkOptions,
            alias: $alias,
            parentTemplateIds: $parentTemplateIds,
            snmpCommunity: $snmpCommunity,
            snmpVersion: $snmpVersion,
            timezoneId: $timezoneId,
            severityId: $severityId,
            extendedInformations: $extendedInformations,
            schedulingOptions: $schedulingOptions,
            dataProcessing: $dataProcessing,
            notifications: $notifications,
            categoryIds: $categoryIds,
            linkedServiceTemplateIds: $linkedServiceTemplateIds,
        );
    }

    /**
     * A copy of this template with the provided properties replaced: NoValue keeps the current one, null
     * clears an optional one.
     *
     * @param NoValue|Collection<HostTemplateId> $parentTemplateIds
     * @param NoValue|Collection<HostCategoryId> $categoryIds
     * @param NoValue|Collection<ServiceTemplateId> $linkedServiceTemplateIds
     *
     * @throws HostTemplateLockedException
     * @throws \InvalidArgumentException when a number does not fit its column
     */
    public function with(
        NoValue|HostTemplateName $name = new NoValue(),
        NoValue|HostAlias $alias = new NoValue(),
        NoValue|SnmpVersionEnum|null $snmpVersion = new NoValue(),
        NoValue|SnmpCommunity|null $snmpCommunity = new NoValue(),
        NoValue|TimezoneId|null $timezoneId = new NoValue(),
        NoValue|HostSeverityId|null $severityId = new NoValue(),
        NoValue|CheckOptions $checkOptions = new NoValue(),
        NoValue|ExtendedInformations|null $extendedInformations = new NoValue(),
        NoValue|SchedulingOptions $schedulingOptions = new NoValue(),
        NoValue|DataProcessing $dataProcessing = new NoValue(),
        NoValue|Notifications|null $notifications = new NoValue(),
        NoValue|Collection $parentTemplateIds = new NoValue(),
        NoValue|Collection $categoryIds = new NoValue(),
        NoValue|Collection $linkedServiceTemplateIds = new NoValue(),
    ): self {
        $this->assertUnlocked();

        if ($schedulingOptions instanceof SchedulingOptions) {
            self::assertSchedulingOptionsBounds($schedulingOptions);
        }
        if ($dataProcessing instanceof DataProcessing) {
            self::assertDataProcessingBounds($dataProcessing);
        }
        if ($notifications instanceof Notifications) {
            self::assertNotificationsBounds($notifications);
        }

        return new self(
            id: $this->id(),
            name: NoValue::resolve($name, $this->name),
            checkOptions: NoValue::resolve($checkOptions, $this->checkOptions),
            serviceTemplateCheckCommandIds: $this->serviceTemplateCheckCommandIds,
            alias: NoValue::resolve($alias, $this->alias),
            isLocked: $this->isLocked,
            parentTemplateIds: NoValue::resolve($parentTemplateIds, $this->parentTemplateIds),
            snmpCommunity: NoValue::resolve($snmpCommunity, $this->snmpCommunity),
            snmpVersion: NoValue::resolve($snmpVersion, $this->snmpVersion),
            timezoneId: NoValue::resolve($timezoneId, $this->timezoneId),
            severityId: NoValue::resolve($severityId, $this->severityId),
            extendedInformations: NoValue::resolve($extendedInformations, $this->extendedInformations),
            schedulingOptions: NoValue::resolve($schedulingOptions, $this->schedulingOptions),
            dataProcessing: NoValue::resolve($dataProcessing, $this->dataProcessing),
            notifications: NoValue::resolve($notifications, $this->notifications),
            categoryIds: NoValue::resolve($categoryIds, $this->categoryIds),
            linkedServiceTemplateIds: NoValue::resolve($linkedServiceTemplateIds, $this->linkedServiceTemplateIds),
        );
    }

    /**
     * @param list<HostMacro> $macros the template's own macros, replacing the current ones
     *
     * @throws HostTemplateLockedException
     * @throws DuplicateHostTemplateMacroNameException
     */
    public function withMacros(array $macros): self
    {
        $names = array_map(static fn (HostMacro $macro): string => $macro->name->value, $macros);
        $duplicates = array_keys(array_filter(array_count_values($names), static fn (int $count): bool => $count > 1));
        if ($duplicates !== []) {
            throw new DuplicateHostTemplateMacroNameException(array_map(strval(...), $duplicates));
        }

        return $this->with(checkOptions: $this->checkOptions->with(macros: $macros));
    }

    /**
     * Every field but the relations and the macros; contacts and contact groups count through the notifications.
     */
    public function hasSameConfigurationAs(self $other): bool
    {
        return $this->hasSameLoggedConfigurationAs($other) && $this->hasSameContactsAs($other);
    }

    /**
     * The fields the activity log records: the configuration without the contacts and contact groups.
     */
    public function hasSameLoggedConfigurationAs(self $other): bool
    {
        $notifications = $this->notifications ?? Notifications::default();
        $otherNotifications = $other->notifications ?? Notifications::default();

        return $this->name->value === $other->name->value
            && $this->alias?->value === $other->alias?->value
            && $this->snmpVersion === $other->snmpVersion
            && $this->snmpCommunity?->value === $other->snmpCommunity?->value
            && $this->timezoneId?->value === $other->timezoneId?->value
            && $this->severityId?->value === $other->severityId?->value
            && $this->checkOptions->with(macros: [])->equals($other->checkOptions->with(macros: []))
            && $this->schedulingOptions->equals($other->schedulingOptions)
            && $this->dataProcessing->equals($other->dataProcessing)
            && ($this->extendedInformations ?? new ExtendedInformations())->equals($other->extendedInformations ?? new ExtendedInformations())
            && $notifications->withContacts($otherNotifications->contactIds, $otherNotifications->contactGroupIds)->equals($otherNotifications);
    }

    /**
     * The parents and the linked service templates in order (it sets the inheritance and the macro
     * priority), the categories as a set.
     */
    public function hasSameRelationsAs(self $other): bool
    {
        return $this->idValues($this->parentTemplateIds) === $this->idValues($other->parentTemplateIds)
            && $this->idValues($this->linkedServiceTemplateIds) === $this->idValues($other->linkedServiceTemplateIds)
            && $this->sortedIdValues($this->categoryIds) === $this->sortedIdValues($other->categoryIds);
    }

    public function hasSameContactsAs(self $other): bool
    {
        return ($this->notifications ?? Notifications::default())->hasSameContactsAs($other->notifications ?? Notifications::default());
    }

    public function hasSameMacrosAs(self $other): bool
    {
        return $this->checkOptions->hasSameMacrosAs($other->checkOptions);
    }

    /**
     * Whether $before kept its secrets in a vault entry this template no longer references: the entry
     * can then be deleted once the write is committed.
     */
    public function releasesVaultEntryOf(self $before, VaultInterface $vault): bool
    {
        $uuid = $before->getVaultUuid($vault);

        return $uuid !== null && $this->getVaultUuid($vault) !== $uuid;
    }

    /**
     * @throws HostTemplateLockedException
     */
    public function assertUnlocked(): void
    {
        if ($this->isLocked) {
            throw HostTemplateLockedException::forId($this->id());
        }
    }

    /**
     * A copy not yet persisted, always unlocked, with its own name, secrets and macros.
     *
     * @param list<HostMacro> $macros the copy's own macros, without source identity
     *
     * @throws HostTemplateLockedException
     */
    public function duplicate(HostTemplateName $newName, ?SnmpCommunity $snmpCommunity, array $macros): self
    {
        $this->assertUnlocked();

        return new self(
            id: null,
            name: $newName,
            checkOptions: $this->checkOptions->with(macros: $macros),
            serviceTemplateCheckCommandIds: $this->serviceTemplateCheckCommandIds,
            alias: $this->alias,
            isLocked: false,
            parentTemplateIds: $this->parentTemplateIds,
            snmpCommunity: $snmpCommunity,
            snmpVersion: $this->snmpVersion,
            timezoneId: $this->timezoneId,
            severityId: $this->severityId,
            extendedInformations: $this->extendedInformations,
            schedulingOptions: $this->schedulingOptions,
            dataProcessing: $this->dataProcessing,
            notifications: $this->notifications,
            categoryIds: $this->categoryIds,
            linkedServiceTemplateIds: $this->linkedServiceTemplateIds,
        );
    }

    /**
     * The vault entry holding the template's secrets, null when none is vaulted.
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
     * The template's own macros, as a host inheriting from it sees them.
     *
     * @return list<HostMacro>
     */
    public function macrosAsInherited(): array
    {
        return array_map(
            static fn (HostMacro $macro): HostMacro => $macro->inheritedFrom(HostMacroParentEnum::Template),
            $this->checkOptions->macros,
        );
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function idValues(Collection $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function sortedIdValues(Collection $ids): array
    {
        $values = $this->idValues($ids);
        sort($values);

        return $values;
    }

    private static function assertSchedulingOptionsBounds(SchedulingOptions $options): void
    {
        Assert::nullOrLessThanEq($options->maxCheckAttempts, self::MAX_INTEGER);
        Assert::nullOrLessThanEq($options->normalCheckInterval, self::MAX_INTEGER);
        Assert::nullOrLessThanEq($options->retryCheckInterval, self::MAX_INTEGER);
    }

    private static function assertDataProcessingBounds(DataProcessing $dataProcessing): void
    {
        Assert::nullOrLessThanEq($dataProcessing->acknowledgmentTimeout, self::MAX_INTEGER);
        Assert::nullOrLessThanEq($dataProcessing->freshnessThreshold, self::MAX_INTEGER);
    }

    private static function assertNotificationsBounds(Notifications $notifications): void
    {
        Assert::nullOrLessThanEq($notifications->interval, self::MAX_INTEGER);
        Assert::nullOrLessThanEq($notifications->firstDelay, self::MAX_INTEGER);
        Assert::nullOrLessThanEq($notifications->recoveryDelay, self::MAX_INTEGER);
    }
}
