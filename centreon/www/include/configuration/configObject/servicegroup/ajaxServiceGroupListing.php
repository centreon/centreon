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
require_once _CENTREON_PATH_ . '/www/class/HtmlAnalyzer.php';

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

// Parameters
$search = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['search'] ?? '');
$num = filter_var($_GET['num'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$limit = filter_var($_GET['limit'] ?? 30, FILTER_VALIDATE_INT) ?: 30;

// ACL - check if user is admin
require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonACL.class.php';

// Register Centreon autoloader for session deserialization
spl_autoload_register(function ($sClass): void {
    $fileName = lcfirst($sClass);
    $fileNameType1 = _CENTREON_PATH_ . '/www/class/' . $fileName . '.class.php';
    $fileNameType2 = _CENTREON_PATH_ . '/www/class/' . $fileName . '.php';
    if (file_exists($fileNameType1)) {
        require_once $fileNameType1;
    } elseif (file_exists($fileNameType2)) {
        require_once $fileNameType2;
    }
});

$centreon = $_SESSION['centreon'] ?? null;
$isAdmin = $centreon ? $centreon->user->admin : false;

$conditionStr = '';
$sgStrParams = [];

if (! $isAdmin && $centreon) {
    $acl = $centreon->user->access;
    $sgs = $acl->getServiceGroupAclConf(null, 'broker');

    if (! empty($sgs)) {
        $sgIds = array_keys($sgs);
        foreach ($sgIds as $index => $sgId) {
            $sgStrParams[':sg_' . $index] = (int) $sgId;
        }
        $queryParams = implode(',', array_keys($sgStrParams));
        $conditionStr = $search !== ''
            ? 'AND sg_id IN (' . $queryParams . ')'
            : 'WHERE sg_id IN (' . $queryParams . ')';
    } else {
        echo json_encode(['rows' => [], 'total' => 0, 'num' => 0, 'limit' => $limit, 'centreon_token' => createCSRFToken()]);
        exit;
    }
}

if ($search !== '') {
    $statement = $pearDB->prepare(
        'SELECT SQL_CALC_FOUND_ROWS sg_id, sg_name, sg_alias, sg_activate'
        . ' FROM servicegroup WHERE (sg_name LIKE :search OR sg_alias LIKE :search) '
        . $conditionStr . ' ORDER BY sg_name LIMIT :offset, :limit'
    );
    $statement->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
} else {
    $statement = $pearDB->prepare(
        'SELECT SQL_CALC_FOUND_ROWS sg_id, sg_name, sg_alias, sg_activate'
        . ' FROM servicegroup ' . $conditionStr . ' ORDER BY sg_name LIMIT :offset, :limit'
    );
}
foreach ($sgStrParams as $key => $sgId) {
    $statement->bindValue($key, $sgId, PDO::PARAM_INT);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($sg = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'sg_id' => (int) $sg['sg_id'],
        'sg_name' => $sg['sg_name'],
        'sg_alias' => $sg['sg_alias'],
        'sg_activate' => (int) $sg['sg_activate'],
    ];
}

// CSRF token for toggle actions
$centreonToken = createCSRFToken();

echo json_encode([
    'rows' => $rows,
    'total' => $total,
    'num' => $num,
    'limit' => $limit,
    'centreon_token' => $centreonToken,
]);

exit;
