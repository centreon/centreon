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

namespace App\MonitoringConfiguration\Domain\Event;

use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\Shared\Domain\Event\EventInterface;

/**
 * A service changed by a mass change, as it was saved: its form (without its own macros, which the activity log
 * leaves out), the poller of its host to flag, and the host and poller it left when it moved.
 */
final readonly class ServiceMassChanged implements EventInterface
{
    /**
     * @param ?PollerId $pollerId the poller of its host to flag, null when that host is disabled or has none
     * @param ?PollerId $previousPollerId the poller of the host the service left, when that host was enabled and
     *                                    another poller monitors the new one; null otherwise
     * @param ?HostId $previousHostId the host the service left, null when it stayed
     */
    public function __construct(
        public ServiceId $serviceId,
        public ServiceName $name,
        public HostId $hostId,
        public bool $activated,
        public ?ServiceTemplateId $templateId,
        public ServiceCheckOptions $checkOptions,
        public ServiceSchedulingOptions $schedulingOptions,
        public ?ServiceNotifications $notifications,
        public ServiceDataProcessing $dataProcessing,
        public ?ServiceExtendedInformations $extendedInformations,
        public ?GraphTemplateId $graphTemplateId,
        public ?PollerId $pollerId,
        public int $creatorId,
        public ?PollerId $previousPollerId = null,
        public ?HostId $previousHostId = null,
        public \DateTimeImmutable $firedAt = new \DateTimeImmutable(),
    ) {
    }

    public static function of(Service $service, int $creatorId, ?PollerId $previousPollerId = null, ?HostId $previousHostId = null): self
    {
        return new self(
            serviceId: $service->id(),
            name: $service->name,
            hostId: $service->hostId,
            activated: $service->activated,
            templateId: $service->templateId,
            checkOptions: new ServiceCheckOptions($service->checkOptions->checkCommandId, $service->checkOptions->args),
            schedulingOptions: $service->schedulingOptions,
            notifications: $service->notifications,
            dataProcessing: $service->dataProcessing,
            extendedInformations: $service->extendedInformations,
            graphTemplateId: $service->graphTemplateId,
            pollerId: $service->pollerToFlag(),
            creatorId: $creatorId,
            previousPollerId: $previousPollerId,
            previousHostId: $previousHostId,
        );
    }

    public function firedAt(): \DateTimeImmutable
    {
        return $this->firedAt;
    }
}
