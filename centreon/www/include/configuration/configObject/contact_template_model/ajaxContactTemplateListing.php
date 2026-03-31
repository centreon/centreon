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

// Build timeperiod cache
$tpCache = [];
$tpResult = $pearDB->query('SELECT tp_id, tp_name FROM timeperiod');
while ($tp = $tpResult->fetch(PDO::FETCH_ASSOC)) {
    $tpCache[(int) $tp['tp_id']] = $tp['tp_name'];
}

// Query contact templates (contact_register = '0')
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'AND contact_name LIKE :search ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS contact_id, contact_name, contact_alias,'
    . ' timeperiod_tp_id, timeperiod_tp_id2, contact_activate'
    . " FROM contact WHERE contact_register = '0' " . $searchCond
    . ' ORDER BY contact_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($ct = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'              => (int) $ct['contact_id'],
        'name'            => $ct['contact_name'],
        'alias'           => $ct['contact_alias'],
        'host_notif_tp'   => $tpCache[(int) $ct['timeperiod_tp_id']] ?? '',
        'svc_notif_tp'    => $tpCache[(int) $ct['timeperiod_tp_id2']] ?? '',
        'activate'        => (int) $ct['contact_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
