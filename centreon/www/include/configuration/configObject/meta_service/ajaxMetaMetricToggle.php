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
$msrId  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $msrId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

// CSRF validation
$newToken = $helper->validateCsrfToken();

// ACL: require write access
$helper->requireWriteAccess(60204);

// Verify metric relation exists
$checkStmt = $pearDB->prepare('SELECT msr_id FROM meta_service_relation WHERE msr_id = :msr_id');
$checkStmt->bindValue(':msr_id', $msrId, PDO::PARAM_INT);
$checkStmt->execute();
if (! $checkStmt->fetch()) {
    AjaxListingHelper::jsonError('Metric relation not found', 404);
}

// Perform enable/disable
$activate = ($action === 's') ? '1' : '0';
$statement = $pearDB->prepare("UPDATE meta_service_relation SET activate = :activate WHERE msr_id = :msr_id");
$statement->bindValue(':activate', $activate, PDO::PARAM_STR);
$statement->bindValue(':msr_id', $msrId, PDO::PARAM_INT);
$statement->execute();

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
