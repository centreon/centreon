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

// error specific content
$versionOfTheUpgrade = 'UPGRADE - 22.04.10: ';
$errorMessage = '';

try {

    // Transactional queries
    $pearDB->beginTransaction();

    // check if entry ldap_connection_timeout exist
    $query = $pearDB->query("SELECT * FROM auth_ressource_info WHERE ari_name = 'ldap_connection_timeout'");
    $ldapResult = $query->fetchAll(PDO::FETCH_ASSOC);
    // insert entry ldap_connection_timeout  with default value
    if (! $ldapResult) {
        $errorMessage = "Unable to add default ldap connection timeout";
        $pearDB->query(
            "INSERT INTO auth_ressource_info (ar_id, ari_name, ari_value)
                        (SELECT ar_id, 'ldap_connection_timeout', '' FROM auth_ressource)"
        );
    }


    $pearDB->commit();
} catch (\Exception $e) {
    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}

