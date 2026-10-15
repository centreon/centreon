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
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\VaultScopedInterface;
use App\Shared\Domain\VaultInterface;

/**
 * Deliberately minimal: only what the DeleteHost cascade needs (identify the service, know its
 * host, log its deletion, purge its vault entry). Not the full ~40-field service configuration
 * model — that belongs to a future dedicated Service CRUD migration, not this one.
 *
 * @extends AggregateRoot<ServiceId>
 */
final class Service extends AggregateRoot implements VaultScopedInterface
{
    /**
     * @param list<ServiceMacro> $macros the service's own custom macros ($_SERVICE<NAME>$)
     */
    public function __construct(
        ?ServiceId $id,
        public readonly ServiceName $name,
        public readonly HostId $hostId,
        public readonly array $macros = [],
    ) {
        parent::__construct($id);
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
