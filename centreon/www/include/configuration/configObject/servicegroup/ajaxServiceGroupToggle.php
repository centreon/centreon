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

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';

header('Content-Type: application/json');

session_start();

$pearDB = new CentreonDB();

try {
    if (! CentreonSession::checkSession(session_id(), $pearDB)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
    exit;
}

$sgId = filter_var($_POST['sg_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;
$token = $_POST['centreon_token'] ?? null;

if (! $sgId || ! in_array($action, ['s', 'u'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// CSRF token validation
if ($token === null || ! in_array($token, $_SESSION['x-centreon-token'] ?? [], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Purge used token
$key = array_search($token, $_SESSION['x-centreon-token'], true);
unset($_SESSION['x-centreon-token'][$key], $_SESSION['x-centreon-token-generated-at'][$token]);

// Perform enable/disable
$activate = ($action === 's') ? '1' : '0';
$statement = $pearDB->prepare("UPDATE servicegroup SET sg_activate = :activate WHERE sg_id = :sg_id");
$statement->bindValue(':activate', $activate, PDO::PARAM_STR);
$statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
$statement->execute();

// Generate a new CSRF token for the next call
$newToken = createCSRFToken();

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
