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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use App\Shared\Domain\VaultInterface;

/**
 * @extends AggregateRoot<HostTemplateId>
 */
final class HostTemplate extends AggregateRoot
{
    /**
     * @param Collection<CommandId> $serviceTemplateCheckCommandIds the check commands of the service
     *                                                              templates linked to this template,
     *                                                              each followed by those of its own
     *                                                              templates, in priority order
     * @param Collection<HostTemplateId> $parentTemplateIds the templates this template directly
     *                                                      inherits from, in relation order
     * @param ?HostTemplateSnmpCommunity $snmpCommunity plaintext, or the `secret::` reference once vaulted
     * @param ?HostSeverityId $severityId the levelled `hostcategories` row; the levelless ones are $categoryIds
     * @param Collection<HostCategoryId> $categoryIds
     * @param Collection<ServiceTemplateId> $linkedServiceTemplateIds in relation order
     */
    public function __construct(
        ?HostTemplateId $id,
        public readonly HostTemplateName $name,
        public readonly HostTemplateCheckOptions $checkOptions = new HostTemplateCheckOptions(),
        public readonly Collection $serviceTemplateCheckCommandIds = new Collection([], CommandId::class),
        public readonly ?HostTemplateAlias $alias = null,
        public readonly bool $isLocked = false,
        public readonly Collection $parentTemplateIds = new Collection([], HostTemplateId::class),
        public readonly ?HostTemplateSnmpCommunity $snmpCommunity = null,
        public readonly ?HostTemplateSnmpVersionEnum $snmpVersion = null,
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
    public function duplicate(HostTemplateName $newName, ?HostTemplateSnmpCommunity $snmpCommunity, Collection $macros): self
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
        if ($this->snmpCommunity instanceof HostTemplateSnmpCommunity && $vault->isVaultPath($this->snmpCommunity->value)) {
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
}
