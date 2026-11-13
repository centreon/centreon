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
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Logging\Attribute\Sensitive;

/**
 * Full replace (PUT) of a single service. Mirrors {@see CreateServiceCommand}'s field set, plus the target $id
 * and an explicit $activated (which POST sets to true).
 */
final readonly class UpdateServiceCommand
{
    /**
     * @param ActingUser $actor the acting user: the action-log actor, whose ACL scope also bounds the target lookup
     *                          and the host, service-group, category, severity and contact checks
     * @param list<string> $checkCommandArgs ordered check-command arguments; they require a check command, set
     *                                       here or provided by the service template chain
     * @param ?ServiceNotifications $notifications null on a platform without notification block (Cloud): the
     *                                             stored settings are kept
     * @param Collection<ServiceGroupId> $serviceGroupIds
     * @param Collection<ServiceCategoryId> $categoryIds
     * @param Collection<TrapId> $trapIds
     * @param Collection<ServiceMacroChange> $macroChanges the submitted own macros, in the order to store them;
     *                                                     resolved by the handler against the ones the service
     *                                                     owns and inherits
     */
    public function __construct(
        public ServiceId $id,
        public ServiceName $name,
        public HostId $hostId,
        public bool $activated,
        public ActingUser $actor,
        public ?ServiceTemplateId $templateId = null,
        public ?CommandId $checkCommandId = null,
        #[Sensitive]
        public array $checkCommandArgs = [],
        public ServiceSchedulingOptions $schedulingOptions = new ServiceSchedulingOptions(),
        public ?ServiceNotifications $notifications = null,
        public ServiceDataProcessing $dataProcessing = new ServiceDataProcessing(),
        public ?ServiceExtendedInformations $extendedInformations = null,
        public ?GraphTemplateId $graphTemplateId = null,
        public ?ServiceSeverityId $severityId = null,
        public Collection $serviceGroupIds = new Collection([], ServiceGroupId::class),
        public Collection $categoryIds = new Collection([], ServiceCategoryId::class),
        public Collection $trapIds = new Collection([], TrapId::class),
        // Masked in logs: a change may carry a password macro's plaintext.
        #[Sensitive]
        public Collection $macroChanges = new Collection([], ServiceMacroChange::class),
    ) {
    }
}
