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
$versionOfTheUpgrade = 'UPGRADE - 24.04.12: ';
$errorMessage = '';

/**
 * Create indexes for resources and tags tables.
 *
 * @param CentreonDB $realtimeDb the realtime database
 *
 * @throws CentreonDbException
 */
$createIndexesForResourceStatus = function (CentreonDB $realtimeDb): void {
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
    $createIndexesForResourceStatus($pearDBO);
} catch (\Throwable $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "{$versionOfTheUpgrade} error while rolling back the upgrade operation",
        customContext: ['error_message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
        exception: $ex
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
