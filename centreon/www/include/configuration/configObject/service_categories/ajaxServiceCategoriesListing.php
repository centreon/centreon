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
    $scString = $acl->getServiceCategoriesString();
    if ($scString !== "''" && $scString !== '') {
        $clause = $search !== '' ? ' AND ' : ' WHERE ';
        $aclCond = $acl->queryBuilder($clause, 'sc_id', $scString);
    } elseif (! $helper->isAdmin()) {
        $helper->jsonResponse([], 0, 0, $limit);
    }
}

// Query
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'WHERE (sc_name LIKE :search OR sc_description LIKE :search) ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate, sc.level,'
    . ' (SELECT COUNT(*) FROM service_categories_relation scr WHERE scr.sc_id = sc.sc_id) AS svc_count'
    . ' FROM service_categories sc ' . $searchCond . $aclCond
    . ' ORDER BY sc.sc_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($sc = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'          => (int) $sc['sc_id'],
        'name'        => $sc['sc_name'],
        'description' => $sc['sc_description'],
        'activate'    => (int) $sc['sc_activate'],
        'svc_count'   => (int) $sc['svc_count'],
        'level'       => $sc['level'] ? (int) $sc['level'] : null,
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
