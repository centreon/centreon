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

$isAdmin       = $helper->isAdmin();
$hasAllFolders = $isAdmin || ! empty($centreon->user->access->hasAccessToAllImageFolders);

$bindParams = [];

$body = ' FROM view_img_dir AS `directories`'
    . ' LEFT JOIN view_img_dir_relation AS `vidr` ON vidr.dir_dir_parent_id = directories.dir_id'
    . ' LEFT JOIN view_img AS `images` ON images.img_id = vidr.img_img_id ';

// Restrict to the folders the user is allowed to see (admins / full-access users skip this)
if (! $hasAllFolders) {
    $body .= ' INNER JOIN acl_resources_image_folder_relations armdr ON armdr.dir_id = vidr.dir_dir_parent_id'
        . ' INNER JOIN acl_resources ar ON ar.acl_res_id = armdr.acl_res_id'
        . ' INNER JOIN acl_res_group_relations argr ON argr.acl_res_id = ar.acl_res_id'
        . ' LEFT JOIN acl_group_contacts_relations gcr ON gcr.acl_group_id = argr.acl_group_id'
        . ' LEFT JOIN acl_group_contactgroups_relations gcgr ON gcgr.acl_group_id = argr.acl_group_id'
        . ' LEFT JOIN contactgroup_contact_relation cgcr ON cgcr.contactgroup_cg_id = gcgr.cg_cg_id'
        . ' AND (cgcr.contact_contact_id = :contactId OR gcr.contact_contact_id = :contactId) ';
    $bindParams[':contactId'] = (int) $centreon->user->user_id;
}

// Reserved directories are never listed here
$conditions = " WHERE directories.dir_name NOT IN ('centreon-map', 'dashboards', 'ppm') ";
if ($search !== '') {
    $searchEsc = str_replace('_', '\\_', $search);
    $conditions .= ' AND (images.img_name LIKE :search OR directories.dir_name LIKE :search) ';
    $bindParams[':search'] = '%' . $searchEsc . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS images.*, directories.*'
    . $body
    . $conditions
    . ' GROUP BY images.img_id, directories.dir_id'
    . ' ORDER BY dir_alias, img_name LIMIT :offset, :limit'
);
foreach ($bindParams as $key => $val) {
    $statement->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$result = $statement->fetchAll(PDO::FETCH_ASSOC);
$total  = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
foreach ($result as $r) {
    $hasImg = ! empty($r['img_id']);

    // File size and modification date are read from disk (not stored in DB)
    $size = null;
    $mtime = null;
    if ($hasImg) {
        $fullPath = _CENTREON_PATH_ . '/www/img/media/' . $r['dir_alias'] . '/' . $r['img_path'];
        if (is_file($fullPath)) {
            $size = filesize($fullPath) ?: null;
            $mtime = filemtime($fullPath) ?: null;
        }
    }

    $rows[] = [
        'dir_id'      => (int) $r['dir_id'],
        'dir_name'    => html_entity_decode($r['dir_name'] ?? '', ENT_QUOTES, 'UTF-8'),
        'dir_comment' => html_entity_decode($r['dir_comment'] ?? '', ENT_QUOTES, 'UTF-8'),
        'img_id'      => $hasImg ? (int) $r['img_id'] : null,
        'img_name'    => $hasImg ? html_entity_decode($r['img_name'], ENT_QUOTES, 'UTF-8') : '',
        'img_path'    => $hasImg ? html_entity_decode($r['dir_alias'] . '/' . $r['img_path'], ENT_QUOTES, 'UTF-8') : '',
        'img_comment' => $hasImg ? html_entity_decode($r['img_comment'] ?? '', ENT_QUOTES, 'UTF-8') : '',
        'size'        => $size,
        'mtime'       => $mtime,
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
