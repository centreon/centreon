<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../../../bootstrap.php';

$versionOfTheUpgrade = '25.01.0';

$createIndexesForResourceStatus = function (CentreonDB $pearDB) use (&$errorMessage): void {
    try {
        $pearDB->updateDatabase('create index resources_id_index on resources (id)');
        $pearDB->updateDatabase('create index poller_id_index on resources (poller_id)');
        $pearDB->updateDatabase('create index resources_parent_id_index on resources (parent_id)');
        $pearDB->updateDatabase('create index tags_type_name_index on tags (type, name(10))');
    } catch (CentreonDbException $e) {
        throw new CentreonDbException(
            "Unable to create indexes for resources and tags tables {$e->getMessage()}",
            $e->getOptions(),
            $e
        );
    }
};

try {
    // DDL queries
    $createIndexesForResourceStatus($pearDB);

    // DQL and DML queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    // Add your DQL and DML queries here...

    $pearDB->commit();
} catch (Throwable $e) {
    logAndCreateException("UPGRADE - {$versionOfTheUpgrade} : {$e->getMessage()}", $e);
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (PDOException $e) {
        logAndCreateException(
            "UPGRADE - {$versionOfTheUpgrade} : error while rolling back transaction : {$e->getMessage()}",
            $e
        );
    }
}

/**
 * Log and create an exception.
 *
 * @param string    $message The message.
 * @param Throwable $e       The exception.
 *
 * @throws Exception
 */
function logAndCreateException(string $message, Throwable $e): void
{
    $customContext = ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
    if ($e instanceof CentreonDbException) {
        $customContext = array_merge($e->getOptions(), $customContext);
    } elseif ($e instanceof PDOException) {
        $customContext = array_merge(
            ['pdo_error_code' => $e->getCode(), 'pdo_error_info' => $e->errorInfo],
            $customContext
        );
    }

    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $message,
        customContext: $customContext,
        exception: $e
    );

    throw new Exception($message, $e->getCode(), $e);
}
