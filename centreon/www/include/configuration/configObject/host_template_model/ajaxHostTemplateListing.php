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

$search        = $params['search'];
$num           = $params['num'];
$limit         = $params['limit'];
$displayLocked = filter_var($_GET['displayLocked'] ?? 'off', FILTER_VALIDATE_BOOLEAN);

// Locked filter
$lockedFilter = $displayLocked ? '' : 'AND host_locked = 0 ';

// Search
$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = "AND (host_name LIKE :search OR host_alias LIKE :search) ";
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS host_id, host_name, host_alias, host_template_model_htm_id'
    . " FROM host WHERE host_register = '0' " . $lockedFilter . $searchCond
    . ' ORDER BY host_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

// Prepare service count query
$svcStmt = $pearDB->prepare(
    "SELECT COUNT(*) AS cnt FROM host_service_relation WHERE host_host_id = :hid"
);

// Prepare parent templates query (via host_template_relation)
$tplStmt = $pearDB->prepare(
    "SELECT h.host_id, h.host_name FROM host_template_relation htr"
    . " INNER JOIN host h ON htr.host_tpl_id = h.host_id"
    . " WHERE htr.host_host_id = :hid ORDER BY htr.`order`"
);

// Locked elements
$lockedElements = [];
$lockResult = $pearDB->query("SELECT host_id FROM host WHERE host_locked = 1 AND host_register = '0'");
while ($row = $lockResult->fetch(PDO::FETCH_ASSOC)) {
    $lockedElements[(int) $row['host_id']] = true;
}

// Icon cache: extended_host_information → view_img (direct icons)
$directIcons = [];
$iconResult = $pearDB->query(
    "SELECT ehi.host_host_id, CONCAT(vid.dir_alias, '/', vi.img_path) AS icon_path"
    . " FROM extended_host_information ehi"
    . " INNER JOIN view_img vi ON ehi.ehi_icon_image = vi.img_id"
    . " INNER JOIN view_img_dir_relation vidr ON vi.img_id = vidr.img_img_id"
    . " INNER JOIN view_img_dir vid ON vidr.dir_dir_parent_id = vid.dir_id"
    . " WHERE ehi.ehi_icon_image IS NOT NULL"
);
while ($row = $iconResult->fetch(PDO::FETCH_ASSOC)) {
    $directIcons[(int) $row['host_host_id']] = './img/media/' . $row['icon_path'];
}

// Resolve icon with inheritance (walk up template chain)
function resolveIcon(int $hostId, array &$directIcons, CentreonDB $pearDB): ?string
{
    if (isset($directIcons[$hostId])) {
        return $directIcons[$hostId];
    }
    // Check parent templates
    $stmt = $pearDB->prepare("SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = :hid ORDER BY `order` LIMIT 1");
    $stmt->execute([':hid' => $hostId]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($parent) {
        return resolveIcon((int) $parent['host_tpl_id'], $directIcons, $pearDB);
    }
    return null;
}

$rows = [];
while ($host = $statement->fetch(PDO::FETCH_ASSOC)) {
    $hid = (int) $host['host_id'];

    // Service count
    $svcStmt->execute([':hid' => $hid]);
    $svcCount = (int) $svcStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Parent templates (first level only for simplicity)
    $templates = [];
    $tplStmt->execute([':hid' => $hid]);
    while ($tpl = $tplStmt->fetch(PDO::FETCH_ASSOC)) {
        $templates[] = ['id' => (int) $tpl['host_id'], 'name' => $tpl['host_name']];
    }

    $rows[] = [
        'id'        => $hid,
        'name'      => $host['host_name'] ?: '',
        'alias'     => $host['host_alias'],
        'svc_count' => $svcCount,
        'templates' => $templates,
        'locked'    => isset($lockedElements[$hid]),
        'icon'      => resolveIcon($hid, $directIcons, $pearDB),
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
