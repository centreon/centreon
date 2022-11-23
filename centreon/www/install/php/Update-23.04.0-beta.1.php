<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

include_once __DIR__ . "/../../class/centreonLog.class.php";
$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 23.04.0-beta.1: ';

/**
 * Query with transaction
 */
try {
    $errorMessage = 'Impossible to change indexes in table "logs"';
    $pearDB->query('DROP INDEX rq1 ON logs');
    $pearDB->query('DROP INDEX rq2 ON logs');
    $pearDB->query('DROP INDEX host_name ON logs');
    $pearDB->query('DROP INDEX status ON logs');
    $pearDB->query('DROP INDEX instance_name ON logs');
    $pearDB->query('CREATE INDEX logs_multi ON logs(host_id,service_id,ctime,msg_type,status,host_name)');
    $pearDB->query('CREATE INDEX logs_msg_type_status ON logs(msg_type, status)');
} catch (\Exception $e) {
    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage .
        " - Code : " . (int)$e->getCode() .
        " - Error : " . $e->getMessage() .
        " - Trace : " . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int)$e->getCode(), $e);
}
