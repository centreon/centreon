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

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// -------------------------------------- AgentConfiguration updates --------------------------------------

/**
 * Align preexisting Agent Configuration with the new schema:
 *      - Add is_poller_initiated bool
 *      - Add is_agent_initiated bool
 *      - Remove is_reverse bool
 */
$alignCMAAgentConfigurationWithNewSchema = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to align agent configuration with new schema';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: aligning agent configuration with new schema"
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: retrieving agent configurations from database..."
    );

    $agentConfigurations = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT * FROM `agent_configuration`
            WHERE `type` = 'centreon-agent'
            SQL
    );
    if ($agentConfigurations === []) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: no agent configurations found, skipping"
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: found " . count($agentConfigurations) . ' agent configurations, updating...'
    );

    foreach ($agentConfigurations as $agentConfiguration) {
        $configuration = json_decode(
            json: $agentConfiguration['configuration'],
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );
        $configuration['agent_initiated'] = false;
        $configuration['poller_initiated'] = false;

        if ($configuration['is_reverse']) {
            $configuration['poller_initiated'] = true;
            unset($configuration['is_reverse']);
        } else {
            $configuration['agent_initiated'] = true;
            unset($configuration['is_reverse']);
        }

        $pearDB->update(
            <<<'SQL'
                    UPDATE agent_configuration
                    SET configuration = :configuration
                    WHERE id = :id
                SQL,
            QueryParameters::create([
                QueryParameter::string(':configuration', json_encode($configuration, JSON_THROW_ON_ERROR)),
                QueryParameter::int(':id', $agentConfiguration['id']),
            ])
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: agent configurations aligned successfully"
    );
};

$cleanGlobalMacrosName = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Failed to clean global macros name';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: cleaning global macros name"
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: retrieving invalid macros from database..."
    );

    $invalidMacros = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT resource_id, resource_name FROM cfg_resource
            WHERE resource_name NOT LIKE '\$%' OR resource_name NOT LIKE '%\$'
            SQL
    );

    if ($invalidMacros === []) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: no invalid macros found, skipping"
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: found " . count($invalidMacros) . ' invalid macros, updating...'
    );

    foreach ($invalidMacros as $macro) {
        $newName = $macro['resource_name'];
        if (str_starts_with($newName, '$') === false) {
            $newName = '$' . $newName;
        }
        if (str_ends_with($newName, '$') === false) {
            $newName .= '$';
        }
        $pearDB->update(
            <<<'SQL'
                UPDATE cfg_resource
                SET resource_name = :resource_name
                WHERE resource_id = :id
                SQL,
            QueryParameters::create([
                QueryParameter::string(':resource_name', $newName),
                QueryParameter::int(':id', (int) $macro['resource_id']),
            ])
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: global macros name cleaned successfully"
    );
};

$fixTypoInStandardMacroName = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Failed to fix typo in standard macro name';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: fixing typo in standard macro name..."
    );

    $nbUpdate = $pearDB->update(
        <<<'SQL'
                UPDATE nagios_macro SET macro_name = '$TOTALHOSTSUNREACHABLEUNHANDLED$' WHERE macro_id = 65
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: {$nbUpdate} typo in standard macro name fixed successfully"
    );
};

/**
 * Update SAML provider configuration:
 *      - If requested_authn_context exists, set requested_authn_context_comparison to its value and requested_authn_context to true
 *      - If requested_authn_context does not exist, set requested_authn_context_comparison to 'exact' and requested_authn_context to false
 */
$updateSamlProviderConfiguration = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to retrieve SAML provider configuration';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating SAML provider configuration"
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: retrieving SAML provider configuration from database..."
    );

    $samlConfiguration = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT * FROM `provider_configuration`
            WHERE `type` = 'saml'
            SQL
    );

    if (! $samlConfiguration || ! isset($samlConfiguration['custom_configuration'])) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: no SAML provider configuration found, skipping"
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: SAML provider configuration found, checking for requested_authn_context"
    );

    $customConfiguration = json_decode($samlConfiguration['custom_configuration'], true, JSON_THROW_ON_ERROR);

    if (isset($customConfiguration['requested_authn_context'])) {
        $customConfiguration['requested_authn_context_comparison'] = $customConfiguration['requested_authn_context'];
        $customConfiguration['requested_authn_context'] = true;

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context found, requested_authn_context_comparison takes the value of requested_authn_context, and requested_authn_context is set to true"
        );
    } else {
        $customConfiguration['requested_authn_context_comparison'] = 'exact';
        $customConfiguration['requested_authn_context'] = false;

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context not found, setting requested_authn_context to false and requested_authn_context_comparison to 'exact'"
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating SAML provider configuration in database..."
    );

    $query = <<<'SQL'
            UPDATE `provider_configuration`
            SET `custom_configuration` = :custom_configuration
            WHERE `type` = 'saml'
        SQL;
    $queryParameters = QueryParameters::create(
        [QueryParameter::string('custom_configuration', json_encode($customConfiguration, JSON_THROW_ON_ERROR))]
    );
    $pearDB->update($query, $queryParameters);

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: SAML provider configuration updated successfully"
    );
};

/** -------------------------------------- Broker configuration -------------------------------------- */
$fixBrokerConfigTypo = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Failed to fix typo in broker configuration';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: fixing typo in broker configuration..."
    );

    $nbUpdate = $pearDB->executeStatement(
        <<<'SQL'
            UPDATE cfg_centreonbroker_info SET config_key = 'negotiation' WHERE config_key = 'negociation'
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: {$nbUpdate} typo in broker configuration fixed successfully"
    );
};

/** -------------------------------------- Engine Configuration updates -------------------------------------- */
$addOpentelemetryLogLevelColumn = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Failed to add log_level_otl column to cfg_nagios_logger table';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: adding log_level_otl column to cfg_nagios_logger table..."
    );

    if (! $pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'cfg_nagios_logger',
        'log_level_otl'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: log_level_otl column does not exist, adding it..."
        );

        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `cfg_nagios_logger`
                ADD COLUMN `log_level_otl` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err'
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: log_level_otl column added successfully"
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: log_level_otl column already exists, skipping"
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: log_level_otl column already exists, skipping"
    );
};

/** -------------------------------------------- BBDO cfg update -------------------------------------------- */
$bbdoDefaultUpdate = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = "Unable to update 'bbdo_version' column to 'cfg_centreonbroker' table";

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating 'bbdo_version' column to 'cfg_centreonbroker' table"
    );

    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'cfg_centreonbroker',
        'bbdo_version'
    )) {

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: 'bbdo_version' column exists, modifying it..."
        );

        $pearDB->executeStatement('ALTER TABLE `cfg_centreonbroker` MODIFY `bbdo_version` VARCHAR(50) DEFAULT "3.0.1"');

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: 'bbdo_version' column modified successfully"
        );

    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: 'bbdo_version' column does not exist, skipping"
        );
    }
};

$bbdoCfgUpdate = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = "Unable to update 'bbdo_version' version in 'cfg_centreonbroker' table";

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating 'bbdo_version' version in 'cfg_centreonbroker' table"
    );

    $pearDB->executeStatement('UPDATE `cfg_centreonbroker` SET `bbdo_version` = "3.0.1"');

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: 'bbdo_version' version updated successfully"
    );
};

// -------------------------------------------- Password encryption --------------------------------------------

$addIsEncryptionReadyAsBooleanColumn = function () use ($pearDB, $pearDBO, &$errorMessage, $version): void {
    $errorMessage = "Unable to update 'is_encryption_ready' column to boolean type";

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating 'is_encryption_ready' column to boolean type"
    );

    if (
        $pearDB->columnExists(
            $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
            'nagios_server',
            'is_encryption_ready'
        )
        && ! $pearDB->columnExists(
            $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
            'nagios_server',
            'is_encryption_ready_old'
        )
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Renaming column 'is_encryption_ready' on 'nagios_server' table",
        );

        $pearDB->executeStatement('ALTER TABLE `nagios_server` RENAME COLUMN `is_encryption_ready` TO `is_encryption_ready_old`');

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Column 'is_encryption_ready' renamed successfully on 'nagios_server' table",
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Column 'is_encryption_ready' already renamed on 'nagios_server' table, skipping",
        );
    }

    if (! $pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'is_encryption_ready'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Adding column 'is_encryption_ready' of type boolean on 'nagios_server' table",
        );

        $pearDB->executeStatement('ALTER TABLE `nagios_server` ADD COLUMN `is_encryption_ready` BOOLEAN NOT NULL DEFAULT 1');

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Column 'is_encryption_ready' added successfully on 'nagios_server' table",
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Column 'is_encryption_ready' already exists on 'nagios_server' table, skipping",
        );
    }

    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'is_encryption_ready_old'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Moving 'is_encryption_ready' value of existing pollers on 'nagios_server' table",
        );

        $pearDB->executeStatement(
            <<<'SQL'
                UPDATE nagios_server ns
                SET ns.is_encryption_ready = 0
                WHERE ns.is_encryption_ready_old = '0'
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] 'is_encryption_ready' values moved successfully on 'nagios_server' table",
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Dropping column 'is_encryption_ready_old' on 'nagios_server' table",
        );

        $pearDB->executeStatement('ALTER TABLE `nagios_server` DROP COLUMN `is_encryption_ready_old`');

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Column 'is_encryption_ready_old' dropped successfully on 'nagios_server' table",
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Configuration] Column 'is_encryption_ready_old' does not exist on 'nagios_server' table, skipping",
        );
    }

    if (
        $pearDBO->columnExists(
            $pearDBO->getConnectionConfig()->getDatabaseNameRealTime(),
            'instances',
            'is_encryption_ready'
        )
        && ! $pearDBO->columnExists(
            $pearDBO->getConnectionConfig()->getDatabaseNameRealTime(),
            'instances',
            'is_encryption_ready_old'
        )
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Moving 'is_encryption_ready' value of existing pollers on 'instances' table",
        );

        $pearDBO->executeStatement('ALTER TABLE `instances` RENAME COLUMN `is_encryption_ready` TO `is_encryption_ready_old`');

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Column 'is_encryption_ready' renamed successfully on 'instances' table",
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Column 'is_encryption_ready' already renamed on 'instances' table, skipping",
        );
    }

    if (! $pearDBO->columnExists(
        $pearDBO->getConnectionConfig()->getDatabaseNameRealTime(),
        'instances',
        'is_encryption_ready'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Adding column 'is_encryption_ready' of type boolean on 'instances' table",
        );

        $pearDBO->executeStatement('ALTER TABLE `instances` ADD COLUMN `is_encryption_ready` BOOLEAN NOT NULL DEFAULT 0');

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Column 'is_encryption_ready' added successfully on 'instances' table",
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Column 'is_encryption_ready' already exists on 'instances' table, skipping",
        );
    }

    if ($pearDBO->columnExists(
        $pearDBO->getConnectionConfig()->getDatabaseNameRealTime(),
        'instances',
        'is_encryption_ready_old'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Moving 'is_encryption_ready' value of existing pollers on 'instances' table",
        );

        $pearDBO->executeStatement(
            <<<'SQL'
                UPDATE instances ins
                SET ins.is_encryption_ready = 1
                WHERE ins.is_encryption_ready_old = '1'
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] 'is_encryption_ready' values moved successfully on 'instances' table",
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Dropping column 'is_encryption_ready_old' on 'instances' table",
        );

        $pearDBO->executeStatement('ALTER TABLE `instances` DROP COLUMN `is_encryption_ready_old`');

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Column 'is_encryption_ready_old' dropped successfully on 'instances' table",
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [DB Realtime] Column 'is_encryption_ready_old' does not exist on 'instances' table, skipping",
        );
    }
};

/** -------------------------------------- Command redesign updates-------------------------------------- */
$addNewCommandPage = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to add new command page topology';
    $alreadyExist = $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM topology WHERE topology_page = 60808
            SQL
    );
    if ($alreadyExist) {
        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`)
            VALUES ( 'Commands', '/configuration/commands', '1', '1', 608, 60808, 1, 1)
            SQL
    );
};

$deleteCommandsTopologyRights = function (int $aclTopologyId) use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to delete from table acl_topology_relations';
    $pearDB->executeStatement(
        <<<'SQL'
            DELETE acl_topology_relations
            FROM acl_topology_relations
            WHERE acl_topology_relations.acl_topo_id = :acl_topo_id
            AND acl_topology_relations.topology_topology_id IN (
                SELECT topology_id FROM topology WHERE topology_page IN (60801, 60802, 60803, 60807)
            )
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_topo_id', $aclTopologyId),
        ])
    );
};

$insertNewCommandsTopologyRights = function (int $aclTopologyId) use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to insert into table acl_topology_relations';
    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO acl_topology_relations (acl_topo_id, topology_topology_id, access_right)
            VALUES (:acl_topo_id, :topology_topology_id, :access_right)
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_topo_id', $aclTopologyId),
            QueryParameter::int('topology_topology_id', 60808),
            QueryParameter::int('access_right', 1),
        ])
    );
};

$getOrCreateActionGroup = function (int $aclGroupId, array &$actionGroupRelations) use ($pearDB, &$errorMessage): array {
    $actionGroup = null;
    foreach ($actionGroupRelations as $relation) {
        if ($relation['acl_group_id'] === $aclGroupId) {
            $actionGroup = $relation;
            break;
        }
    }

    if ($actionGroup !== null) {

        return $actionGroup;
    }

    $errorMessage = 'Unable to create a new acl_action';
    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO acl_actions (acl_action_name, acl_action_activate)
            VALUES (CONCAT((SELECT acl_group_name FROM acl_groups WHERE acl_group_id = :acl_group_id), '_actions'), '1')
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_group_id', $aclGroupId),
        ])
    );
    $actionId = (int) $pearDB->lastInsertId();

    $errorMessage = 'Unable to link a new acl_action to an acl_group';
    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO acl_group_actions_relations (acl_group_id, acl_action_id)
            VALUES (:acl_group_id, :acl_action_id)
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_group_id', $aclGroupId),
            QueryParameter::int('acl_action_id', $actionId),
        ])
    );
    $actionGroup = [
        'acl_group_id' => $aclGroupId,
        'acl_action_id' => $actionId,
    ];
    $actionGroupRelations[] = $actionGroup;

    return $actionGroup;
};

$addCommandRightIntoAction = function (string $commandType, int $accessRight, int $aclActionId) use ($pearDB, &$errorMessage): void {
    if ($accessRight === 0) {
        return;
    }
    $actionName = match($accessRight) {
        1 => "manage_{$commandType}_commands",
        2 => "see_{$commandType}_commands",
        default => null,
    };

    if ($actionName === null) {
        // Should never occur
        return;
    }

    $errorMessage = 'Unable to read into table acl_actions_rules';
    $alreadyExist = $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM acl_actions_rules
            WHERE acl_action_rule_id = :acl_action_id
            AND acl_action_name = :action_name
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_action_id', $aclActionId),
            QueryParameter::string('action_name', $actionName),
        ])
    );

    if ($alreadyExist) {
        return;
    }

    $errorMessage = 'Unable to insert into table acl_actions_rules';
    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO acl_actions_rules (acl_action_rule_id, acl_action_name)
            VALUES (:acl_action_id, :action_name)
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_action_id', $aclActionId),
            QueryParameter::string('action_name', $actionName),
        ])
    );
};

$moveCommandACLTopologyIntoACLActions = function () use ($pearDB, &$errorMessage, $deleteCommandsTopologyRights, $getOrCreateActionGroup, $addCommandRightIntoAction, $insertNewCommandsTopologyRights): void {
    $errorMessage = 'Unable to read acl topology';
    $topologyGroupRelations = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT acl_topology.acl_topo_id, group_topo_rel.acl_group_id FROM acl_topology
            LEFT JOIN acl_group_topology_relations as group_topo_rel
                ON acl_topology.acl_topo_id = group_topo_rel.acl_topology_id
            SQL
    );

    $errorMessage = 'Unable to read acl actions';
    $actionGroupRelations = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT action.acl_action_id, group_action_rel.acl_group_id FROM acl_actions as action
            LEFT JOIN acl_group_actions_relations as group_action_rel
                ON action.acl_action_id = group_action_rel.acl_action_id
            SQL
    );

    foreach ($topologyGroupRelations as $topoGroup) {
        $aclGroupId = $topoGroup['acl_group_id'];
        $aclTopologyId = $topoGroup['acl_topo_id'];
        $commandAccessRights = $pearDB->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    topology.topology_name,
                    acl_topo_rel.topology_topology_id as topology_id,
                    acl_topo_rel.access_right
                FROM topology
                INNER JOIN acl_topology_relations as acl_topo_rel
                    ON topology.topology_id = acl_topo_rel.topology_topology_id
                    AND acl_topo_rel.acl_topo_id = :acl_topo_id
                WHERE topology.topology_page IN (60801, 60802, 60803, 60807)
                SQL,
            QueryParameters::create([
                QueryParameter::int('acl_topo_id', $aclTopologyId),
            ])
        );
        if ($commandAccessRights === []) {
            continue;
        }
        if ($topoGroup['acl_group_id'] === null) {
            $deleteCommandsTopologyRights($aclTopologyId);
            continue;
        }

        $actionGroup = $getOrCreateActionGroup($aclGroupId, $actionGroupRelations);

        foreach ($commandAccessRights as $commandRights) {
            $commandType = match($commandRights['topology_name']) {
                'Checks' => 'check',
                'Notifications' => 'notification',
                'Discovery' => 'discovery',
                'Miscellaneous' => 'miscellaneous',
            };

            $addCommandRightIntoAction($commandType, $commandRights['access_right'], $actionGroup['acl_action_id']);
        }
        $topologyToClean[] = $aclTopologyId;
    }

    $topologyToClean = array_unique($topologyToClean ?? []);
    foreach ($topologyToClean ?? [] as $aclTopologyId) {
        $insertNewCommandsTopologyRights($aclTopologyId);
        $deleteCommandsTopologyRights($aclTopologyId);
    }
};

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    $bbdoDefaultUpdate();
    $addOpentelemetryLogLevelColumn();
    $addIsEncryptionReadyAsBooleanColumn();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the configuration database data here
    $alignCMAAgentConfigurationWithNewSchema();
    $cleanGlobalMacrosName();
    $fixTypoInStandardMacroName();
    $addNewCommandPage();
    $moveCommandACLTopologyIntoACLActions();
    $fixBrokerConfigTypo();
    $bbdoCfgUpdate();
    $updateSamlProviderConfiguration();

    $pearDB->commitTransaction();

} catch (Throwable $throwable) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $throwable
    );

    try {
        if ($pearDB->isTransactionActive()) {
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            previous: $rollbackException
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
