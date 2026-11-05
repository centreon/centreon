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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\PollerScopedInterface;
use App\Shared\Domain\Aggregate\VaultScopedInterface;
use App\Shared\Domain\VaultInterface;

/**
 * Deliberately minimal: what the DeleteHost cascade needs (identify the service, know its host, log its
 * deletion, purge its vault entry), what the service listing shows of the service itself (activation,
 * direct template, own icon, own check intervals) and the state of its host as loaded with it (activation,
 * poller), which decides the poller to flag when the service changes. What it inherits from its templates
 * is not part of it. Not the full ~40-field service configuration model — that belongs to a future
 * dedicated Service CRUD migration.
 *
 * @extends AggregateRoot<ServiceId>
 */
final class Service extends AggregateRoot implements AclScopedInterface, PollerScopedInterface, VaultScopedInterface
{
    /**
     * @param bool $hostActivated whether its host is enabled, as loaded with the service
     * @param ?PollerId $hostPollerId the poller of its host, as loaded with the service, null when the host has none
     * @param list<ServiceMacro> $macros the service's own custom macros ($_SERVICE<NAME>$)
     */
    public function __construct(
        ?ServiceId $id,
        public readonly ServiceName $name,
        public readonly HostId $hostId,
        public readonly bool $hostActivated,
        public readonly ?PollerId $hostPollerId,
        public readonly array $macros = [],
        // Mutable (the other fields are readonly) so enable()/disable() can toggle it.
        public bool $activated = true,
        public readonly ?ServiceTemplateId $templateId = null,
        public readonly ?MediaId $iconId = null,
        public readonly ServiceCheckIntervals $checkIntervals = new ServiceCheckIntervals(),
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
        foreach ($this->macros as $macro) {
            if ($macro->isPassword && $vault->isVaultPath($macro->value)) {
                return $vault->extractUuid($macro->value);
            }
        }

        return null;
    }
}
