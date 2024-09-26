<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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
require_once __DIR__ . '/../../class/centreonLog.class.php';

$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.10.1: ';
$errorMessage = '';

$updateNagiosMacros =  function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to check for existing macros in nagios_macro table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT COUNT(*) FROM `nagios_macro`
            WHERE `macro_name` IN (
                '$NOTIFICATIONAUTHOR$',
                '$NOTIFICATIONAUTHORNAME$',
                '$NOTIFICATIONAUTHORALIAS$',
                '$NOTIFICATIONCOMMENT$'
            )
        SQL
    );

    $errorMessage = 'Unable to insert new macros into nagios_macro table';
    if (0 === (int) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->executeQuery(
        <<<'SQL'
            INSERT INTO `nagios_macro` (`macro_name`)
            VALUES
                ('$NOTIFICATIONAUTHOR$'),
                ('$NOTIFICATIONAUTHORNAME$'),
                ('$NOTIFICATIONAUTHORALIAS$'),
                ('$NOTIFICATIONCOMMENT$')
            SQL
        );
    }
    
    $errorMessage = 'Unable to delete deprecated macros from nagios_macro table';
    $pearDB->executeQuery(
    <<<'SQL'
        DELETE FROM `nagios_macro`
        WHERE `macro_name` IN (
            '$HOSTACKAUTHOR$',
            '$HOSTACKCOMMENT$',
            '$SERVICEACKAUTHOR$',
            '$SERVICEACKCOMMENT$'
        )
        SQL
    );
};

try {
    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateNagiosMacros($pearDB);

    $pearDB->commit();
} catch (\Exception $e) {

    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->log(
        4,
        strtoupper($centreonLog::LEVEL_ERROR),
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
