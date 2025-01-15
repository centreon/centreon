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

declare(strict_types = 1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonLog.class.php';

global $pearDBO;
$versionOfTheUpgrade = 'UPGRADE - 24.04.10: ';
$errorMessage = '';

/**
 * Log and create an exception.
 *
 * @param string $message the message
 * @param Throwable $e the exception
 *
 * @throws Exception
 */
$logAndCreateException = function (string $message, Throwable $e): void
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
};

/**
 * Create indexes for resources and tags tables.
 *
 * @param CentreonDB $realtimeDb the realtime database
 *
 * @throws CentreonDbException
 */
$createIndexesForResourceStatus = function (CentreonDB $realtimeDb): void {
    try {
        $realtimeDb->updateDatabase('CREATE INDEX `resources_poller_id_index` ON resources (`poller_id`)');
        $realtimeDb->updateDatabase('CREATE INDEX `resources_id_index` ON resources (`id`)');
        $realtimeDb->updateDatabase('CREATE INDEX `resources_parent_id_index` ON resources (`parent_id`)');
        $realtimeDb->updateDatabase('CREATE INDEX `resources_enabled_type_index` ON resources (`enabled`, `type`)');
        $realtimeDb->updateDatabase('CREATE INDEX `tags_type_name_index` ON tags (`type`, `name`(10))');
    } catch (CentreonDbException $e) {
        throw new CentreonDbException(
            "Unable to create indexes for resources and tags tables {$e->getMessage()}",
            $e->getOptions(),
            $e
        );
    }
};

try {
    // DDL queries for realtime database
    $createIndexesForResourceStatus($pearDBO);
} catch (Throwable $e) {
    $logAndCreateException("UPGRADE - {$versionOfTheUpgrade} : {$e->getMessage()}", $e);
}
