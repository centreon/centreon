#!@PHP_BIN@
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

define('_DELAY_', '600'); // Default 10 minutes

require_once realpath(__DIR__ . '/../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonDowntime.Broker.class.php';

$unix_time = time();

$ext_cmd_add['host'] = ['[%u] SCHEDULE_HOST_DOWNTIME;%s;%u;%u;%u;0;%u;Downtime cycle;[Downtime cycle #%u]', '[%u] SCHEDULE_HOST_SVC_DOWNTIME;%s;%u;%u;%u;0;%u;Downtime cycle;[Downtime cycle #%u]'];

$ext_cmd_del['host'] = ['[%u] DEL_HOST_DOWNTIME;%u'];

$ext_cmd_add['svc'] = ['[%u] SCHEDULE_SVC_DOWNTIME;%s;%s;%u;%u;%u;0;%u;Downtime cycle;[Downtime cycle #%u]'];

$ext_cmd_del['svc'] = ['[%u] DEL_SVC_DOWNTIME;%u'];

// Connector to centreon DB
$pearDB = new CentreonDB();
$downtimeObj = new CentreonDowntimeBroker($pearDB, _CENTREON_VARLIB_);

// Get approaching downtimes
$downtimes = $downtimeObj->getApproachingDowntimes(_DELAY_);

foreach ($downtimes as $downtime) {
    $isScheduled = $downtimeObj->isScheduled($downtime);

    if (! $isScheduled && $downtime['dt_activate'] == '1') {
        $downtimeObj->insertCache($downtime);
        if ($downtime['service_id'] != '') {
            foreach ($ext_cmd_add['svc'] as $cmd) {
                $cmd = sprintf(
                    $cmd,
                    $unix_time,
                    $downtime['host_name'],
                    $downtime['service_description'],
                    $downtime['start_timestamp'],
                    $downtime['end_timestamp'],
                    $downtime['fixed'],
                    $downtime['duration'],
                    $downtime['dt_id']
                );
                $downtimeObj->setCommand($downtime['host_id'], $cmd);
            }
        } else {
            foreach ($ext_cmd_add['host'] as $cmd) {
                $cmd = sprintf(
                    $cmd,
                    $unix_time,
                    $downtime['host_name'],
                    $downtime['start_timestamp'],
                    $downtime['end_timestamp'],
                    $downtime['fixed'],
                    $downtime['duration'],
                    $downtime['dt_id']
                );
                $downtimeObj->setCommand($downtime['host_id'], $cmd);
            }
        }
    } elseif ($isScheduled && $downtime['dt_activate'] == '0') {
        if ($downtime['service_id'] != '') {
            foreach ($ext_cmd_del['svc'] as $cmd) {
                $cmd = sprintf(
                    $cmd,
                    $unix_time,
                    $downtime['dt_id']
                );
                $downtimeObj->setCommand($downtime['host_id'], $cmd);
            }
        } else {
            foreach ($ext_cmd_del['host'] as $cmd) {
                $cmd = sprintf(
                    $cmd,
                    $unix_time,
                    $downtime['dt_id']
                );
                $downtimeObj->setCommand($downtime['host_id'], $cmd);
            }
        }
    }
}

// Send the external commands
$downtimeObj->sendCommands();

// Purge downtime cache
$downtimeObj->purgeCache();
