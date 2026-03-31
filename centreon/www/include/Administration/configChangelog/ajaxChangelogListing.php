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

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// ACL: require at least read access on changelog page (508)
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    if (! $acl || $acl->page(508) === 0) {
        AjaxListingHelper::jsonError('Access denied', 403);
    }
}

// Extra filters (sanitized)
$searchUser = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['searchUser'] ?? '');
$objectType = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['objectType'] ?? '');

// Connect to centstorage
$pearDBO = new CentreonDB('centstorage');

// Object types list (same as ActionLog model)
$objectTypes = [
    'command', 'timeperiod', 'contact', 'contactgroup',
    'host', 'hostgroup', 'service', 'servicegroup',
    'traps', 'escalation',
    'host dependency', 'hostgroup dependency', 'service dependency', 'servicegroup dependency',
    'poller', 'engine', 'broker', 'resources',
    'meta', 'access group', 'menu access', 'resource access', 'action access',
    'manufacturer', 'hostcategories', 'servicecategories',
];

// Build contact list for author display
$contactList = [];
$dbResult = $pearDB->query('SELECT contact_id, contact_name, contact_alias FROM contact');
while ($row = $dbResult->fetch(PDO::FETCH_ASSOC)) {
    $contactList[$row['contact_id']] = $row['contact_name'] . ' (' . $row['contact_alias'] . ')';
}

// Search user by name => get contact IDs
$contactIds = [];
if ($searchUser !== '') {
    $prepareContact = $pearDB->prepare(
        'SELECT contact_id FROM contact WHERE contact_name LIKE :name OR contact_alias LIKE :alias'
    );
    $prepareContact->bindValue(':name', '%' . $searchUser . '%', PDO::PARAM_STR);
    $prepareContact->bindValue(':alias', '%' . $searchUser . '%', PDO::PARAM_STR);
    $prepareContact->execute();
    while ($c = $prepareContact->fetch(PDO::FETCH_ASSOC)) {
        $contactIds[] = (int) $c['contact_id'];
    }
    if (empty($contactIds)) {
        $contactIds[] = -1; // no match
    }
}

// Build query
$logQuery = 'SELECT SQL_CALC_FOUND_ROWS object_id, object_type, object_name, action_log_date, action_type, log_contact_id, action_log_id FROM log_action';
$conditions = [];
$valuesToBind = [];

if ($search !== '') {
    $conditions[] = 'object_name LIKE :object_name';
    $valuesToBind[':object_name'] = '%' . $search . '%';
}

if (! empty($contactIds)) {
    $conditions[] = 'log_contact_id IN (' . implode(',', $contactIds) . ')';
}

if ($objectType !== '' && in_array($objectType, $objectTypes, true)) {
    $conditions[] = 'object_type = :object_type';
    $valuesToBind[':object_type'] = $objectType;
}

if (! empty($conditions)) {
    $logQuery .= ' WHERE ' . implode(' AND ', $conditions);
}

$logQuery .= ' ORDER BY action_log_date DESC LIMIT :from, :nbrElement';

$prepareSelect = $pearDBO->prepare($logQuery);
foreach ($valuesToBind as $label => $value) {
    $prepareSelect->bindValue($label, $value, PDO::PARAM_STR);
}
$prepareSelect->bindValue(':from', $num * $limit, PDO::PARAM_INT);
$prepareSelect->bindValue(':nbrElement', $limit, PDO::PARAM_INT);
$prepareSelect->execute();

$total = (int) $pearDBO->query('SELECT FOUND_ROWS()')->fetchColumn();

// Action type labels + badge mapping
$tabAction = [
    'a' => 'Added',
    'c' => 'Changed',
    'mc' => 'Mass Change',
    'enable' => 'Enabled',
    'disable' => 'Disabled',
    'd' => 'Deleted',
];
$badgeMap = [
    'Added' => 'ok',
    'Changed' => 'warning',
    'Mass Change' => 'warning',
    'Deleted' => 'critical',
    'Enabled' => 'ok',
    'Disabled' => 'critical',
];

// For service host resolution
require_once _CENTREON_PATH_ . '/www/class/centreonLogAction.class.php';
$logAction = $centreon->CentreonLogAction ?? null;

$rows = [];
while ($res = $prepareSelect->fetch(PDO::FETCH_ASSOC)) {
    if (! $res['object_id']) {
        continue;
    }

    $objectName = $res['object_name'];
    $objectName = str_replace(['#S#', '#BS#'], ['/', '\\'], $objectName);

    $author = isset($contactList[$res['log_contact_id']])
        ? $contactList[$res['log_contact_id']]
        : 'unknown';

    $actionLabel = $tabAction[$res['action_type']] ?? $res['action_type'];
    $badge = $badgeMap[$actionLabel] ?? 'warning';

    // For services, try to prepend host name
    $displayName = $objectName;
    if ($res['object_type'] === 'service' && $logAction !== null) {
        try {
            $tmp = $logAction->getHostId($res['object_id']);
            if ($tmp != -1 && isset($tmp['h'])) {
                $tabHost = explode(',', $tmp['h']);
                if (count($tabHost) === 1) {
                    $hostName = $logAction->getHostName($tmp['h']);
                    if ((int) $hostName !== -1) {
                        $displayName = $hostName . ' / ' . $objectName;
                    }
                } elseif (count($tabHost) > 1) {
                    $hosts = [];
                    foreach ($tabHost as $hid) {
                        $hn = $logAction->getHostName($hid);
                        if ((int) $hn !== -1) {
                            $hosts[] = $hn;
                        }
                    }
                    if (! empty($hosts)) {
                        $displayName = '(' . implode(', ', $hosts) . ') ' . $objectName;
                    }
                }
            } elseif ($tmp != -1 && isset($tmp['hg'])) {
                $tabHg = explode(',', $tmp['hg']);
                if (count($tabHg) === 1) {
                    $hgName = $logAction->getHostGroupName($tmp['hg']);
                    $displayName = $hgName . ' / ' . $objectName;
                }
            }
        } catch (\Throwable $e) {
            // silently ignore
        }
    }

    $rows[] = [
        'action_log_id' => (int) $res['action_log_id'],
        'object_id'     => (int) $res['object_id'],
        'object_type'   => $res['object_type'],
        'object_name'   => $displayName,
        'date'          => (int) $res['action_log_date'],
        'action_type'   => $actionLabel,
        'badge'         => $badge,
        'author'        => $author,
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
