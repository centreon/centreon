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

/**
 * This file contains changes to be included in the next version.
 * The actual version number should be added in the variable $version.
 */
$version = '';
$errorMessage = '';

$addCMATypeToTokenTable = function () use ($pearDB, &$errorMessage) {
    $errorMessage = 'Failed to add column cma type to table security_authentication_tokens';
    $query = "ALTER TABLE security_authentication_tokens MODIFY COLUMN token_type enum('auto','manual','cma') DEFAULT 'auto'";
    $pearDB->query($query);
};

try {
    // DDL statements for real time database

    // DDL statements for configuration database
    $addCMATypeToTokenTable();

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $pearDB->commit();

} catch (\Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        customContext: [
            'exception' => [
                'error_message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]
        ],
        exception: $exception
    );
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (\PDOException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            customContext: [
                'error_to_rollback' => $errorMessage,
                'exception' => [
                    'error_message' => $rollbackException->getMessage(),
                    'trace' => $rollbackException->getTraceAsString()
                ]
            ],
            exception: $rollbackException
        );
        throw new \Exception(
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            (int) $rollbackException->getCode(),
            $rollbackException
        );
    }
    throw new \Exception("UPGRADE - {$version}: " . $errorMessage, (int) $exception->getCode(), $exception);
}
