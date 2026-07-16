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

// ACL: use getContactGroupAclConf for non-admin
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $cgAcl = $acl->getContactGroupAclConf(
        ['fields' => ['cg_id', 'cg_name', 'cg_alias', 'cg_activate'], 'keys' => ['cg_id'], 'order' => ['cg_name']],
        false
    );

    if (empty($cgAcl)) {
        $helper->jsonResponse([], 0, 0, $limit);
    }

    $cgIds = array_keys($cgAcl);
    $cgStrParams = [];
    foreach ($cgIds as $index => $cgId) {
        $cgStrParams[':cg_' . $index] = (int) $cgId;
    }
    $aclIn = implode(',', array_keys($cgStrParams));
    $aclCond = $search !== ''
        ? 'AND cg.cg_id IN (' . $aclIn . ')'
        : 'WHERE cg.cg_id IN (' . $aclIn . ')';
} else {
    $cgStrParams = [];
    $aclCond = '';
}

// Query
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'WHERE (cg.cg_name LIKE :search OR cg.cg_alias LIKE :search) ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS cg.cg_id, cg.cg_name, cg.cg_alias, cg.cg_activate,'
    . ' (SELECT COUNT(*) FROM contactgroup_contact_relation ccr WHERE ccr.contactgroup_cg_id = cg.cg_id) AS contact_count'
    . ' FROM contactgroup cg ' . $searchCond . $aclCond
    . ' ORDER BY cg.cg_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
foreach ($cgStrParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_INT);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($cg = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'            => (int) $cg['cg_id'],
        'name'          => $cg['cg_name'],
        'alias'         => $cg['cg_alias'],
        'activate'      => (int) $cg['cg_activate'],
        'contact_count' => (int) $cg['contact_count'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
