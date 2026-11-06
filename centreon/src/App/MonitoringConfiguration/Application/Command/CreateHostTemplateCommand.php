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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Logging\Attribute\Sensitive;

final readonly class CreateHostTemplateCommand
{
    /**
     * @param ActingUser $actor the action-log actor, whose ACL scope bounds the category and severity checks
     * @param Collection<HostTemplateId> $templateIds in inheritance order
     * @param Collection<HostCategoryId> $categoryIds
     * @param Collection<ServiceTemplateId> $linkedServiceTemplateIds in relation order
     * @param list<string> $checkCommandArgs
     * @param Collection<HostMacroChange> $macroChanges the submitted macros, in the order to store
     *                                                  them; resolved against what the template inherits
     */
    public function __construct(
        public HostTemplateName $name,
        public HostAlias $alias,
        public ActingUser $actor,
        public ?SnmpVersionEnum $snmpVersion = null,
        // Plaintext until the handler vaults it.
        #[Sensitive]
        public ?string $snmpCommunity = null,
        public ?TimezoneId $timezoneId = null,
        public ?HostSeverityId $severityId = null,
        public Collection $templateIds = new Collection([], HostTemplateId::class),
        public Collection $categoryIds = new Collection([], HostCategoryId::class),
        public Collection $linkedServiceTemplateIds = new Collection([], ServiceTemplateId::class),
        public ?CommandId $checkCommandId = null,
        #[Sensitive]
        public array $checkCommandArgs = [],
        // A change may carry a password macro's plaintext.
        #[Sensitive]
        public Collection $macroChanges = new Collection([], HostMacroChange::class),
        public ?ExtendedInformations $extendedInformations = null,
        public SchedulingOptions $schedulingOptions = new SchedulingOptions(),
        public DataProcessing $dataProcessing = new DataProcessing(),
        public ?Notifications $notifications = null,
    ) {
    }
}
