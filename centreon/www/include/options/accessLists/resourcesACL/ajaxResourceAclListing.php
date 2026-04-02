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
    $searchCond = 'AND (acl_res_name LIKE :search OR acl_res_alias LIKE :search) ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS acl_res_id, acl_res_name, acl_res_alias, acl_res_activate,'
    . ' all_hosts, all_hostgroups, all_servicegroups, all_image_folders'
    . ' FROM acl_resources WHERE locked = 0 AND cloud_specific = 0 ' . $searchCond
    . ' ORDER BY acl_res_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($acl = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'             => (int) $acl['acl_res_id'],
        'name'           => $acl['acl_res_name'],
        'alias'          => $acl['acl_res_alias'],
        'all_hosts'      => (int) $acl['all_hosts'],
        'all_hostgroups' => (int) $acl['all_hostgroups'],
        'all_svcgroups'  => (int) $acl['all_servicegroups'],
        'all_imgfolders' => (int) $acl['all_image_folders'],
        'activate'       => (int) $acl['acl_res_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
