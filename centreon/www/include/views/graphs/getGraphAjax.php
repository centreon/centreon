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

// using bootstrap.php to load the paths and the DB configurations
require_once __DIR__ . '/../../../../bootstrap.php';
require_once _CENTREON_PATH_ . '/www/class/centreonACL.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonLog.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';

session_start();
session_write_close();

// Initialize database connection
$pearDB = $dependencyInjector['configuration_db'];
$pearDBO = $dependencyInjector['realtime_db'];

// Load session
$centreon = $_SESSION['centreon'];

// Validate session and get contact
$sid = session_id();
$contactId = check_session($sid, $pearDB);
$isAdmin = isUserAdmin($sid);

$access = new CentreonACL($contactId, $isAdmin);

$lca = $access->getHostsServices($pearDBO);

// Build list of services
$servicesReturn = [];

/**
 * Get the list of graph by host
 *
 * Apply ACL and if the service has a graph
 *
 * @param int $host The host ID
 * @param bool $isAdmin If the contact is admin
 * @param array $lca The ACL of the contact
 */
function getServiceGraphByHost($host, $isAdmin, $lca)
{
    $listGraph = [];
    if (
        $isAdmin
        || (! $isAdmin && isset($lca[$host]))
    ) {
        $services =  getMyHostServices($host);
        foreach ($services as $svcId => $svcName) {
            $svcGraph = getGraphByService($host, $svcId, $svcName, $isAdmin, $lca);
            if ($svcGraph !== false) {
                $listGraph[] = $svcGraph;
            }
        }
    }

    return $listGraph;
}

/**
 * Get the graph of a service
 *
 * Apply ACL and if the service has a graph
 *
 * @param int $host The host ID
 * @param int $svcId The service ID
 * @param string $svcName The service name
 * @param bool $isAdmin If the contact is admin
 * @param array $lca The ACL of the contact
 * @param mixed $title
 */
function getGraphByService($host, $svcId, $title, $isAdmin, $lca)
{
    if (
        service_has_graph($host, $svcId)
        && ($isAdmin || (! $isAdmin && isset($lca[$host][$svcId])))
    ) {
        return ['type' => 'service', 'hostId' => $host, 'serviceId' => $svcId, 'id' => $host . '_' . $svcId, 'title' => $title];
    }

    return false;
}

// By hostgroups
if (isset($_POST['host_group_filter'])) {
    foreach ($_POST['host_group_filter'] as $hgId) {
        $hosts = getMyHostGroupHosts($hgId);
        foreach ($hosts as $host) {
            $servicesReturn = array_merge($servicesReturn, getServiceGraphByHost($host, $isAdmin, $lca));
        }
    }
}
// By hosts
if (isset($_POST['host_selector'])) {
    foreach ($_POST['host_selector'] as $host) {
        $servicesReturn = array_merge($servicesReturn, getServiceGraphByHost($host, $isAdmin, $lca));
    }
}

// By servicegroups
if (isset($_POST['service_group_filter'])) {
    foreach ($_POST['service_group_filter'] as $sgId) {
        $services = getMyServiceGroupServices($sgId);
        foreach ($services as $hostSvcId => $svcName) {
            [$hostId, $svcId] = explode('_', $hostSvcId);
            $servicesReturn[] = getGraphByService($hostId, $svcId, $svcName, $isAdmin, $lca);
        }
    }
}

// By service
if (isset($_POST['service_selector'])) {
    foreach ($_POST['service_selector'] as $selectedService) {
        [$hostId, $svcId] = explode('-', $selectedService['id']);
        $svcGraph = getGraphByService($hostId, $svcId, $selectedService['text'], $isAdmin, $lca);
        if ($svcGraph !== false) {
            $servicesReturn[] = $svcGraph;
        }
    }
}

// By metaservice
// @todo

header('Content-type: application/json');
echo json_encode(array_unique($servicesReturn, SORT_REGULAR));
