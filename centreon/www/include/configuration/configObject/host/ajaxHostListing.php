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
$hostgroup = filter_var($_GET['hostgroup'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$poller    = filter_var($_GET['poller'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$template  = filter_var($_GET['template'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$status    = filter_var($_GET['status'] ?? null, FILTER_VALIDATE_INT) ?: 0;

// Server name cache
$servers = [];
$srvResult = $pearDB->query('SELECT id, name FROM nagios_server ORDER BY name');
while ($row = $srvResult->fetch(PDO::FETCH_ASSOC)) {
    $servers[(int) $row['id']] = $row['name'];
}

// Host-to-poller relation cache
$hostPollers = [];
$relResult = $pearDB->query('SELECT host_host_id, nagios_server_id FROM ns_host_relation');
while ($row = $relResult->fetch(PDO::FETCH_ASSOC)) {
    $hostPollers[(int) $row['host_host_id']] = $servers[(int) $row['nagios_server_id']] ?? '';
}

// Icon cache
$iconCache = [];
$iconResult = $pearDB->query(
    "SELECT ehi.host_host_id, CONCAT(vid.dir_alias, '/', vi.img_path) AS icon_path"
    . " FROM extended_host_information ehi"
    . " INNER JOIN view_img vi ON ehi.ehi_icon_image = vi.img_id"
    . " INNER JOIN view_img_dir_relation vidr ON vi.img_id = vidr.img_img_id"
    . " INNER JOIN view_img_dir vid ON vidr.dir_dir_parent_id = vid.dir_id"
    . " WHERE ehi.ehi_icon_image IS NOT NULL"
);
while ($row = $iconResult->fetch(PDO::FETCH_ASSOC)) {
    $iconCache[(int) $row['host_host_id']] = './img/media/' . $row['icon_path'];
}

// Icon inheritance
function resolveHostIcon(int $hostId, array &$iconCache, CentreonDB $db): ?string
{
    if (isset($iconCache[$hostId])) {
        return $iconCache[$hostId];
    }
    $stmt = $db->prepare("SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = :hid ORDER BY `order` LIMIT 1");
    $stmt->execute([':hid' => $hostId]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($parent) {
        return resolveHostIcon((int) $parent['host_tpl_id'], $iconCache, $db);
    }
    return null;
}

// ACL filtering for non-admin users
$aclJoin = '';
$aclBindParams = [];
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $aclDbName = $acl->getNameDBAcl();
    $aclGroupIds = array_keys($acl->getAccessGroups());
    if (! empty($aclGroupIds)) {
        $aclPlaceholders = [];
        foreach ($aclGroupIds as $index => $groupId) {
            $key = ':acl_gid' . $index;
            $aclPlaceholders[] = $key;
            $aclBindParams[$key] = (int) $groupId;
        }
        $aclIn = implode(',', $aclPlaceholders);
        $aclJoin = " INNER JOIN `{$aclDbName}`.centreon_acl acl ON acl.host_id = h.host_id AND acl.service_id IS NULL AND acl.group_id IN ({$aclIn}) ";
    } else {
        // No ACL access at all
        $helper->jsonResponse([], 0, 0, $limit);
    }
}

// Build query
$joins = $aclJoin;
$conditions = "WHERE h.host_register = '1' ";
$bindParams = $aclBindParams;

// Search filter
if ($search !== '') {
    $searchEsc = str_replace('_', '\\_', $search);
    $conditions .= "AND (h.host_name LIKE :search OR h.host_alias LIKE :search OR h.host_address LIKE :search) ";
    $bindParams[':search'] = '%' . $searchEsc . '%';
}

// Status filter
if ($status === 2) {
    $conditions .= "AND h.host_activate = '1' ";
} elseif ($status === 1) {
    $conditions .= "AND h.host_activate = '0' ";
}

// Hostgroup filter
if ($hostgroup > 0) {
    $joins .= " INNER JOIN hostgroup_relation hr ON hr.host_host_id = h.host_id ";
    $conditions .= "AND hr.hostgroup_hg_id = :hg_id ";
    $bindParams[':hg_id'] = $hostgroup;
}

// Poller filter
if ($poller > 0) {
    $joins .= " INNER JOIN ns_host_relation nshr ON nshr.host_host_id = h.host_id ";
    $conditions .= "AND nshr.nagios_server_id = :poller_id ";
    $bindParams[':poller_id'] = $poller;
}

// Template filter
if ($template > 0) {
    $joins .= " INNER JOIN host_template_relation htr_filter ON htr_filter.host_host_id = h.host_id ";
    $conditions .= "AND htr_filter.host_tpl_id = :tpl_id ";
    $bindParams[':tpl_id'] = $template;
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS DISTINCT h.host_id, h.host_name, h.host_alias, h.host_address, h.host_activate'
    . ' FROM host h ' . $joins . $conditions
    . ' ORDER BY h.host_name LIMIT :offset, :limit'
);
foreach ($bindParams as $key => $val) {
    $pType = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $statement->bindValue($key, $val, $pType);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

// Template query
$tplStmt = $pearDB->prepare(
    "SELECT h.host_id, h.host_name FROM host_template_relation htr"
    . " INNER JOIN host h ON htr.host_tpl_id = h.host_id"
    . " WHERE htr.host_host_id = :hid ORDER BY htr.`order`"
);

// First pass: collect hosts from query
$hostResults = [];
while ($host = $statement->fetch(PDO::FETCH_ASSOC)) {
    $hostResults[] = $host;
}

// Real-time monitoring status from centstorage (only for listed hosts, by ID)
$pearDBO = new CentreonDB('centstorage');
$rtmStatus = [];
if (! empty($hostResults)) {
    $hostIds = array_map(function ($h) { return (int) $h['host_id']; }, $hostResults);
    $inList = implode(',', $hostIds);
    $rtmResult = $pearDBO->query(
        "SELECT host_id, name, state, acknowledged, scheduled_downtime_depth, last_check, output, last_state_change"
        . " FROM hosts WHERE host_id IN ({$inList})"
    );
    while ($row = $rtmResult->fetch(PDO::FETCH_ASSOC)) {
        $rtmStatus[(int) $row['host_id']] = [
            'state'      => (int) $row['state'],
            'ack'        => (int) $row['acknowledged'],
            'dt'         => (int) $row['scheduled_downtime_depth'],
            'last_check' => $row['last_check'] ? (int) $row['last_check'] : null,
            'output'     => $row['output'],
            'since'      => $row['last_state_change'] ? (int) $row['last_state_change'] : null,
        ];
    }
}

$rows = [];
foreach ($hostResults as $host) {
    $hid = (int) $host['host_id'];

    // Templates
    $templates = [];
    $tplStmt->execute([':hid' => $hid]);
    while ($tpl = $tplStmt->fetch(PDO::FETCH_ASSOC)) {
        $templates[] = ['id' => (int) $tpl['host_id'], 'name' => $tpl['host_name']];
    }

    // Monitoring status (0=UP, 1=DOWN, 2=UNREACHABLE, 4=PENDING)
    $monStatus = $rtmStatus[$hid] ?? null;

    $rows[] = [
        'id'        => $hid,
        'name'      => $host['host_name'],
        'alias'     => $host['host_alias'],
        'address'   => $host['host_address'],
        'poller'    => $hostPollers[$hid] ?? '',
        'templates' => $templates,
        'activate'  => (int) $host['host_activate'],
        'icon'      => resolveHostIcon($hid, $iconCache, $pearDB),
        'mon_state'  => $monStatus ? $monStatus['state'] : null,
        'mon_ack'    => $monStatus ? $monStatus['ack'] : 0,
        'mon_dt'     => $monStatus ? $monStatus['dt'] : 0,
        'mon_last'   => $monStatus ? $monStatus['last_check'] : null,
        'mon_output' => $monStatus ? $monStatus['output'] : null,
        'mon_since'  => $monStatus ? $monStatus['since'] : null,
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
