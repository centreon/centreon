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

// ACL filtering
$aclCond = '';
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $metaStr = $acl->getMetaServiceString();
    if ($metaStr !== "''" && $metaStr !== '') {
        $clause = $search !== '' ? 'AND' : 'WHERE';
        $aclCond = $acl->queryBuilder($clause, 'meta_id', $metaStr);
    } else {
        $helper->jsonResponse([], 0, 0, $limit);
    }
}

// Query
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'WHERE meta_name LIKE :search ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS meta_id, meta_name, calcul_type, warning, critical, meta_activate, meta_select_mode'
    . ' FROM meta_service ' . $searchCond . $aclCond
    . ' ORDER BY meta_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($ms = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'          => (int) $ms['meta_id'],
        'name'        => $ms['meta_name'],
        'calcul_type' => $ms['calcul_type'],
        'warning'     => $ms['warning'],
        'critical'    => $ms['critical'],
        'activate'    => (int) $ms['meta_activate'],
        'select_mode' => (int) $ms['meta_select_mode'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
