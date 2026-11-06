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
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
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
        self::assertMacroNamesAreUnique($checkOptions);
        if ($extendedInformations instanceof ExtendedInformations) {
            self::assertExtendedInformationsBounds($extendedInformations);
        }
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

    private static function assertMacroNamesAreUnique(CheckOptions $checkOptions): void
    {
        $names = array_map(static fn (HostMacro $macro): string => $macro->name->value, $checkOptions->macros);
        Assert::uniqueValues($names, 'A host template cannot have two macros with the same name.');
    }

    private static function assertExtendedInformationsBounds(ExtendedInformations $informations): void
    {
        Assert::nullOrLengthBetween($informations->noteUrl, 1, self::MAX_URL_LENGTH);
        Assert::nullOrLengthBetween($informations->note, 1, self::MAX_NOTE_LENGTH);
        Assert::nullOrLengthBetween($informations->actionUrl, 1, self::MAX_URL_LENGTH);
        Assert::nullOrLengthBetween($informations->altIcon, 1, ExtendedInformations::MAX_ALT_ICON_LENGTH);
        Assert::nullOrLengthBetween($informations->comment, 1, self::MAX_COMMENT_LENGTH);
    }

    private static function assertSchedulingOptionsBounds(SchedulingOptions $options): void
    {
        Assert::nullOrRange($options->maxCheckAttempts, 1, self::MAX_INTEGER);
        Assert::nullOrRange($options->normalCheckInterval, 1, self::MAX_INTEGER);
        Assert::nullOrRange($options->retryCheckInterval, 1, self::MAX_INTEGER);
    }

    private static function assertDataProcessingBounds(DataProcessing $dataProcessing): void
    {
        Assert::nullOrRange($dataProcessing->acknowledgmentTimeout, 1, self::MAX_INTEGER);
        Assert::nullOrRange($dataProcessing->freshnessThreshold, 0, self::MAX_INTEGER);
        Assert::nullOrRange($dataProcessing->lowFlapThreshold, 0, self::MAX_FLAP_THRESHOLD);
        Assert::nullOrRange($dataProcessing->highFlapThreshold, 0, self::MAX_FLAP_THRESHOLD);
    }

    private static function assertNotificationsBounds(Notifications $notifications): void
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
