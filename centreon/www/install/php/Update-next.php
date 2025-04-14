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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';
$errorMessage = '';

$updateSamlProviderConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to retrieve SAML provider configuration';
    $samlConfiguration = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT * FROM `provider_configuration`
            WHERE `type` = 'saml'
            SQL
    );

    if (! $samlConfiguration || ! isset($samlConfiguration['custom_configuration'])) {
        throw new \Exception('SAML configuration is missing');
    }

    $customConfiguration = json_decode($samlConfiguration['custom_configuration'], true, JSON_THROW_ON_ERROR);

    if (!isset($customConfiguration['requested_authn_context'])) {
        $customConfiguration['requested_authn_context'] = 'minimum';
        $query = <<<'SQL'
                UPDATE `provider_configuration`
                SET `custom_configuration` = :custom_configuration
                WHERE `type` = 'saml'
            SQL;
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::string(
                    'custom_configuration',
                    json_encode($customConfiguration, JSON_THROW_ON_ERROR)
                )
            ]
        );

        $pearDB->update($query, $queryParameters);
    }
};

try {
    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateSamlProviderConfiguration($pearDB);

    $pearDB->commit();
} catch (\Exception $e) {
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (PDOException $e) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation",
            customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
            exception: $e
        );
    }

    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
        exception: $e
    );

    throw new Exception("UPGRADE - {$version}: " . $errorMessage, (int) $e->getCode(), $e);
}
