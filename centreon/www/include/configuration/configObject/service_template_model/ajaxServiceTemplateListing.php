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

// Make $pearDB available as global for common-Func.php functions
$GLOBALS['pearDB'] = $pearDB;

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// ACL: require at least read access on service templates page (60206)
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    if (! $acl || $acl->page(60206) === 0) {
        AjaxListingHelper::jsonError('Access denied', 403);
    }
}

// Extra filter: display locked elements
$displayLocked = filter_var($_GET['displayLocked'] ?? '0', FILTER_VALIDATE_BOOLEAN);

// Include common functions for template chain, icon inheritance, intervals
require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';
require_once _CENTREON_PATH_ . '/www/class/centreonMedia.class.php';

// Get interval_length from options
$optStmt = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'interval_length'");
$intervalLength = (int) ($optStmt->fetchColumn() ?: 60);

$mediaObj = new CentreonMedia($pearDB);

// Build query
$searchCond = '';
$bindParams = [];
if ($search !== '') {
    $searchCond = 'AND (sv.service_description LIKE :search OR sv.service_alias LIKE :search) ';
    $bindParams[':search'] = '%' . $search . '%';
}

$lockedFilter = $displayLocked ? '' : 'AND sv.service_locked = 0 ';

$statement = $pearDB->prepare(
    "SELECT SQL_CALC_FOUND_ROWS sv.service_id, sv.service_description, sv.service_alias,"
    . " sv.service_template_model_stm_id, sv.service_locked, sv.service_activate,"
    . " sv.service_normal_check_interval, sv.service_retry_check_interval"
    . " FROM service sv"
    . " WHERE sv.service_register = '0'"
    . " {$searchCond}"
    . " {$lockedFilter}"
    . " ORDER BY sv.service_description"
    . " LIMIT :offset, :limit"
);

foreach ($bindParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($svc = $statement->fetch(PDO::FETCH_ASSOC)) {
    $svcId = (int) $svc['service_id'];

    // Name — fallback to template chain if empty
    $name = $svc['service_description'];
    if (! $name) {
        $name = getMyServiceName($svc['service_template_model_stm_id']);
    }
    $name = str_replace(['#S#', '#BS#', '#BR#', '#T#', '#R#'], ['/', '\\', "\n", "\t", "\r"], $name ?: '');

    // Alias
    $alias = str_replace(['#S#', '#BS#', '#BR#', '#T#', '#R#'], ['/', '\\', "\n", "\t", "\r"], $svc['service_alias'] ?: '');

    // Scheduling: normal / retry intervals
    $normalInterval = (int) getMyServiceField($svcId, 'service_normal_check_interval') * $intervalLength;
    $retryInterval  = (int) getMyServiceField($svcId, 'service_retry_check_interval') * $intervalLength;

    if ($normalInterval % 60 === 0) {
        $normalStr = ($normalInterval / 60) . ' min';
    } else {
        $normalStr = $normalInterval . ' sec';
    }
    if ($retryInterval % 60 === 0) {
        $retryStr = ($retryInterval / 60) . ' min';
    } else {
        $retryStr = $retryInterval . ' sec';
    }

    // Template chain
    $tplArr = getMyServiceTemplateModels($svc['service_template_model_stm_id']);
    $tplLinks = [];
    if (is_array($tplArr) && count($tplArr)) {
        foreach ($tplArr as $tplId => $tplName) {
            $tplName = str_replace(['#S#', '#BS#'], ['/', '\\'], $tplName);
            $tplLinks[] = ['id' => (int) $tplId, 'name' => $tplName];
        }
    }

    // Icon (with template inheritance, fallback to default SVG)
    $iconId = getMyServiceExtendedInfoField($svcId, 'esi_icon_image');
    $iconFile = $iconId ? $mediaObj->getFilename($iconId) : null;
    $icon = $iconFile ? './img/media/' . $iconFile : './img/icons/service.svg';

    $rows[] = [
        'id'         => $svcId,
        'name'       => $name,
        'alias'      => $alias,
        'scheduling' => "{$normalStr} / {$retryStr}",
        'templates'  => $tplLinks,
        'icon'       => $icon,
        'locked'     => (int) ($svc['service_locked'] ?? 0),
        'activate'   => $svc['service_activate'] ?? '1',
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
