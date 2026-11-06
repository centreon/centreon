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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
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
    public const MAX_NOTE_LENGTH = 512;
    public const MAX_URL_LENGTH = 2048;
    public const MAX_COMMENT_LENGTH = 65535;
    public const MAX_FLAP_THRESHOLD = 100;

    // the int(11) columns
    public const MAX_INTEGER = 2147483647;

    /**
     * @param Collection<CommandId> $serviceTemplateCheckCommandIds the check commands of the service
     *                                                              templates linked to this template,
     *                                                              each followed by those of its own
     *                                                              templates, in priority order
     * @param Collection<HostTemplateId> $parentTemplateIds the templates this template directly
     *                                                      inherits from, in relation order
     * @param ?SnmpCommunity $snmpCommunity plaintext, or the `secret::` reference once vaulted
     * @param ?HostSeverityId $severityId the levelled `hostcategories` row; the levelless ones are $categoryIds
     * @param Collection<HostCategoryId> $categoryIds
     * @param Collection<ServiceTemplateId> $linkedServiceTemplateIds in relation order
     */
    public function __construct(
        ?HostTemplateId $id,
        public readonly HostTemplateName $name,
        public readonly HostTemplateCheckOptions $checkOptions = new HostTemplateCheckOptions(),
        public readonly Collection $serviceTemplateCheckCommandIds = new Collection([], CommandId::class),
        public readonly ?HostAlias $alias = null,
        public readonly bool $isLocked = false,
        public readonly Collection $parentTemplateIds = new Collection([], HostTemplateId::class),
        public readonly ?SnmpCommunity $snmpCommunity = null,
        public readonly ?SnmpVersionEnum $snmpVersion = null,
        public readonly ?TimezoneId $timezoneId = null,
        public readonly ?HostSeverityId $severityId = null,
        public readonly ?HostTemplateExtendedInformations $extendedInformations = null,
        public readonly HostTemplateSchedulingOptions $schedulingOptions = new HostTemplateSchedulingOptions(),
        public readonly HostTemplateDataProcessing $dataProcessing = new HostTemplateDataProcessing(),
        public readonly ?HostTemplateNotifications $notifications = null,
        public readonly Collection $categoryIds = new Collection([], HostCategoryId::class),
        public readonly Collection $linkedServiceTemplateIds = new Collection([], ServiceTemplateId::class),
    ) {
        parent::__construct($id);
    }

    /**
     * A new template, not yet persisted, whose values must all be within the write bounds; a stored
     * template may hold values outside them.
     *
     * @param Collection<HostTemplateId> $parentTemplateIds in inheritance order
     * @param Collection<HostCategoryId> $categoryIds
     * @param Collection<ServiceTemplateId> $linkedServiceTemplateIds in relation order
     *
     * @throws \InvalidArgumentException when a value is out of the write bounds
     */
    public static function create(
        HostTemplateName $name,
        HostAlias $alias,
        HostTemplateCheckOptions $checkOptions = new HostTemplateCheckOptions(),
        Collection $parentTemplateIds = new Collection([], HostTemplateId::class),
        ?SnmpCommunity $snmpCommunity = null,
        ?SnmpVersionEnum $snmpVersion = null,
        ?TimezoneId $timezoneId = null,
        ?HostSeverityId $severityId = null,
        ?HostTemplateExtendedInformations $extendedInformations = null,
        HostTemplateSchedulingOptions $schedulingOptions = new HostTemplateSchedulingOptions(),
        HostTemplateDataProcessing $dataProcessing = new HostTemplateDataProcessing(),
        ?HostTemplateNotifications $notifications = null,
        Collection $categoryIds = new Collection([], HostCategoryId::class),
        Collection $linkedServiceTemplateIds = new Collection([], ServiceTemplateId::class),
    ): self {
        self::assertMacroNamesAreUnique($checkOptions);
        if ($extendedInformations instanceof HostTemplateExtendedInformations) {
            self::assertExtendedInformationsBounds($extendedInformations);
        }
        self::assertSchedulingOptionsBounds($schedulingOptions);
        self::assertDataProcessingBounds($dataProcessing);
        if ($notifications instanceof HostTemplateNotifications) {
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
                [$informations->noteUrl, self::MAX_URL_LENGTH],
                [$informations->note, self::MAX_NOTE_LENGTH],
                [$informations->actionUrl, self::MAX_URL_LENGTH],
                [$informations->altIcon, HostTemplateExtendedInformations::MAX_ALT_ICON_LENGTH],
                [$informations->comment, self::MAX_COMMENT_LENGTH],
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
            $this->assertProvidedRange($change->dataProcessing->lowFlapThreshold, 0, self::MAX_FLAP_THRESHOLD);
            $this->assertProvidedRange($change->dataProcessing->highFlapThreshold, 0, self::MAX_FLAP_THRESHOLD);
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
            && $this->checkOptions->hasSameCommandAs($other->checkOptions)
            && $this->schedulingOptions->equals($other->schedulingOptions)
            && $this->dataProcessing->equals($other->dataProcessing)
            && ($this->extendedInformations ?? new HostTemplateExtendedInformations())->equals($other->extendedInformations ?? new HostTemplateExtendedInformations())
            && ($this->notifications ?? new HostTemplateNotifications())->equals($other->notifications ?? new HostTemplateNotifications());
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
     * @param Collection<HostTemplateMacro> $macros the copy's own macros, without source identity
     *
     * @throws HostTemplateLockedException
     */
    public function duplicate(HostTemplateName $newName, ?SnmpCommunity $snmpCommunity, Collection $macros): self
    {
        $this->assertUnlocked();

        return new self(
            id: null,
            name: $newName,
            checkOptions: new HostTemplateCheckOptions($this->checkOptions->checkCommandId, $this->checkOptions->args, $macros),
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
        return array_values(array_map(
            static fn (HostTemplateMacro $macro): HostMacro => $macro->toHostMacro(HostMacroParentEnum::Template),
            $this->checkOptions->macros->toArray(),
        ));
    }

    private function assertProvidedRange(NoValue|int|null $value, int $min, int $max): void
    {
        if (is_int($value)) {
            Assert::range($value, $min, $max);
        }
    }

    private static function assertMacroNamesAreUnique(HostTemplateCheckOptions $checkOptions): void
    {
        $names = array_map(static fn (HostTemplateMacro $macro): string => $macro->name->value, $checkOptions->macros->toArray());
        Assert::uniqueValues($names, 'A host template cannot have two macros with the same name.');
    }

    private static function assertExtendedInformationsBounds(HostTemplateExtendedInformations $informations): void
    {
        Assert::nullOrLengthBetween($informations->noteUrl, 1, self::MAX_URL_LENGTH);
        Assert::nullOrLengthBetween($informations->note, 1, self::MAX_NOTE_LENGTH);
        Assert::nullOrLengthBetween($informations->actionUrl, 1, self::MAX_URL_LENGTH);
        Assert::nullOrLengthBetween($informations->altIcon, 1, HostTemplateExtendedInformations::MAX_ALT_ICON_LENGTH);
        Assert::nullOrLengthBetween($informations->comment, 1, self::MAX_COMMENT_LENGTH);
    }

    private static function assertSchedulingOptionsBounds(HostTemplateSchedulingOptions $options): void
    {
        Assert::nullOrRange($options->maxCheckAttempts, 1, self::MAX_INTEGER);
        Assert::nullOrRange($options->normalCheckInterval, 1, self::MAX_INTEGER);
        Assert::nullOrRange($options->retryCheckInterval, 1, self::MAX_INTEGER);
    }

    private static function assertDataProcessingBounds(HostTemplateDataProcessing $dataProcessing): void
    {
        Assert::nullOrRange($dataProcessing->acknowledgmentTimeout, 1, self::MAX_INTEGER);
        Assert::nullOrRange($dataProcessing->freshnessThreshold, 0, self::MAX_INTEGER);
        Assert::nullOrRange($dataProcessing->lowFlapThreshold, 0, self::MAX_FLAP_THRESHOLD);
        Assert::nullOrRange($dataProcessing->highFlapThreshold, 0, self::MAX_FLAP_THRESHOLD);
    }

    private static function assertNotificationsBounds(HostTemplateNotifications $notifications): void
    {
        Assert::nullOrRange($notifications->interval, 0, self::MAX_INTEGER);
        Assert::nullOrRange($notifications->firstDelay, 0, self::MAX_INTEGER);
        Assert::nullOrRange($notifications->recoveryDelay, 0, self::MAX_INTEGER);
        Assert::false(
            in_array(NotificationOptionEnum::None, $notifications->options, true) && count($notifications->options) > 1,
            'The "none" notification option cannot be combined with another option.',
        );
    }
}
