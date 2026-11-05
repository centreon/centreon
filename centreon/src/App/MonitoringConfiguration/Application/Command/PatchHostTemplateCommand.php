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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Domain\NoValue;

final readonly class PatchHostTemplateCommand
{
    /**
     * @param HostTemplateChange $change what the write changes, the SNMP community aside
     * @param NoValue|string|null $snmpCommunity left out keeps it, null clears it, a string replaces it;
     *                                           plaintext until the handler vaults it
     */
    public function __construct(
        public HostTemplateId $id,
        public ActingUser $actor,
        public HostTemplateChange $change,
        #[Sensitive]
        public NoValue|string|null $snmpCommunity = new NoValue(),
    ) {
    }
}
