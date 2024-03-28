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
require_once __DIR__ . '/../../class/centreonLog.class.php';
$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 23.10.5: ';
$errorMessage = '';

// ------------ INSERT / UPDATE / DELETE
$updateTopologyForCloudNotifications = function(CentreonDB $pearDB): void {
    $statement = $pearDB->query(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_url` = '/configuration/notifications'
            SQL
    );

    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology`
                    (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`,
                    `topology_order`, `topology_group`,`topology_feature_flag`)
                VALUES
                    ('Notifications', '/configuration/notifications', '1', '1', 604, 60413, 1, 1,'notification')
                SQL
        );
    }
};

try {
    $errorMessage = '';
    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }
    $errorMessage = 'Unable to update topology for Cloud Notifications';
    $updateTopologyForCloudNotifications($pearDB);

    $pearDB->commit();
} catch (\Exception $ex) {
    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $ex->getCode()
        . ' - Error : ' . $ex->getMessage()
        . ' - Trace : ' . $ex->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $ex->getCode(), $ex);
}
