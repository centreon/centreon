<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\Monitoring\MonitoringResource\UseCase\V2110;

use Centreon\Domain\Monitoring\MonitoringResource\Model\MonitoringResource;

/**
 * This class is a DTO for the FindMonitoringResources use case.
 *
 * @package Centreon\Domain\Monitoring\MonitoringResource\UseCase\V2110
 */
class FindMonitoringResourcesResponse
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private $monitoringResources = [];

    /**
     * @param MonitoringResource[] $monitoringResources
     */
    public function setMonitoringResources(array $monitoringResources): void
    {
        foreach ($monitoringResources as $monitoringResource) {
            $this->monitoringResources[] = [
                'uuid' => $monitoringResource->getUuid(),
                'short_type' => $monitoringResource->getShortType(),
                'id' => $monitoringResource->getId(),
                'name' => $monitoringResource->getName(),
                'type' => $monitoringResource->getType(),
                'alias' => $monitoringResource->getAlias(),
                'fqdn' => $monitoringResource->getFqdn(),
                'acknowledged' => $monitoringResource->getAcknowledged(),
                'active_checks' => $monitoringResource->getActiveChecks(),
                'flapping' => $monitoringResource->getFlapping(),
                'icon' => $monitoringResource->getIcon(),
                'in_downtime' => $monitoringResource->getInDowntime(),
                'information' => $monitoringResource->getInformation(),
                'last_check' => $monitoringResource->getLastCheck(),
                'last_status_change' => $monitoringResource->getLastStatusChange(),
                'monitoring_server_name' => $monitoringResource->getMonitoringServerName(),
                'notification_enabled' => $monitoringResource->isNotificationEnabled(),
                'parent' => $monitoringResource->getParent(),
                'passive_checks' => $monitoringResource->getPassiveChecks(),
                'performance_data' => $monitoringResource->getPerformanceData(),
                'severity_level' => $monitoringResource->getSeverityLevel(),
                'status' => $monitoringResource->getStatus(),
                'tries' => $monitoringResource->getTries(),
                'duration' => $monitoringResource->getDuration(),
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMonitoringResources(): array
    {
        return $this->monitoringResources;
    }
}
