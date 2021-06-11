<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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

namespace Centreon\Infrastructure\Monitoring\MonitoringResource\API\Model_2110;

use Centreon\Domain\Monitoring\MonitoringResource\UseCase\V2110\FindMonitoringResourcesResponse;

/**
 * This class is designed to create the MonitoringResourceV2110 entity
 *
 * @package Centreon\Infrastructure\Monitoring\MonitoringResource\API\Model_2110
 */
class MonitoringResourceFormatter
{
    /**
     * @param FindMonitoringResourcesResponse $response
     * @return \stdClass[]
     */
    public static function createFromResponse(
        FindMonitoringResourcesResponse $response
    ): array {
        $monitoringResources = [];
        foreach ($response->getMonitoringResources() as $monitoringResource) {
            $newMonitoringResource = self::createEmptyClass();
            $newMonitoringResource->uuid = $monitoringResource['uuid'];
            $newMonitoringResource->short_type = $monitoringResource['short_type'];
            $newMonitoringResource->id = $monitoringResource['id'];
            $newMonitoringResource->name = $monitoringResource['name'];
            $newMonitoringResource->type = $monitoringResource['type'];
            $newMonitoringResource->alias = $monitoringResource['alias'];
            $newMonitoringResource->fqdn = $monitoringResource['fqdn'];
            $newMonitoringResource->acknowledged = $monitoringResource['acknowledged'];
            $newMonitoringResource->active_checks = $monitoringResource['active_checks'];
            $newMonitoringResource->flapping = $monitoringResource['flapping'];
            $newMonitoringResource->icon = $monitoringResource['icon'];
            $newMonitoringResource->in_downtime = $monitoringResource['in_downtime'];
            $newMonitoringResource->information = $monitoringResource['information'];
            $newMonitoringResource->last_check = $monitoringResource['last_check'];
            $newMonitoringResource->last_status_change = $monitoringResource['last_status_change'];
            $newMonitoringResource->monitoring_server_name = $monitoringResource['monitoring_server_name'];
            $newMonitoringResource->notification_enabled = $monitoringResource['notification_enabled'];
            $newMonitoringResource->parent = $monitoringResource['parent'];
            $newMonitoringResource->passive_checks = $monitoringResource['passive_checks'];
            $newMonitoringResource->performance_data = $monitoringResource['performance_data'];
            $newMonitoringResource->severity_level = $monitoringResource['severity_level'];
            $newMonitoringResource->status = $monitoringResource['status'];
            $newMonitoringResource->tries = $monitoringResource['tries'];
            $newMonitoringResource->duration = $monitoringResource['duration'];

            $monitoringResources[] = $newMonitoringResource;
        }
        return $monitoringResources;
    }

    /**
     * @return \stdClass
     */
    private static function createEmptyClass(): \stdClass
    {
        return new class extends \stdClass
        {
            public $uuid;
            public $id;
            public $name;
            public $type;
            public $short_type;
            public $alias;
            public $fqdn;
            public $acknowledged;
            public $active_checks;
            public $duration;
            public $flapping;
            public $icon;
            public $in_downtime;
            public $information;
            public $last_check;
            public $last_status_change;
            public $monitoring_server_name;
            public $notification_enabled;
            public $parent;
            public $passive_checks;
            public $performance_data;
            public $severity_level;
            public $status;
            public $tries;
        };
    }
}
