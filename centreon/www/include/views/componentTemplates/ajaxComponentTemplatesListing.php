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
    $cond = ' WHERE gct.name LIKE :search';
    $bind[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS gct.compo_id, gct.name, gct.ds_name, gct.ds_legend, gct.ds_stack, '
    . 'gct.ds_order, gct.ds_transparency, gct.ds_tickness, gct.ds_filled, gct.ds_color_line, '
    . 'gct.ds_color_area, h.host_name '
    . 'FROM giv_components_template gct LEFT JOIN host h ON h.host_id = gct.host_id'
    . $cond . ' ORDER BY h.host_name, gct.name LIMIT :offset, :limit'
);
foreach ($bind as $key => $value) {
    $statement->bindValue($key, $value, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($c = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id' => (int) $c['compo_id'],
        'name' => $c['name'],
        'host' => $c['host_name'] ?: _('Global'),
        'ds_name' => $c['ds_name'],
        'legend' => $c['ds_legend'],
        'stacked' => ((int) $c['ds_stack']) ? _('Yes') : _('No'),
        'order' => $c['ds_order'],
        'transparency' => $c['ds_transparency'],
        'thickness' => $c['ds_tickness'],
        'filling' => ((int) $c['ds_filled']) ? _('Yes') : _('No'),
        'colorLine' => $c['ds_color_line'],
        'colorArea' => $c['ds_color_area'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
