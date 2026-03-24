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

// Server name cache
$servers = [];
$dbResult = $pearDB->query('SELECT id, name FROM nagios_server ORDER BY name');
while ($row = $dbResult->fetch(PDO::FETCH_ASSOC)) {
    $servers[(int) $row['id']] = $row['name'];
}

// ACL filtering
$aclCond = '';
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $pollerResult = $acl->getPollerAclConf(['fields' => ['id', 'name'], 'order' => ['name']]);
    $pollerIds = [];
    foreach ($pollerResult as $p) {
        $pollerIds[] = (int) $p['id'];
    }
    if (! empty($pollerIds)) {
        $aclCond = 'AND ns_nagios_server IN (' . implode(',', $pollerIds) . ') ';
    } else {
        $helper->jsonResponse([], 0, 0, $limit);
    }
}

// Query
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'AND config_name LIKE :search ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS config_id, config_name, ns_nagios_server, config_activate'
    . ' FROM cfg_centreonbroker WHERE 1=1 ' . $searchCond . $aclCond
    . ' ORDER BY config_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

// Count inputs/outputs per config
$countStmt = $pearDB->prepare(
    "SELECT COUNT(DISTINCT config_group_id) AS cnt FROM cfg_centreonbroker_info WHERE config_id = :id AND config_group = :grp"
);

$rows = [];
while ($cfg = $statement->fetch(PDO::FETCH_ASSOC)) {
    $configId = (int) $cfg['config_id'];

    $countStmt->execute([':id' => $configId, ':grp' => 'input']);
    $inputs = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    $countStmt->execute([':id' => $configId, ':grp' => 'output']);
    $outputs = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    $rows[] = [
        'id'        => $configId,
        'name'      => $cfg['config_name'],
        'requester' => $servers[(int) $cfg['ns_nagios_server']] ?? '',
        'inputs'    => $inputs,
        'outputs'   => $outputs,
        'activate'  => (int) $cfg['config_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
