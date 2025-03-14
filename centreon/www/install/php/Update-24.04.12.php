<?php
/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

$versionOfTheUpgrade = 'UPGRADE - 24.04.12: ';
$errorMessage = '';

// -------------------------------------------- Downtimes -------------------------------------------- //
/**
 * Create index for resources table.
 *
 * @param CentreonDB $realtimeDb
 *
 * @throws CentreonDbException
 */
$createIndexForDowntimes = function (CentreonDB $realtimeDb) use (&$errorMessage): void {
    if (! $realtimeDb->isIndexExists('downtimes', 'downtimes_end_time_index')) {
        $errorMessage = 'Unable to create index for downtimes table';
        $realtimeDb->executeQuery('CREATE INDEX `downtimes_end_time_index` ON downtimes (`end_time`)');
    }
};

// -------------------------------------------- Resource Status -------------------------------------------- //
$createIndexesForResourceStatus = function (CentreonDB $realtimeDb) use (&$errorMessage): void {
    if (! $realtimeDb->isIndexExists('resources', 'resources_poller_id_index')) {
        $errorMessage = 'Unable to create index resources_poller_id_index';
        $realtimeDb->exec('CREATE INDEX `resources_poller_id_index` ON resources (`poller_id`)');
    }

    if (! $realtimeDb->isIndexExists('resources', 'resources_id_index')) {
        $errorMessage = 'Unable to create index resources_id_index';
        $realtimeDb->exec('CREATE INDEX `resources_id_index` ON resources (`id`)');
    }

    if (! $realtimeDb->isIndexExists('resources', 'resources_parent_id_index')) {
        $errorMessage = 'Unable to create index resources_parent_id_index';
        $realtimeDb->exec('CREATE INDEX `resources_parent_id_index` ON resources (`parent_id`)');
    }

    if (! $realtimeDb->isIndexExists('resources', 'resources_enabled_type_index')) {
        $errorMessage = 'Unable to create index resources_enabled_type_index';
        $realtimeDb->exec('CREATE INDEX `resources_enabled_type_index` ON resources (`enabled`, `type`)');
    }

    if (! $realtimeDb->isIndexExists('tags', 'tags_type_name_index')) {
        $errorMessage = 'Unable to create index tags_type_name_index';
        $realtimeDb->exec('CREATE INDEX `tags_type_name_index` ON tags (`type`, `name`(10))');
    }
};

try {
    $createIndexForDowntimes($pearDBO);
    $createIndexesForResourceStatus($pearDBO);
} catch (CentreonDbException $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage
            . ' - Code : ' . (int) $e->getCode()
            . ' - Error : ' . $e->getMessage(),
        customContext: [
            'exception' => $e->getOptions(),
            'trace' => $e->getTraceAsString(),
        ],
        exception: $e
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
