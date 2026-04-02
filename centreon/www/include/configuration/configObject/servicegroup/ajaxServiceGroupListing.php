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

$helper  = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB  = $helper->getDb();
$params  = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// ACL filtering
$conditionStr = '';
$sgStrParams  = [];

if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $sgs = $acl->getServiceGroupAclConf(null, 'broker');

    if (empty($sgs)) {
        $helper->jsonResponse([], 0, 0, $limit);
    }

    $sgIds = array_keys($sgs);
    foreach ($sgIds as $index => $sgId) {
        $sgStrParams[':sg_' . $index] = (int) $sgId;
    }
    $queryParams  = implode(',', array_keys($sgStrParams));
    $conditionStr = $search !== ''
        ? 'AND sg_id IN (' . $queryParams . ')'
        : 'WHERE sg_id IN (' . $queryParams . ')';
}

// Query
if ($search !== '') {
    $statement = $pearDB->prepare(
        'SELECT SQL_CALC_FOUND_ROWS sg_id, sg_name, sg_alias, sg_activate'
        . ' FROM servicegroup WHERE (sg_name LIKE :search OR sg_alias LIKE :search) '
        . $conditionStr . ' ORDER BY sg_name LIMIT :offset, :limit'
    );
    $statement->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
} else {
    $statement = $pearDB->prepare(
        'SELECT SQL_CALC_FOUND_ROWS sg_id, sg_name, sg_alias, sg_activate'
        . ' FROM servicegroup ' . $conditionStr . ' ORDER BY sg_name LIMIT :offset, :limit'
    );
}
foreach ($sgStrParams as $key => $sgId) {
    $statement->bindValue($key, $sgId, PDO::PARAM_INT);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($sg = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'       => (int) $sg['sg_id'],
        'name'     => $sg['sg_name'],
        'alias'    => $sg['sg_alias'],
        'activate' => (int) $sg['sg_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
