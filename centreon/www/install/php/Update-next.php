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
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\LoggerUpgrade;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

$createAclFingerprintTable = function () use ($pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create the centreon_acl_checksum table';
    LoggerUpgrade::create()->info($version, 'Creating the centreon_acl_checksum table');

    // Fingerprint of the centreon_acl rows of each access group: centAcl.php skips the groups whose rows did not change.
    $pearDBO->executeStatement(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `centreon_acl_checksum` (
                `group_id` int(11) NOT NULL,
                `checksum` char(32) NOT NULL,
                PRIMARY KEY (`group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            SQL
    );
};

$createBrokerOutputVictoriaMetrics = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create the Broker output victoria_metrics';
    LoggerUpgrade::create()->info($version, "Creating the Broker output 'victoria_metrics'");

    $moduleId = $pearDB->fetchOne("SELECT `cb_module_id` FROM `cb_module` WHERE `name` = 'VictoriaMetrics'");
    if ($moduleId === false) {
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_module` (`name`, `libname`, `loading_pos`, `is_bundle`, `is_activated`)
                VALUES ('VictoriaMetrics', 'victoria_metrics.so', 22, 0, 1)
                SQL
        );
        $moduleId = $pearDB->fetchOne("SELECT `cb_module_id` FROM `cb_module` WHERE `name` = 'VictoriaMetrics'");
    }

    $typeId = $pearDB->fetchOne("SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'victoria_metrics'");
    if ($typeId === false) {
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_type` (`type_name`, `type_shortname`, `cb_module_id`)
                VALUES ('Storage - VictoriaMetrics', 'victoria_metrics', :module_id)
                SQL,
            QueryParameters::create([QueryParameter::int('module_id', (int) $moduleId)])
        );
        $typeId = $pearDB->fetchOne("SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'victoria_metrics'");
    }
    $typeId = (int) $typeId;

    $typeParameter = QueryParameters::create([QueryParameter::int('type_id', $typeId)]);
    if ($pearDB->fetchOne(
        'SELECT 1 FROM `cb_tag_type_relation` WHERE `cb_tag_id` = 1 AND `cb_type_id` = :type_id',
        $typeParameter
    ) === false) {
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_tag_type_relation` (`cb_tag_id`, `cb_type_id`, `cb_type_uniq`)
                VALUES (1, :type_id, 0)
                SQL,
            $typeParameter
        );
    }

    // Fields specific to the http_tsdb based outputs
    $newFields = [
        'http_target' => [
            'HTTP target',
            'HTTP path of the write endpoint (default: /write). For a VictoriaMetrics cluster, use /insert/<accountID>/influx/write',
        ],
        'certificate_path' => [
            "Trusted CA's certificate path",
            'Full path to the CA certificate (PEM) used to verify the server certificate when TLS encryption is enabled',
        ],
        'account_id' => [
            'Account ID',
            'VictoriaMetrics tenant account ID, sent in the account_id HTTP header',
        ],
    ];
    foreach ($newFields as $fieldName => [$displayName, $description]) {
        $exists = $pearDB->fetchOne(
            'SELECT 1 FROM `cb_field` WHERE `fieldname` = :fieldname AND `cb_fieldgroup_id` IS NULL',
            QueryParameters::create([QueryParameter::string('fieldname', $fieldName)])
        );
        if ($exists === false) {
            $pearDB->insert(
                <<<'SQL'
                    INSERT INTO `cb_field` (`fieldname`, `displayname`, `description`, `fieldtype`, `external`, `cb_fieldgroup_id`)
                    VALUES (:fieldname, :displayname, :description, 'text', NULL, NULL)
                    SQL,
                QueryParameters::create([
                    QueryParameter::string('fieldname', $fieldName),
                    QueryParameter::string('displayname', $displayName),
                    QueryParameter::string('description', $description),
                ])
            );
        }
    }

    // Resolve field ids by name (and group for the column fields), lowest id first as fieldname is not unique
    $fieldIds = [];
    $rows = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT f.`cb_field_id`, f.`fieldname`, g.`groupname`
            FROM `cb_field` f
            LEFT JOIN `cb_fieldgroup` g ON g.`cb_fieldgroup_id` = f.`cb_fieldgroup_id`
            WHERE (
                f.`cb_fieldgroup_id` IS NULL
                AND f.`fieldname` IN (
                    'db_host', 'db_port', 'db_user', 'db_password', 'encryption',
                    'queries_per_transaction', 'http_target', 'certificate_path', 'account_id'
                )
            ) OR (
                g.`groupname` IN ('metrics_column', 'status_column')
                AND f.`fieldname` IN ('name', 'value', 'type', 'is_tag')
            )
            ORDER BY f.`cb_field_id` DESC
            SQL
    );
    foreach ($rows as $row) {
        $key = $row['groupname'] === null ? $row['fieldname'] : $row['groupname'] . '__' . $row['fieldname'];
        $fieldIds[$key] = (int) $row['cb_field_id'];
    }

    // [field key, is_required, order_display]
    $relations = [
        ['db_host', 1, 1],
        ['db_port', 0, 2],
        ['encryption', 1, 3],
        ['certificate_path', 0, 4],
        ['http_target', 0, 5],
        ['db_user', 0, 6],
        ['db_password', 0, 7],
        ['account_id', 0, 8],
        ['queries_per_transaction', 0, 9],
        ['metrics_column__name', 0, 10],
        ['metrics_column__value', 0, 11],
        ['metrics_column__type', 0, 12],
        ['metrics_column__is_tag', 0, 13],
        ['status_column__name', 0, 14],
        ['status_column__value', 0, 15],
        ['status_column__type', 0, 16],
        ['status_column__is_tag', 0, 17],
    ];
    $existingFieldIds = array_map(
        'intval',
        $pearDB->fetchFirstColumn(
            'SELECT `cb_field_id` FROM `cb_type_field_relation` WHERE `cb_type_id` = :type_id',
            $typeParameter
        )
    );
    foreach ($relations as [$fieldKey, $isRequired, $orderDisplay]) {
        if (! isset($fieldIds[$fieldKey])) {
            throw new RuntimeException("Broker field '{$fieldKey}' not found");
        }
        if (in_array($fieldIds[$fieldKey], $existingFieldIds, true)) {
            continue;
        }
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_type_field_relation` (`cb_type_id`, `cb_field_id`, `is_required`, `order_display`)
                VALUES (:type_id, :field_id, :is_required, :order_display)
                SQL,
            QueryParameters::create([
                QueryParameter::int('type_id', $typeId),
                QueryParameter::int('field_id', $fieldIds[$fieldKey]),
                QueryParameter::int('is_required', $isRequired),
                QueryParameter::int('order_display', $orderDisplay),
            ])
        );
    }

    LoggerUpgrade::create()->info($version, "Successfully created the Broker output 'victoria_metrics'");
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here
    $createAclFingerprintTable();

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    $errorMessage = 'Unable to start the configuration database transaction';
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the configuration database data here
    $createBrokerOutputVictoriaMetrics();

    $errorMessage = 'Unable to commit the configuration database transaction';
    $pearDB->commitTransaction();

    LoggerUpgrade::create()->info($version, "Upgrade script for version {$version} completed");

} catch (Throwable $throwable) {
    try {
        if ($pearDB->isTransactionActive()) {
            LoggerUpgrade::create()->info($version, "Rolling back transaction after error: {$errorMessage}");
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        LoggerUpgrade::create()->stepFailure(
            $version,
            'php_script_rollback',
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: " . $errorMessage,
            previous: $throwable
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
