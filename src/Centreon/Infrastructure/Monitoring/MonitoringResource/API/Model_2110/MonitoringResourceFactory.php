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

use Centreon\Domain\Monitoring\MonitoringResource\Model\MonitoringResource;

class MonitoringResourceFactory {
    public static function createFromResponse(array $monitoringResource): MonitoringResource
    {
        $monitoringResourceEntity = new MonitoringResource(
            $monitoringResource['id'],
            $monitoringResource['name'],
            $monitoringResource['type']
        );

        $monitoringResourceEntity->setAlias(
            array_key_exists('alias', $monitoringResource) ? $monitoringResource['alias'] : null
        );

        $monitoringResourceEntity->setFqdn(
            array_key_exists('fqdn', $monitoringResource) ? $monitoringResource['fqdn'] : null
        );

        $monitoringResourceEntity->setAcknowledged(
            array_key_exists('acknowledged', $monitoringResource) ? $monitoringResource['acknowledged'] : null
        );

        $monitoringResourceEntity->setActiveChecks(
            array_key_exists('active_checks', $monitoringResource) ? $monitoringResource['active_checks'] : null
        );
/*      public $alias;
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
        public $tries;*/

        return $monitoringResource;
    }
}

?>