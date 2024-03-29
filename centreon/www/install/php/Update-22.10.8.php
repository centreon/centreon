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
$versionOfTheUpgrade = 'UPGRADE - 22.10.8: ';
$errorMessage = '';

$updateOpenIdCustomConfiguration = function (CentreonDB $pearDB): void
{
    $customConfigurationJson = $pearDB->query(
        <<<'SQL'
        SELECT custom_configuration
            FROM provider_configuration
        WHERE
            name = 'openid'
        SQL
    )->fetchColumn();

    $customConfiguration = json_decode($customConfigurationJson, true);
    if (! array_key_exists('redirect_url', $customConfiguration)) {
        $customConfiguration['redirect_url'] = null;
        $updatedCustomConfigurationEncoded = json_encode($customConfiguration);

        $statement = $pearDB->prepare(
            <<<SQL
            UPDATE provider_configuration
                SET custom_configuration = :encodedConfiguration
            WHERE name = 'openid'
            SQL
        );
        $statement->bindValue(':encodedConfiguration', $updatedCustomConfigurationEncoded, \PDO::PARAM_STR);
        $statement->execute();
    }
};

try {
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $errorMessage = 'Unable to update provider_configuration table to add redirect_url';
    $updateOpenIdCustomConfiguration($pearDB);

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
