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

// Input validation
$scId   = filter_var($_POST['sg_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $scId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

// CSRF validation
$newToken = $helper->validateCsrfToken();

// ACL: write access on service categories page (60209)
$acl = $helper->getAcl();
if (! $acl || $acl->page(60209) !== 1) {
    AjaxListingHelper::jsonError('Write access denied', 403);
}

// ACL: non-admin must have access to this specific service category
if (! $helper->isAdmin()) {
    $scString = $acl->getServiceCategoriesString();
    if (strpos($scString, "'" . $scId . "'") === false) {
        AjaxListingHelper::jsonError('Access denied to this service category', 403);
    }
}

// Verify service category exists
$checkStmt = $pearDB->prepare('SELECT sc_id FROM service_categories WHERE sc_id = :sc_id');
$checkStmt->bindValue(':sc_id', $scId, PDO::PARAM_INT);
$checkStmt->execute();
if (! $checkStmt->fetch()) {
    AjaxListingHelper::jsonError('Service category not found', 404);
}

// Perform enable/disable
$activate = ($action === 's') ? '1' : '0';
$statement = $pearDB->prepare("UPDATE service_categories SET sc_activate = :activate WHERE sc_id = :sc_id");
$statement->bindValue(':activate', $activate, PDO::PARAM_STR);
$statement->bindValue(':sc_id', $scId, PDO::PARAM_INT);
$statement->execute();

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
