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

$helper  = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB  = $helper->getDb();

// Input validation
$sgId   = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $sgId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

// CSRF validation (consumes token, returns a fresh one)
$newToken = $helper->validateCsrfToken();

// ACL: require write access
$helper->requireWriteAccess(60203);

// ACL: write access on service groups page (60801)
$acl = $helper->getAcl();
if (! $acl || $acl->page(60801) !== 1) {
    AjaxListingHelper::jsonError('Write access denied', 403);
}

// ACL: non-admin must have access to this specific service group
if (! $helper->isAdmin()) {
    $sgs = $acl->getServiceGroupAclConf(null, 'broker');
    if (! array_key_exists($sgId, $sgs)) {
        AjaxListingHelper::jsonError('Access denied to this service group', 403);
    }
}

// Verify service group exists
$checkStmt = $pearDB->prepare('SELECT sg_id FROM servicegroup WHERE sg_id = :sg_id');
$checkStmt->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
$checkStmt->execute();
if (! $checkStmt->fetch()) {
    AjaxListingHelper::jsonError('Service group not found', 404);
}

// Perform enable/disable
$activate = ($action === 's') ? '1' : '0';
$statement = $pearDB->prepare("UPDATE servicegroup SET sg_activate = :activate WHERE sg_id = :sg_id");
$statement->bindValue(':activate', $activate, PDO::PARAM_STR);
$statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
$statement->execute();

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
