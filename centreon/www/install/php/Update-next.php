<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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
require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';
$errorMessage = '';

try {
    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $pearDB->commit();
} catch (\Exception $e) {
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (PDOException $e) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation",
            customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
            exception: $e
        );
    }

    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
        exception: $e
    );

    throw new Exception("UPGRADE - {$version}: " . $errorMessage, (int) $e->getCode(), $e);
}
