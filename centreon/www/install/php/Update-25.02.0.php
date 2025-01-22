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

declare(strict_types = 1);


// error specific content
$versionOfTheUpgrade = '25.02.0';

/**
 * Create index for resources table.
 *
 * @param CentreonDB $realtimeDb
 *
 * @throws CentreonDbException
 */
$createIndexForDowntimes = function (CentreonDB $realtimeDb): void {
    try {
        $realtimeDb->executeQuery('CREATE INDEX IF NOT EXISTS `downtimes_end_time_index` ON downtimes (`end_time`)');
    } catch (CentreonDbException $e) {
        throw new CentreonDbException(
            "Unable to create index for downtimes: {$e->getMessage()}",
            $e->getOptions(),
            $e
        );
    }
};

try {
    $createIndexForDowntimes($pearDBO);
} catch (Throwable $e) {
    $message = "UPGRADE - {$versionOfTheUpgrade}: {$e->getMessage()}";
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        customContext: ['trace' => $e->getTraceAsString()],
        message: $message,
        exception: $e
    );

    throw new Exception($message, $e->getCode(), $e);
}
