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

$objId  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $objId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$newToken = $helper->validateCsrfToken();

// ACL: require write access on the services page (60201 or 60202) AND, at the
// resource level, that the user actually has access to this specific service.
// Checking only the page would let a user toggle any service by id (IDOR).
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    if ($acl->page(60201) !== 1 && $acl->page(60202) !== 1) {
        AjaxListingHelper::jsonError('Write access denied', 403);
    }
    $aclGroupIds = array_keys($acl->getAccessGroups());
    if ($aclGroupIds === []) {
        AjaxListingHelper::jsonError('Access denied', 403);
    }
    $aclDbName = $acl->getNameDBAcl();
    $aclPlaceholders = [];
    $aclParams = [':sid' => $objId];
    foreach ($aclGroupIds as $idx => $gid) {
        $key = ':acl_g' . $idx;
        $aclPlaceholders[] = $key;
        $aclParams[$key] = (int) $gid;
    }
    $aclStmt = $pearDB->prepare(
        "SELECT 1 FROM `{$aclDbName}`.centreon_acl"
        . " WHERE service_id = :sid AND group_id IN (" . implode(',', $aclPlaceholders) . ") LIMIT 1"
    );
    foreach ($aclParams as $key => $value) {
        $aclStmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $aclStmt->execute();
    if (! $aclStmt->fetchColumn()) {
        AjaxListingHelper::jsonError('Access denied', 403);
    }
}

// Verify exists
$checkStmt = $pearDB->prepare("SELECT service_id FROM service WHERE service_id = :id AND service_register = '1'");
$checkStmt->bindValue(':id', $objId, PDO::PARAM_INT);
$checkStmt->execute();
if (! $checkStmt->fetch()) {
    AjaxListingHelper::jsonError('Object not found', 404);
}

// Get name for logging
$nameStmt = $pearDB->prepare('SELECT service_description FROM service WHERE service_id = :id');
$nameStmt->bindValue(':id', $objId, PDO::PARAM_INT);
$nameStmt->execute();
$objName = $nameStmt->fetchColumn() ?: '';

// Perform enable/disable
$activate = ($action === 's') ? '1' : '0';
$stmt = $pearDB->prepare("UPDATE service SET service_activate = :activate WHERE service_id = :id");
$stmt->bindValue(':activate', $activate, PDO::PARAM_STR);
$stmt->bindValue(':id', $objId, PDO::PARAM_INT);
$stmt->execute();

// Audit log
$helper->logToggleAction('service', $objId, $objName, $action === 's' ? 'enable' : 'disable');

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
