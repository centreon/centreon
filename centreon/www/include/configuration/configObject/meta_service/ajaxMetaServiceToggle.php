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

$helper->requireWriteAccess(60204);

// Verify exists
$checkStmt = $pearDB->prepare('SELECT meta_id FROM meta_service WHERE meta_id = :id');
$checkStmt->bindValue(':id', $objId, PDO::PARAM_INT);
$checkStmt->execute();
if (! $checkStmt->fetch()) {
    AjaxListingHelper::jsonError('Object not found', 404);
}

// Get name for logging
$nameStmt = $pearDB->prepare('SELECT meta_name FROM meta_service WHERE meta_id = :id');
$nameStmt->bindValue(':id', $objId, PDO::PARAM_INT);
$nameStmt->execute();
$objName = $nameStmt->fetchColumn() ?: '';

// Perform enable/disable
$activate = ($action === 's') ? '1' : '0';
$stmt = $pearDB->prepare("UPDATE meta_service SET meta_activate = :activate WHERE meta_id = :id");
$stmt->bindValue(':activate', $activate, PDO::PARAM_STR);
$stmt->bindValue(':id', $objId, PDO::PARAM_INT);
$stmt->execute();

// Audit log
$helper->logToggleAction('meta_service', $objId, $objName, $action === 's' ? 'enable' : 'disable');

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
