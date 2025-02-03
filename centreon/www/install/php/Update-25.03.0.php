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
require_once __DIR__ . '/../../class/centreonLog.class.php';

$versionOfTheUpgrade = 'UPGRADE - 25.03.0: ';
$errorMessage = '';

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 * @return void
 */
$removeConstraintFromBrokerConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    // prevent side effect on the $removeFieldFromBrokerConfiguration function
    $errorMessage = 'Unable to update table cb_list_values';
    $pearDB->executeQuery(
        <<<SQL
        ALTER TABLE cb_list_values DROP CONSTRAINT `fk_cb_list_values_1`
        SQL
    );
};

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 * @return void
 */
$removeFieldFromBrokerConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to remove data from cb_field';
    $pearDB->executeQuery(
        <<<SQL
        DELETE FROM cb_field WHERE fieldname = 'check_replication'
        SQL
    );

    $errorMessage = 'Unable to remove data from cfg_centreonbroker_info';
    $pearDB->executeQuery(
        <<<SQL
        DELETE FROM cfg_centreonbroker_info WHERE config_key = 'check_replication'
        SQL
    );
};

try {
    $removeConstraintFromBrokerConfiguration($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $removeFieldFromBrokerConfiguration($pearDB);

    $pearDB->commit();
} catch (CentreonDbException $e) {
    CentreonLog::create()->critical(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString(),
        customContext: [
            'exception' => $e->getOptions(),
            'trace' => $e->getTraceAsString(),
        ],
        exception: $e
    );

    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
