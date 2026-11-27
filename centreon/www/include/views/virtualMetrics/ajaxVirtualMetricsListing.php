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

$helper = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search = $params['search'];
$num = $params['num'];
$limit = $params['limit'];

$cond = '';
$bind = [];
if ($search !== '') {
    $cond = ' WHERE vmetric_name LIKE :search';
    $bind[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS vmetric_id, vmetric_name, unit_name, rpn_function, def_type, hidden, '
    . 'vmetric_activate, index_id FROM virtual_metrics' . $cond
    . ' ORDER BY vmetric_name LIMIT :offset, :limit'
);
foreach ($bind as $key => $value) {
    $statement->bindValue($key, $value, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$vmetrics = $statement->fetchAll(PDO::FETCH_ASSOC);

// Resolve the "host > service" label from the storage index_data table (batch)
$indexNames = [];
$indexIds = array_filter(array_map(static fn($v) => (int) $v['index_id'], $vmetrics));
if ($indexIds !== []) {
    $pearDBO = new CentreonDB('centstorage');
    $idList = implode(',', array_unique($indexIds));
    $idxResult = $pearDBO->query(
        'SELECT id, host_name, service_description FROM index_data WHERE id IN (' . $idList . ')'
    );
    while ($idx = $idxResult->fetch(PDO::FETCH_ASSOC)) {
        $indexNames[(int) $idx['id']] = trim(($idx['host_name'] ?? '') . ' > ' . ($idx['service_description'] ?? ''), ' >');
    }
}

$defType = [0 => 'CDEF', 1 => 'VDEF'];

$rows = [];
foreach ($vmetrics as $vm) {
    $rows[] = [
        'id' => (int) $vm['vmetric_id'],
        'name' => $vm['vmetric_name'],
        'resource' => $indexNames[(int) $vm['index_id']] ?? '',
        'unit' => $vm['unit_name'],
        'rpn' => $vm['rpn_function'],
        'deftype' => $defType[(int) $vm['def_type']] ?? '',
        'hidden' => ((int) $vm['hidden']) ? _('Yes') : _('No'),
        'activate' => (int) $vm['vmetric_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
