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
        $inList = implode(',', $pollerIds);
        $resIds = [];
        $stmt = $pearDB->query("SELECT DISTINCT resource_id FROM cfg_resource_instance_relations WHERE instance_id IN ({$inList})");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resIds[] = (int) $row['resource_id'];
        }
        if (! empty($resIds)) {
            $aclCond = 'AND resource_id IN (' . implode(',', $resIds) . ') ';
        } else {
            $helper->jsonResponse([], 0, 0, $limit);
        }
    }
}

// Query
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'AND resource_name LIKE :search ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS resource_id, resource_name, resource_line, resource_comment, resource_activate, is_password'
    . ' FROM cfg_resource WHERE 1=1 ' . $searchCond . $aclCond
    . ' ORDER BY resource_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

// Linked pollers per resource
$pollerStmt = $pearDB->prepare(
    'SELECT instance_id FROM cfg_resource_instance_relations WHERE resource_id = :rid'
);

$rows = [];
while ($res = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rid = (int) $res['resource_id'];

    // Get linked poller names
    $pollerStmt->execute([':rid' => $rid]);
    $pollerNames = [];
    while ($rel = $pollerStmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int) $rel['instance_id'];
        if (isset($servers[$sid])) {
            $pollerNames[] = $servers[$sid];
        }
    }

    // Mask password values
    $value = $res['is_password'] ? '**********' : $res['resource_line'];

    $comment = $res['resource_comment'] ?? '';
    if (mb_strlen($comment) > 40) {
        $comment = mb_substr($comment, 0, 40) . '...';
    }

    $rows[] = [
        'id'       => $rid,
        'name'     => $res['resource_name'],
        'value'    => $value,
        'pollers'  => implode(', ', $pollerNames),
        'comment'  => $comment,
        'activate' => (int) $res['resource_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
