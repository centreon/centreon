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

require_once __DIR__ . '/../../class/centreonLog.class.php';
$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 23.04.0: ';
$errorMessage = '';

/**
 * Update illegal_object_name_chars + illegal_macro_output_chars fields from cf_nagios table.
 * The aim is to decode entities from them.
 *
 * @param CentreonDB $pearDB
 */
$decodeIllegalCharactersNagios = function(CentreonDB $pearDB): void
{
    $configs = $pearDB->query(
        <<<'SQL'
            SELECT
                nagios_id,
                illegal_object_name_chars,
                illegal_macro_output_chars
            FROM
                `cfg_nagios`
            SQL
    )->fetchAll(PDO::FETCH_ASSOC);

    $statement = $pearDB->prepare(
        <<<'SQL'
            UPDATE
                `cfg_nagios`
            SET
                illegal_object_name_chars = :illegal_object_name_chars,
                illegal_macro_output_chars = :illegal_macro_output_chars
            WHERE
                nagios_id = :nagios_id
            SQL
    );
    foreach ($configs as $config) {
        $modified = $config;
        $modified['illegal_object_name_chars'] = html_entity_decode($config['illegal_object_name_chars']);
        $modified['illegal_macro_output_chars'] = html_entity_decode($config['illegal_macro_output_chars']);

        if ($config === $modified) {
            // no need to update, we skip a useless query
            continue;
        }

        $statement->bindValue(':illegal_object_name_chars', $modified['illegal_object_name_chars'], \PDO::PARAM_STR);
        $statement->bindValue(':illegal_macro_output_chars', $modified['illegal_macro_output_chars'], \PDO::PARAM_STR);
        $statement->bindValue(':nagios_id', $modified['nagios_id'], \PDO::PARAM_INT);
        $statement->execute();
    }
};

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

        $pearDB->query(
            <<<SQL
            UPDATE provider_configuration
                SET custom_configuration = '$updatedCustomConfigurationEncoded'
            SQL
        );
    }
};

$insertSAMLProviderConfiguration = function (CentreonDB $pearDB): void {
    $customConfiguration = [
        "remote_login_url" => null,
        "entity_id_url" => '',
        "certificate" => '',
        "user_id_attribute" => '',
        "logout_from" => true,
        "logout_from_url" => null,
        "auto_import" => false,
        "contact_template_id" => null,
        "email_bind_attribute" => '',
        "fullname_bind_attribute" => '',
        "authentication_conditions" => [
            'is_enabled' => false,
            'attribute_path' => '',
            'authorized_values' => []
        ],
        "roles_mapping" => [
            'is_enabled' => false,
            'apply_only_first_role' => false,
            'attribute_path' => '',
        ],
        "groups_mapping" => [
            'is_enabled' => false,
            'attribute_path' => '',
        ]
    ];

    $isActive = false;
    $isForced = false;
    $insertStatement = $pearDB->prepare(
        "INSERT INTO provider_configuration (`type`,`name`,`custom_configuration`,`is_active`,`is_forced`)
        VALUES ('saml','SAML', :customConfiguration, :isActive, :isForced)"
    );

    $insertStatement->bindValue(':isActive', $isActive);
    $insertStatement->bindValue(':isForced', $isForced);
    $insertStatement->bindValue(':customConfiguration', json_encode($customConfiguration));
    $insertStatement->execute();
};

try {
    if ($pearDB->isColumnExist('cfg_centreonbroker', 'event_queues_total_size') === 0) {
        $errorMessage = "Impossible to update cfg_centreonbroker table";
        $pearDB->query(
            "ALTER TABLE `cfg_centreonbroker`
            ADD COLUMN `event_queues_total_size` INT(11) DEFAULT NULL
            AFTER `event_queue_max_size`"
        );
    }

    $errorMessage = "Impossible to delete color picker topology_js entries";
    $pearDB->query(
        "DELETE FROM `topology_JS`
        WHERE `PathName_js` = './include/common/javascript/color_picker_mb.js'"
    );


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

    $errorMessage = 'Unable to update illegal characters fields from engine configuration of pollers';
    $decodeIllegalCharactersNagios($pearDB);

    $errorMessage = 'Unable to update provider_configuration table to add redirect_url';
    $updateOpenIdCustomConfiguration($pearDB);

    $errorMessage = 'Unable to add SAML provider_configuration';
    $insertSAMLProviderConfiguration($pearDB);

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
