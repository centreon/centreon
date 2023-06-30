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
$versionOfTheUpgrade = 'UPGRADE - 22.10.10: ';
$errorMessage = '';

$alterTableSession = function(CentreonDB $pearDB) use(&$errorMessage) {
    $constraintExistStatement = $pearDB->query(
        <<<SQL
            SELECT 1  from INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME="session" AND CONSTRAINT_NAME="token_id_fk_1"
        SQL
    );
    if ($constraintExistStatement->fetch() !== false) {
        $errorMessage = "Unable to delete entries from session table";
        $pearDB->query("DELETE FROM session");

        $errorMessage = "Unable to delete entries from security_token table";
        $pearDB->query("DELETE FROM security_token");

        $errorMessage = "Unable to delete entries from security_token table";
        $pearDB->query(
            <<<SQL
                ALTER TABLE session
                ADD CONSTRAINT `token_id_fk` FOREIGN KEY (`session_id`)
                    REFERENCES `security_authentication_tokens` (`token`) ON DELETE CASCADE
            SQL
        );
    }
};

try {
    $alterTableSession($pearDB);
} catch (\Exception $e) {
    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}