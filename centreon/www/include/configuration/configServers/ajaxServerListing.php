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

require_once realpath(__DIR__ . '/../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// RTM database
$pearDBO = new CentreonDB('centstorage');

// Get RTM info (instances)
$nagiosInfo = [];
$dbResult = $pearDBO->query(
    'SELECT start_time AS program_start_time, running AS is_currently_running, pid AS process_id, '
    . 'instance_id, name AS instance_name, last_alive FROM instances WHERE deleted = 0'
);
while ($info = $dbResult->fetch(PDO::FETCH_ASSOC)) {
    $nagiosInfo[$info['instance_id']] = $info;
}

// Get engine version
$dbResult = $pearDBO->query(
    'SELECT DISTINCT instance_id, version AS program_version, engine AS program_name '
    . 'FROM instances WHERE deleted = 0'
);
while ($info = $dbResult->fetch(PDO::FETCH_ASSOC)) {
    if (isset($nagiosInfo[$info['instance_id']])) {
        $nagiosInfo[$info['instance_id']]['version'] = $info['program_name'] . ' ' . $info['program_version'];
    }
}

// Remote server IPs
$remotesServerIPs = $pearDB->query('SELECT ip FROM remote_servers')->fetchAll(PDO::FETCH_COLUMN);

// Last restart times
$restartTimes = [];
$dbResult = $pearDB->query('SELECT id, last_restart FROM nagios_server');
while ($row = $dbResult->fetch(PDO::FETCH_ASSOC)) {
    $restartTimes[(int) $row['id']] = $row['last_restart'];
}

// Main query
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = "AND name LIKE :search ";
    $searchParams[':search'] = '%' . $search . '%';
}

// ACL
$acl = $helper->getAcl();
$pollerIds = [];
if ($acl) {
    $serverResult = $acl->getPollerAclConf(['fields' => ['id', 'name'], 'order' => ['name']]);
    foreach ($serverResult as $srv) {
        $pollerIds[] = (int) $srv['id'];
    }
}
$aclCond = '';
if (! empty($pollerIds)) {
    $aclCond = 'WHERE id IN (' . implode(',', $pollerIds) . ') ';
} else {
    $aclCond = 'WHERE 1=1 ';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS id, name, ns_activate, ns_ip_address, localhost, is_default, '
    . 'updated, gorgone_communication_type'
    . ' FROM nagios_server ' . $aclCond . $searchCond
    . ' ORDER BY name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($srv = $statement->fetch(PDO::FETCH_ASSOC)) {
    $id = (int) $srv['id'];
    $isRunning = isset($nagiosInfo[$id]['is_currently_running']) && $nagiosInfo[$id]['is_currently_running'] == 1;

    // Server type
    $serverType = $srv['localhost'] ? 'Central' : 'Poller';
    if (in_array($srv['ns_ip_address'], $remotesServerIPs)) {
        $serverType = 'Remote Server';
    }

    // Conf changed
    $confChanged = 'N/A';
    if ($srv['ns_activate'] && isset($restartTimes[$id])) {
        $confChanged = $srv['updated'] ? 'Yes' : 'No';
    }

    // Uptime
    $uptime = '-';
    if ($isRunning && isset($nagiosInfo[$id]['program_start_time'])) {
        $now = new DateTime();
        $startDate = (new DateTime())->setTimestamp($nagiosInfo[$id]['program_start_time']);
        $interval = date_diff($now, $startDate);
        $days = (int) $interval->format('%a');
        if ($days >= 2) {
            $uptime = $interval->format('%a days');
        } elseif ($days == 1) {
            $uptime = $interval->format('%a day %h hours');
        } elseif ((int) $interval->format('%h') >= 1) {
            $uptime = $interval->format('%hh %imin');
        } else {
            $uptime = $interval->format('%imin %ss');
        }
    }

    // Last update + stale flag
    $lastAlive = $nagiosInfo[$id]['last_alive'] ?? null;
    $lastUpdateStale = $lastAlive && (time() - $lastAlive > 600);

    // Cfg ID for edit config link
    $cfgStmt = $pearDB->prepare(
        "SELECT nagios_id FROM cfg_nagios WHERE nagios_server_id = :id AND nagios_activate = '1'"
    );
    $cfgStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $cfgStmt->execute();
    $cfgRow = $cfgStmt->fetch(PDO::FETCH_ASSOC);
    $cfgId = $cfgRow ? (int) $cfgRow['nagios_id'] : null;

    $rows[] = [
        'id'              => $id,
        'name'            => $srv['name'],
        'ip_address'      => $srv['ns_ip_address'],
        'type'            => $serverType,
        'is_running'      => $isRunning,
        'conf_changed'    => $confChanged,
        'conf_changed_flag' => (int) $srv['updated'],
        'pid'             => $isRunning ? ($nagiosInfo[$id]['process_id'] ?? '-') : '-',
        'uptime'          => $uptime,
        'version'         => $nagiosInfo[$id]['version'] ?? 'N/A',
        'last_alive'      => $lastAlive ? (int) $lastAlive : null,
        'last_alive_stale' => $lastUpdateStale,
        'is_default'      => (int) $srv['is_default'],
        'activate'        => (int) $srv['ns_activate'],
        'cfg_id'          => $cfgId,
        'gorgone_comm'    => (int) $srv['gorgone_communication_type'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
