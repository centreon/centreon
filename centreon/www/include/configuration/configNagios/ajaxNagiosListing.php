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
        // Get nagios_ids for allowed pollers
        $inList = implode(',', $pollerIds);
        $nagiosIds = [];
        $stmt = $pearDB->query("SELECT nagios_id FROM cfg_nagios WHERE nagios_server_id IN ({$inList})");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $nagiosIds[] = (int) $row['nagios_id'];
        }
        if (! empty($nagiosIds)) {
            $aclCond = 'AND nagios_id IN (' . implode(',', $nagiosIds) . ') ';
        } else {
            $helper->jsonResponse([], 0, 0, $limit);
        }
    }
}

// Query
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'AND nagios_name LIKE :search ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS nagios_id, nagios_name, nagios_comment, nagios_activate, nagios_server_id'
    . ' FROM cfg_nagios WHERE 1=1 ' . $searchCond . $aclCond
    . ' ORDER BY nagios_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($cfg = $statement->fetch(PDO::FETCH_ASSOC)) {
    $desc = $cfg['nagios_comment'] ?? '';
    if (mb_strlen($desc) > 40) {
        $desc = mb_substr($desc, 0, 40) . '...';
    }
    $rows[] = [
        'id'       => (int) $cfg['nagios_id'],
        'name'     => $cfg['nagios_name'],
        'desc'     => $desc,
        'instance' => $servers[(int) $cfg['nagios_server_id']] ?? '',
        'activate' => (int) $cfg['nagios_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
