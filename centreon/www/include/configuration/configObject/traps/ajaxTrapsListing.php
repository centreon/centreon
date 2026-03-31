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

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];
$statusFilter = filter_var($_GET['status'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$vendorFilter = filter_var($_GET['vendor'] ?? null, FILTER_VALIDATE_INT) ?: 0;

$statusEnum = [-1 => 'Pending', 0 => 'OK', 1 => 'Warning', 2 => 'Critical', 3 => 'Unknown'];

// Vendor cache
$vendors = [];
$vResult = $pearDB->query('SELECT id, alias FROM traps_vendor ORDER BY alias');
while ($v = $vResult->fetch(PDO::FETCH_ASSOC)) {
    $vendors[(int) $v['id']] = $v['alias'];
}

// Build conditions
$conditions = 'WHERE 1 ';
$bindParams = [];

if ($search !== '') {
    $conditions .= "AND (t.traps_oid LIKE :search OR t.traps_name LIKE :search "
        . "OR t.manufacturer_id IN (SELECT id FROM traps_vendor WHERE alias LIKE :search)) ";
    $bindParams[':search'] = '%' . $search . '%';
}

if ($statusFilter > 0) {
    $enumStatus = $statusFilter == 5 ? -1 : $statusFilter - 1;
    $conditions .= "AND t.traps_status = :status ";
    $bindParams[':status'] = (string) $enumStatus;
}

if ($vendorFilter > 0) {
    $conditions .= "AND t.manufacturer_id = :vendor ";
    $bindParams[':vendor'] = $vendorFilter;
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS t.traps_id, t.traps_name, t.traps_oid, t.traps_status, t.manufacturer_id, t.traps_args'
    . ' FROM traps t ' . $conditions
    . ' ORDER BY t.manufacturer_id, t.traps_name LIMIT :offset, :limit'
);
foreach ($bindParams as $key => $val) {
    $pType = ($key === ':vendor') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $statement->bindValue($key, $val, $pType);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($t = $statement->fetch(PDO::FETCH_ASSOC)) {
    $oid = $t['traps_oid'];
    if (strlen($oid) > 40) {
        $oid = substr($oid, 0, 40) . '...';
    }
    $rows[] = [
        'id'     => (int) $t['traps_id'],
        'name'   => $t['traps_name'],
        'oid'    => $oid,
        'status' => $statusEnum[$t['traps_status']] ?? '',
        'status_code' => (int) $t['traps_status'],
        'vendor' => $vendors[(int) $t['manufacturer_id']] ?? '',
        'output' => $t['traps_args'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
