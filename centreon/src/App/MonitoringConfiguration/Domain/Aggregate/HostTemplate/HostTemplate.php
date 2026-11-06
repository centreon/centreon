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
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Aggregate\AggregateRoot;
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
     * The template once the write is applied. Only the values the change provides are checked against
     * the write bounds: a stored value out of them that the change leaves alone stays.
     *
     * @throws HostTemplateLockedException
     * @throws \InvalidArgumentException when a provided value is out of the write bounds
     */
    public function withChange(HostTemplateChange $change): self
    {
        $this->assertUnlocked();

        if ($change->extendedInformations instanceof HostTemplateExtendedInformationsChange) {
            $informations = $change->extendedInformations;
            foreach ([
                [$informations->noteUrl, ExtendedInformations::MAX_NOTE_URL_LENGTH],
                [$informations->note, ExtendedInformations::MAX_NOTE_LENGTH],
                [$informations->actionUrl, ExtendedInformations::MAX_ACTION_URL_LENGTH],
                [$informations->altIcon, ExtendedInformations::MAX_ALT_ICON_LENGTH],
                [$informations->comment, ExtendedInformations::MAX_COMMENT_LENGTH],
            ] as [$text, $maxLength]) {
                if (is_string($text)) {
                    Assert::lengthBetween(trim($text), 1, $maxLength);
                }
            }
        }
        if ($change->schedulingOptions instanceof HostTemplateSchedulingOptionsChange) {
            foreach ([$change->schedulingOptions->maxCheckAttempts, $change->schedulingOptions->normalCheckInterval, $change->schedulingOptions->retryCheckInterval] as $value) {
                $this->assertProvidedRange($value, 1, self::MAX_INTEGER);
            }
        }
        if ($change->dataProcessing instanceof HostTemplateDataProcessingChange) {
            $this->assertProvidedRange($change->dataProcessing->acknowledgmentTimeout, 1, self::MAX_INTEGER);
            $this->assertProvidedRange($change->dataProcessing->freshnessThreshold, 0, self::MAX_INTEGER);
            $this->assertProvidedRange($change->dataProcessing->lowFlapThreshold, 0, 100);
            $this->assertProvidedRange($change->dataProcessing->highFlapThreshold, 0, 100);
        }
        if ($change->notifications instanceof HostTemplateNotificationsChange) {
            foreach ([$change->notifications->interval, $change->notifications->firstDelay, $change->notifications->recoveryDelay] as $value) {
                $this->assertProvidedRange($value, 0, self::MAX_INTEGER);
            }
        }

        return new self(
            id: $this->id(),
            name: NoValue::resolve($change->name, $this->name),
            checkOptions: $change->checkOptions instanceof HostTemplateCheckOptionsChange
                ? $change->checkOptions->applyTo($this->checkOptions)
                : $this->checkOptions,
            serviceTemplateCheckCommandIds: $this->serviceTemplateCheckCommandIds,
            alias: NoValue::resolve($change->alias, $this->alias),
            isLocked: $this->isLocked,
            parentTemplateIds: $this->parentTemplateIds,
            snmpCommunity: NoValue::resolve($change->snmpCommunity, $this->snmpCommunity),
            snmpVersion: NoValue::resolve($change->snmpVersion, $this->snmpVersion),
            timezoneId: NoValue::resolve($change->timezoneId, $this->timezoneId),
            severityId: NoValue::resolve($change->severityId, $this->severityId),
            extendedInformations: $change->extendedInformations instanceof HostTemplateExtendedInformationsChange
                ? $change->extendedInformations->applyTo($this->extendedInformations)
                : $this->extendedInformations,
            schedulingOptions: $change->schedulingOptions instanceof HostTemplateSchedulingOptionsChange
                ? $change->schedulingOptions->applyTo($this->schedulingOptions)
                : $this->schedulingOptions,
            dataProcessing: $change->dataProcessing instanceof HostTemplateDataProcessingChange
                ? $change->dataProcessing->applyTo($this->dataProcessing)
                : $this->dataProcessing,
            notifications: $change->notifications instanceof HostTemplateNotificationsChange
                ? $change->notifications->applyTo($this->notifications)
                : $this->notifications,
            categoryIds: $this->categoryIds,
            linkedServiceTemplateIds: $this->linkedServiceTemplateIds,
        );
    }

    /**
     * Every field but the relations and the macros; contacts and contact groups count through the notifications.
     */
    public function hasSameConfigurationAs(self $other): bool
    {
        return $this->name->value === $other->name->value
            && $this->alias?->value === $other->alias?->value
            && $this->snmpVersion === $other->snmpVersion
            && $this->snmpCommunity?->value === $other->snmpCommunity?->value
            && $this->timezoneId?->value === $other->timezoneId?->value
            && $this->severityId?->value === $other->severityId?->value
            && $this->checkOptions->checkCommandId?->value === $other->checkOptions->checkCommandId?->value
            && $this->checkOptions->args === $other->checkOptions->args
            && $this->schedulingOptions->equals($other->schedulingOptions)
            && $this->dataProcessing->equals($other->dataProcessing)
            && ($this->extendedInformations ?? new ExtendedInformations())->equals($other->extendedInformations ?? new ExtendedInformations())
            && ($this->notifications ?? Notifications::default())->equals($other->notifications ?? Notifications::default());
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

    private function assertProvidedRange(NoValue|int|null $value, int $min, int $max): void
    {
        if (is_int($value)) {
            Assert::range($value, $min, $max);
        }
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
