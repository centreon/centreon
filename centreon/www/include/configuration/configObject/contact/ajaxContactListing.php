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

$search       = $params['search'];
$num          = $params['num'];
$limit        = $params['limit'];
$contactGroup = filter_var($_GET['contactGroup'] ?? null, FILTER_VALIDATE_INT) ?: 0;

// Build timeperiod cache
$tpCache = ['' => ''];
$tpResult = $pearDB->query('SELECT tp_id, tp_name FROM timeperiod');
while ($tp = $tpResult->fetch(PDO::FETCH_ASSOC)) {
    $tpCache[(string) $tp['tp_id']] = $tp['tp_name'];
}

// Query registered contacts (contact_register = '1')
$searchCond = '';
$searchParams = [];
$joinCond = '';

if ($search !== '') {
    $searchCond = "AND (c.contact_name LIKE :search OR c.contact_alias LIKE :search) ";
    $searchParams[':search'] = '%' . $search . '%';
}

$cgCond = '';
if ($contactGroup > 0) {
    $cgCond = ' AND c.contact_id IN (SELECT contact_contact_id FROM contactgroup_contact_relation WHERE contactgroup_cg_id = :cg_id) ';
}

// ACL: non-admin users only see the contacts covered by their access groups
$aclCond = '';
$aclParams = [];
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $contactAcl = $acl->getContactAclConf(['fields' => ['contact_id'], 'keys' => ['contact_id']]);
    if ($contactAcl === []) {
        $helper->jsonResponse([], 0, 0, $limit);
    }
    foreach (array_keys($contactAcl) as $index => $contactId) {
        $aclParams[':acl_c' . $index] = (int) $contactId;
    }
    $aclCond = ' AND c.contact_id IN (' . implode(',', array_keys($aclParams)) . ') ';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS c.contact_id, c.contact_name, c.contact_alias, c.contact_email,'
    . ' c.timeperiod_tp_id, c.timeperiod_tp_id2,'
    . ' c.contact_host_notification_options, c.contact_service_notification_options,'
    . ' c.contact_lang, c.contact_oreon, c.contact_admin, c.contact_activate,'
    . ' c.contact_register, c.contact_auth_type, c.contact_ldap_required_sync, c.blocking_time'
    . " FROM contact c WHERE c.contact_register = '1' " . $searchCond . $cgCond . $aclCond
    . ' ORDER BY c.contact_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
if ($contactGroup > 0) {
    $statement->bindValue(':cg_id', $contactGroup, PDO::PARAM_INT);
}
foreach ($aclParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_INT);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$currentUserId = $centreon->user->get_id();
$isAdmin = $centreon->user->admin;

$rows = [];
while ($c = $statement->fetch(PDO::FETCH_ASSOC)) {
    $hostTp = $tpCache[(string) ($c['timeperiod_tp_id'] ?? '')] ?? '';
    $svcTp  = $tpCache[(string) ($c['timeperiod_tp_id2'] ?? '')] ?? '';
    $hostNotifOpts = $c['contact_host_notification_options'] ?? '';
    $svcNotifOpts  = $c['contact_service_notification_options'] ?? '';

    $rows[] = [
        'id'              => (int) $c['contact_id'],
        'name'            => $c['contact_name'],
        'alias'           => $c['contact_alias'],
        'email'           => $c['contact_email'],
        'host_notif'      => $hostTp ? $hostTp . ' (' . $hostNotifOpts . ')' : '',
        'svc_notif'       => $svcTp ? $svcTp . ' (' . $svcNotifOpts . ')' : '',
        'lang'            => $c['contact_lang'],
        'access'          => (int) $c['contact_oreon'],
        'admin'           => (int) $c['contact_admin'],
        'activate'        => (int) $c['contact_activate'],
        'is_current_user' => ((int) $c['contact_id'] === (int) $currentUserId),
        'auth_type'       => $c['contact_auth_type'],
        'ldap_sync'       => $c['contact_ldap_required_sync'],
        'blocked'         => $isAdmin && $c['blocking_time'] !== null,
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
