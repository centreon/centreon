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

$search    = $params['search'];
$num       = $params['num'];
$limit     = $params['limit'];
$searchH   = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['searchH'] ?? '');
$searchS   = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['searchS'] ?? '');
$template  = filter_var($_GET['template'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$status    = filter_var($_GET['status'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$hostStatus = filter_var($_GET['hostStatus'] ?? null, FILTER_VALIDATE_INT) ?: 0;

// Interval length from options table
$ilResult = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'interval_length'");
$intervalLength = (int) ($ilResult->fetchColumn() ?: 60);

// Icon caches (bulk load)
$hostIconCache = [];
$iconResult = $pearDB->query(
    "SELECT ehi.host_host_id, CONCAT(vid.dir_alias, '/', vi.img_path) AS icon_path"
    . " FROM extended_host_information ehi"
    . " INNER JOIN view_img vi ON ehi.ehi_icon_image = vi.img_id"
    . " INNER JOIN view_img_dir_relation vidr ON vi.img_id = vidr.img_img_id"
    . " INNER JOIN view_img_dir vid ON vidr.dir_dir_parent_id = vid.dir_id"
    . " WHERE ehi.ehi_icon_image IS NOT NULL"
);
while ($row = $iconResult->fetch(PDO::FETCH_ASSOC)) {
    $hostIconCache[(int) $row['host_host_id']] = './img/media/' . $row['icon_path'];
}

$svcIconCache = [];
$svcIconResult = $pearDB->query(
    "SELECT esi.service_service_id, CONCAT(vid.dir_alias, '/', vi.img_path) AS icon_path"
    . " FROM extended_service_information esi"
    . " INNER JOIN view_img vi ON esi.esi_icon_image = vi.img_id"
    . " INNER JOIN view_img_dir_relation vidr ON vi.img_id = vidr.img_img_id"
    . " INNER JOIN view_img_dir vid ON vidr.dir_dir_parent_id = vid.dir_id"
    . " WHERE esi.esi_icon_image IS NOT NULL"
);
while ($row = $svcIconResult->fetch(PDO::FETCH_ASSOC)) {
    $svcIconCache[(int) $row['service_service_id']] = './img/media/' . $row['icon_path'];
}

// Build query
$joins = " FROM service sv"
    . " INNER JOIN host_service_relation hsr ON hsr.service_service_id = sv.service_id"
    . " INNER JOIN host ON host.host_id = hsr.host_host_id";
$conditions = " WHERE host.host_register = '1' AND sv.service_register = '1' ";
$bindParams = [];

// ACL
$aclJoin = '';
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $aclDbName = $acl->getNameDBAcl();
    $aclGroupIds = array_keys($acl->getAccessGroups());
    if (! empty($aclGroupIds)) {
        $aclPlaceholders = [];
        foreach ($aclGroupIds as $idx => $gid) {
            $key = ':acl_g' . $idx;
            $aclPlaceholders[] = $key;
            $bindParams[$key] = (int) $gid;
        }
        $aclIn = implode(',', $aclPlaceholders);
        $aclJoin = " INNER JOIN `{$aclDbName}`.centreon_acl acl ON acl.host_id = host.host_id AND acl.service_id = sv.service_id AND acl.group_id IN ({$aclIn}) ";
    } else {
        $helper->jsonResponse([], 0, 0, $limit);
    }
}
$joins .= $aclJoin;

// Host search
if ($searchH !== '') {
    $conditions .= "AND (host.host_name LIKE :searchH OR host.host_alias LIKE :searchH OR host.host_address LIKE :searchH) ";
    $bindParams[':searchH'] = '%' . $searchH . '%';
}

// Service search
if ($searchS !== '') {
    $conditions .= "AND (sv.service_description LIKE :searchS OR sv.service_alias LIKE :searchS) ";
    $bindParams[':searchS'] = '%' . $searchS . '%';
}

// Host status (0 = only enabled hosts, 1 = include disabled)
if (! $hostStatus) {
    $conditions .= "AND host.host_activate = '1' ";
}

// Service status filter
if ($status === 2) {
    $conditions .= "AND sv.service_activate = '1' ";
} elseif ($status === 1) {
    $conditions .= "AND sv.service_activate = '0' ";
}

// Template filter
if ($template > 0) {
    $conditions .= "AND sv.service_template_model_stm_id = :tpl_id ";
    $bindParams[':tpl_id'] = $template;
}

$distinct = $helper->isAdmin() ? '' : 'DISTINCT';

$statement = $pearDB->prepare(
    "SELECT SQL_CALC_FOUND_ROWS {$distinct}"
    . " sv.service_id, sv.service_description, sv.service_activate,"
    . " sv.service_template_model_stm_id,"
    . " sv.service_normal_check_interval, sv.service_retry_check_interval,"
    . " host.host_id, host.host_name"
    . $joins . $conditions
    . " ORDER BY host.host_name, sv.service_description LIMIT :offset, :limit"
);
foreach ($bindParams as $key => $val) {
    $pType = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $statement->bindValue($key, $val, $pType);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

// Collect results
$results = [];
while ($svc = $statement->fetch(PDO::FETCH_ASSOC)) {
    $results[] = $svc;
}

// RTM status (only for services on this page)
$pearDBO = new CentreonDB('centstorage');
$rtmStatus = [];
if (! empty($results)) {
    $svcKeys = [];
    foreach ($results as $r) {
        $svcKeys[] = (int) $r['host_id'] . '_' . (int) $r['service_id'];
    }
    // Build host_id list for RTM query
    $hostIds = array_unique(array_map(function($r) { return (int) $r['host_id']; }, $results));
    if (! empty($hostIds)) {
        $inList = implode(',', $hostIds);
        // Service RTM
        $rtmResult = $pearDBO->query(
            "SELECT s.host_id, s.service_id, s.state, s.output, s.last_check"
            . " FROM services s WHERE s.host_id IN ({$inList}) AND s.enabled = 1"
        );
        while ($row = $rtmResult->fetch(PDO::FETCH_ASSOC)) {
            $rtmStatus[(int) $row['host_id'] . '_' . (int) $row['service_id']] = [
                'state'  => (int) $row['state'],
                'output' => $row['output'],
                'last_check' => $row['last_check'] ? (int) $row['last_check'] : null,
            ];
        }
        // Host RTM
        $hostRtmResult = $pearDBO->query(
            "SELECT host_id, state, output, last_check FROM hosts WHERE host_id IN ({$inList})"
        );
        while ($row = $hostRtmResult->fetch(PDO::FETCH_ASSOC)) {
            $hostRtm[(int) $row['host_id']] = [
                'state'  => (int) $row['state'],
                'output' => $row['output'],
                'last_check' => $row['last_check'] ? (int) $row['last_check'] : null,
            ];
        }
    }
}
$hostRtm = $hostRtm ?? [];

// Template name cache (for hierarchy)
$tplStmt = $pearDB->prepare("SELECT service_id, service_description FROM service WHERE service_id = :id");

function getTemplateChain(int $tplId, CentreonDB $db, array &$cache, int $depth = 0): array {
    if ($depth > 5 || ! $tplId) return [];
    if (isset($cache[$tplId])) return $cache[$tplId];
    $stmt = $db->prepare("SELECT service_description, service_template_model_stm_id, service_normal_check_interval, service_retry_check_interval FROM service WHERE service_id = :id");
    $stmt->execute([':id' => $tplId]);
    $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
    if (! $tpl) return [];
    $chain = [['id' => $tplId, 'name' => $tpl['service_description'], 'normal' => $tpl['service_normal_check_interval'], 'retry' => $tpl['service_retry_check_interval']]];
    if ($tpl['service_template_model_stm_id']) {
        $chain = array_merge($chain, getTemplateChain((int) $tpl['service_template_model_stm_id'], $db, $cache, $depth + 1));
    }
    $cache[$tplId] = $chain;
    return $chain;
}

// Resolve inherited interval by walking template chain
function resolveInterval(?string $direct, array $tplChain, string $field): ?int {
    if ($direct !== null && $direct !== '') return (int) $direct;
    foreach ($tplChain as $tpl) {
        if (isset($tpl[$field]) && $tpl[$field] !== null && $tpl[$field] !== '') {
            return (int) $tpl[$field];
        }
    }
    return null;
}

$tplCache = [];
$rows = [];
foreach ($results as $svc) {
    $sid = (int) $svc['service_id'];
    $hid = (int) $svc['host_id'];

    // Service description
    $desc = $svc['service_description'];
    if ($desc) {
        $desc = str_replace(['#S#', '#BS#'], ['/', '\\'], $desc);
    }

    // Template chain (must be before scheduling for inheritance)
    $templates = [];
    if ($svc['service_template_model_stm_id']) {
        $templates = getTemplateChain((int) $svc['service_template_model_stm_id'], $pearDB, $tplCache);
    }

    // Scheduling (with template inheritance)
    $resolvedNormal = resolveInterval($svc['service_normal_check_interval'], $templates, 'normal');
    $resolvedRetry  = resolveInterval($svc['service_retry_check_interval'], $templates, 'retry');
    $normalInterval = ($resolvedNormal !== null) ? $resolvedNormal * $intervalLength : 0;
    $retryInterval  = ($resolvedRetry !== null) ? $resolvedRetry * $intervalLength : 0;
    $scheduling = '';
    if ($normalInterval > 0) {
        $scheduling = ($normalInterval % 60 === 0 ? ($normalInterval / 60) . ' min' : $normalInterval . ' sec');
        if ($retryInterval > 0) {
            $scheduling .= ' / ' . ($retryInterval % 60 === 0 ? ($retryInterval / 60) . ' min' : $retryInterval . ' sec');
        }
    }

    // RTM
    $rtmKey = $hid . '_' . $sid;
    $mon = $rtmStatus[$rtmKey] ?? null;

    $rows[] = [
        'id'         => $sid,
        'host_id'    => $hid,
        'host_name'  => $svc['host_name'],
        'host_icon'  => $hostIconCache[$hid] ?? null,
        'desc'       => $desc,
        'svc_icon'   => $svcIconCache[$sid] ?? null,
        'scheduling' => $scheduling,
        'templates'  => $templates,
        'activate'   => (int) $svc['service_activate'],
        'mon_state'      => $mon ? $mon['state'] : null,
        'mon_output'     => $mon ? $mon['output'] : null,
        'mon_last'       => $mon ? $mon['last_check'] : null,
        'host_mon_state' => isset($hostRtm[$hid]) ? $hostRtm[$hid]['state'] : null,
        'host_mon_output'=> isset($hostRtm[$hid]) ? $hostRtm[$hid]['output'] : null,
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
