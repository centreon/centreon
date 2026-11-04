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

$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'AND (acl_group_name LIKE :search OR acl_group_alias LIKE :search) ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS ag.acl_group_id, ag.acl_group_name, ag.acl_group_alias, ag.acl_group_activate,'
    . ' (SELECT COUNT(*) FROM acl_group_contacts_relations agcr WHERE agcr.acl_group_id = ag.acl_group_id) AS contact_count,'
    . ' (SELECT COUNT(*) FROM acl_group_contactgroups_relations agcgr WHERE agcgr.acl_group_id = ag.acl_group_id) AS cg_count'
    . ' FROM acl_groups ag WHERE ag.cloud_specific = 0 ' . $searchCond
    . ' ORDER BY ag.acl_group_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($grp = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'            => (int) $grp['acl_group_id'],
        'name'          => $grp['acl_group_name'],
        'alias'         => $grp['acl_group_alias'],
        'contact_count' => (int) $grp['contact_count'],
        'cg_count'      => (int) $grp['cg_count'],
        'activate'      => (int) $grp['acl_group_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
