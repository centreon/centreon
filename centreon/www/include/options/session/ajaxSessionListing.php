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

require_once realpath(__DIR__ . '/../../common/listing/AjaxListingHelper.php');

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// Search filter (user name or IP address)
$conditions = 'WHERE 1=1 ';
$bindParams = [];
if ($search !== '') {
    $searchEsc = str_replace('_', '\\_', $search);
    $conditions .= 'AND (contact_name LIKE :search OR ip_address LIKE :search) ';
    $bindParams[':search'] = '%' . $searchEsc . '%';
}

// Single query: sessions joined to their contact and current topology page
$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS session.*, contact_name, contact_admin, contact_auth_type,'
    . ' contact_ldap_last_sync, topology_name, topology_url_opt'
    . ' FROM session'
    . ' INNER JOIN contact ON contact_id = user_id'
    . ' LEFT JOIN topology ON topology_page = current_page '
    . $conditions
    . ' ORDER BY contact_name, contact_admin LIMIT :offset, :limit'
);
foreach ($bindParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$isAdmin = $helper->isAdmin();

$rows = [];
while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
    // LDAP-linked accounts expose their last synchronization time (admins only)
    $lastSync = '';
    if ($isAdmin && $r['contact_auth_type'] === 'ldap') {
        $lastSync = $r['contact_ldap_last_sync'] > 0 ? (int) $r['contact_ldap_last_sync'] : '-';
    }

    $rows[] = [
        'user_id'       => (int) $r['user_id'],
        'user_alias'    => $r['contact_name'],
        'admin'         => (int) $r['contact_admin'],
        'ip_address'    => $r['ip_address'],
        'last_reload'   => (int) $r['last_reload'],
        'ldapContact'   => $r['contact_auth_type'],
        'current_page'  => $r['current_page'] . ($r['topology_url_opt'] ?? ''),
        'topology_name' => $r['topology_name'] != '' ? _($r['topology_name']) : '',
        'last_sync'     => $lastSync,
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
