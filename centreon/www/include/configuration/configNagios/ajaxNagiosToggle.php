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

$nagiosId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action   = $_POST['action'] ?? null;

if (! $nagiosId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$newToken = $helper->validateCsrfToken();

// ACL: require write access
$helper->requireWriteAccess(60903);

// Verify config exists
$checkStmt = $pearDB->prepare('SELECT nagios_id, nagios_server_id FROM cfg_nagios WHERE nagios_id = :id');
$checkStmt->bindValue(':id', $nagiosId, PDO::PARAM_INT);
$checkStmt->execute();
$cfg = $checkStmt->fetch(PDO::FETCH_ASSOC);
if (! $cfg) {
    AjaxListingHelper::jsonError('Configuration not found', 404);
}

if ($action === 's') {
    // Enable: deactivate all others on the same server first
    $pearDB->prepare("UPDATE cfg_nagios SET nagios_activate = '0' WHERE nagios_server_id = :sid")
        ->execute([':sid' => $cfg['nagios_server_id']]);
    $pearDB->prepare("UPDATE cfg_nagios SET nagios_activate = '1' WHERE nagios_id = :id")
        ->execute([':id' => $nagiosId]);
} else {
    $pearDB->prepare("UPDATE cfg_nagios SET nagios_activate = '0' WHERE nagios_id = :id")
        ->execute([':id' => $nagiosId]);
}

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
