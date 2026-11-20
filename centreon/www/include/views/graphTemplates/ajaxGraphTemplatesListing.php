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
    $cond = ' WHERE name LIKE :search';
    $bind[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS graph_id, name, vertical_label, base, split_component '
    . 'FROM giv_graphs_template' . $cond . ' ORDER BY name LIMIT :offset, :limit'
);
foreach ($bind as $key => $value) {
    $statement->bindValue($key, $value, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($graph = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id' => (int) $graph['graph_id'],
        'name' => $graph['name'],
        'desc' => $graph['vertical_label'],
        'base' => $graph['base'],
        'split' => ((int) $graph['split_component']) ? _('Yes') : _('No'),
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
