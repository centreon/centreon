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

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use App\Kernel;
use Core\AgentConfiguration\Application\UseCase\DeployDefaultAgentConfigurationForPoller\{
    DeployDefaultAgentConfigurationForPoller,
    DeployDefaultAgentConfigurationForPollerRequest
};

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */
$deployDefaultAgentConfiguration = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to deploy default agent configuration to central poller';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Deploying default agent configuration to central poller",
    );
    $kernel = Kernel::createForWeb();
    $deployAgentConfiguration = $kernel->getContainer()
        ->get(DeployDefaultAgentConfigurationForPoller::class);
    if (! $deployAgentConfiguration instanceof DeployDefaultAgentConfigurationForPoller) {
        CentreonLog::create()->warning(
            CentreonLog::TYPE_BUSINESS_LOG,
            'DeployDefaultAgentConfigurationForPoller service not found, skipping default agent configuration deployment'
        );

        return;
    }

    $errorMessage = 'Unable to find central poller to deploy default agent configuration';
    $centralId = $pearDB->fetchOne(
        "SELECT `id` FROM `nagios_server` WHERE `is_default` = 1 AND `localhost` = '1'"
    );
    if ($centralId === false) {
        CentreonLog::create()->warning(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Default central poller not found, skipping default agent configuration deployment'
        );

        return;
    }

    $errorMessage = 'Unable to find admin contact to deploy default agent configuration';
    $adminInfos = $pearDB->fetchAssociative(
        "SELECT `contact_id`, `contact_alias` FROM `contact` WHERE `contact_admin` = '1' LIMIT 1"
    );
    if ($adminInfos === false) {
        CentreonLog::create()->warning(
            CentreonLog::TYPE_BUSINESS_LOG,
            'No admin contact found, skipping default agent configuration deployment'
        );

        return;
    }

    $errorMessage = 'Error during default agent configuration deployment';
    $request = new DeployDefaultAgentConfigurationForPollerRequest(
        pollerId: (int) $centralId,
        creatorId: (int) $adminInfos['contact_id'],
        creatorName: $adminInfos['contact_alias'],
    );
    $deployAgentConfiguration($request);
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully deployed default agent configuration to central poller",
    );
};

/** ------------------------------------- cfg_broker_input_output migration ------------------------------------- */
$createCfgCentreonbrokerInputOutputTable = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create table cfg_broker_input_output';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Creating table cfg_broker_input_output",
    );

    $pearDB->executeStatement(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `cfg_broker_input_output` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `config_id`  INT NOT NULL,
                `tag`        ENUM('input','output') NOT NULL,
                `type_id`    INT NOT NULL,
                `type_name`  VARCHAR(50) NOT NULL,
                `name`       VARCHAR(255) NOT NULL,
                `parameters` JSON NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_cbio_config_tag` (`config_id`, `tag`),
                CONSTRAINT `cfg_broker_input_output_ibfk_01`
                    FOREIGN KEY (`config_id`) REFERENCES `cfg_centreonbroker` (`config_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully created table cfg_broker_input_output",
    );
};

$migrateCfgCentreonbrokerInfoData = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to migrate data from cfg_centreonbroker_info to cfg_broker_input_output';

    if ($pearDB->fetchOne('SELECT 1 FROM `cfg_broker_input_output` LIMIT 1') !== false) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: cfg_broker_input_output already has data, skipping migration",
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Migrating cfg_centreonbroker_info data to cfg_broker_input_output",
    );

    $rows = $pearDB->fetchAllAssociative('SELECT * FROM `cfg_centreonbroker_info`');

    // Group EAV rows by (config_id, config_group, config_group_id).
    $groups = [];
    foreach ($rows as $row) {
        $key = "{$row['config_id']}_{$row['config_group']}_{$row['config_group_id']}";
        $groups[$key][] = $row;
    }

    foreach ($groups as $group) {
        $configId   = null;
        $tag        = null;
        $typeId     = null;
        $typeName   = null;
        $name       = null;
        $parameters = [];

        foreach ($group as $row) {
            $configId ??= (int) $row['config_id'];
            $tag      ??= $row['config_group'];

            if ($row['config_key'] === 'name') {
                $name = $row['config_value'];
                continue;
            }
            if ($row['config_key'] === 'blockId') {
                // blockId format: '{prefix}_{type_id}' where prefix is '1' (output) or '2' (input).
                $typeId = (int) substr($row['config_value'], strrpos($row['config_value'], '_') + 1);
                continue;
            }
            if ($row['config_key'] === 'type') {
                $typeName = $row['config_value'];
                continue;
            }
            if ($row['fieldIndex'] !== null) {
                // Grouped field: stored as groupName__subFieldName with a numeric fieldIndex.
                [$grpName, $subName] = explode('__', $row['config_key'], 2);
                $parameters[$grpName][(int) $row['fieldIndex']][$subName] = $row['config_value'];
                continue;
            }
            if ($row['subgrp_id'] !== null) {
                // Multiselect parent row – key carries the field name; value is empty.
                // The actual values come from child rows (parent_grp_id != null).
                continue;
            }
            if ($row['parent_grp_id'] !== null) {
                // Multiselect child row – handled in the second pass below.
                continue;
            }

            $parameters[$row['config_key']] = $row['config_value'];
        }

        // Second pass: collect multiselect child values.
        $multiselectName = null;
        foreach ($group as $row) {
            if ($row['subgrp_id'] !== null && $row['parent_grp_id'] === null) {
                $multiselectName = $row['config_key'];
            }
        }
        if ($multiselectName !== null) {
            foreach ($group as $row) {
                if ($row['parent_grp_id'] !== null) {
                    $parameters["{$multiselectName}_{$row['config_key']}"][] = $row['config_value'];
                }
            }
        }

        if ($configId === null || $tag === null
            || $typeId === null || $typeName === null || $name === null
        ) {
            $missing = implode(', ', array_keys(array_filter(
                ['configId' => $configId, 'tag' => $tag, 'typeId' => $typeId, 'typeName' => $typeName, 'name' => $name],
                static fn ($v) => $v === null,
            )));
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: Skipping incomplete broker block (config_id={$group[0]['config_id']}"
                    . ", config_group={$group[0]['config_group']}"
                    . ", config_group_id={$group[0]['config_group_id']})"
                    . " — missing fields: {$missing}",
            );
            continue;
        }

        $pearDB->executeStatement(
            <<<'SQL'
                INSERT INTO `cfg_broker_input_output`
                    (config_id, tag, type_id, type_name, name, parameters)
                VALUES
                    (:configId, :tag, :typeId, :typeName, :name, :parameters)
                SQL,
            QueryParameters::create([
                QueryParameter::int('configId', $configId),
                QueryParameter::string('tag', $tag),
                QueryParameter::int('typeId', $typeId),
                QueryParameter::string('typeName', $typeName),
                QueryParameter::string('name', $name),
                QueryParameter::string('parameters', json_encode($parameters, JSON_THROW_ON_ERROR)),
            ])
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully migrated " . count($groups) . ' input/output block(s)',
    );
};

/** ------------------------------------- Broker output for CMA ------------------------------------- */
$createBrokerOutputEventScript = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create Broker output event_script';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Creating Broker output 'event_script'",
    );

    // Creating type
    if ($typeId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `cb_type_id` FROM `cb_type`
            WHERE `type_shortname` = 'event_script'
            SQL)
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Broker output 'event_script' already exists, skipping creation",
        );
    } else {
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_type` (`cb_type_id`, `type_name`, `type_shortname`, `cb_module_id`)
                VALUES (NULL, 'Run script on event', 'event_script', 21)
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully created Broker output 'event_script'",
        );

        $typeId = $pearDB->lastInsertId();
    }

    // Creating tag_type relation
    $hasTagRelation = $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `cb_tag_type_relation`
            WHERE `cb_type_id` = :type_id
            SQL,
        QueryParameters::create([QueryParameter::int('type_id', $typeId)])
    );

    if ($hasTagRelation) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Broker output 'event_script' tag relations already exist, skipping creation",
        );
    } else {
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_tag_type_relation` (`cb_type_id`, `cb_tag_id`) VALUES (:type_id, 1)
                SQL,
            QueryParameters::create([QueryParameter::int('type_id', $typeId)])
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully created Broker output 'event_script' tag relations",
        );
    }

    // Creating fields
    $fieldIds = $pearDB->fetchAllKeyValue(
        <<<'SQL'
            SELECT `fieldname`, `cb_field_id` FROM `cb_field`
            WHERE `fieldname` IN ('script_path', 'timeout', 'managed_event_ttl', 'event')
            SQL
    );
    $countFields = count($fieldIds);
    if ($countFields === 4) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: All required fields for Broker output 'event_script' already exist, skipping creation",
        );
    } elseif ($countFields !== 0 && $countFields < 4) {
        // Not supposed to happen
        throw new RuntimeException('Not all required fields for Broker output "event_script" exist');
    } else {
        $pearDB->batchInsert(
            'cb_field', ['fieldname', 'displayname', 'description', 'fieldtype', 'cb_fieldgroup_id', 'external'],
            BatchInsertParameters::create([
                QueryParameters::create([
                    QueryParameter::string('fieldname', 'script_path'),
                    QueryParameter::string('displayname', 'Script path'),
                    QueryParameter::string('description', 'Path to the script to execute'),
                    QueryParameter::string('fieldtype', 'text'),
                    QueryParameter::int('cb_fieldgroup_id', null),
                    QueryParameter::string('external', 'T=options:C=value:CK=key:K=brokercfg_event_script_script_path'),
                ]),
                QueryParameters::create([
                    QueryParameter::string('fieldname', 'timeout'),
                    QueryParameter::string('displayname', 'Timeout'),
                    QueryParameter::string('description', 'Script response time before timeout (in seconds)'),
                    QueryParameter::string('fieldtype', 'int'),
                    QueryParameter::int('cb_fieldgroup_id', null),
                    QueryParameter::string('external', 'T=options:C=value:CK=key:K=brokercfg_event_script_timeout'),
                ]),
                QueryParameters::create([
                    QueryParameter::string('fieldname', 'managed_event_ttl'),
                    QueryParameter::string('displayname', 'Managed event TTL'),
                    QueryParameter::string('description', 'Delay before the script is called again for the same event (in seconds)'),
                    QueryParameter::string('fieldtype', 'int'),
                    QueryParameter::int('cb_fieldgroup_id', null),
                    QueryParameter::string('external', 'T=options:C=value:CK=key:K=brokercfg_event_script_managed_event_ttl'),
                ]),
                QueryParameters::create([
                    QueryParameter::string('fieldname', 'event'),
                    QueryParameter::string('displayname', 'Event'),
                    QueryParameter::string('description', 'Filtered event type'),
                    QueryParameter::string('fieldtype', 'multiselect'),
                    QueryParameter::int('cb_fieldgroup_id', 1),
                    QueryParameter::string('external', 'T=options:C=value:CK=key:K=brokercfg_event_script_event'),
                ]),
            ])
        );

        $fieldIds = $pearDB->fetchAllKeyValue(
            <<<'SQL'
                SELECT `fieldname`, `cb_field_id` FROM `cb_field`
                WHERE `fieldname` IN ('script_path', 'timeout', 'managed_event_ttl', 'event')
                SQL
        );
    }

    if ($pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `options` WHERE `key` = 'brokercfg_event_script_timeout'
            SQL
    ) !== false) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Default options for Broker output 'event_script' already exist, skipping insertion",
        );
    } else {
        $pearDB->batchInsert(
            'options', ['`key`', '`value`'],
            BatchInsertParameters::create([
                QueryParameters::create([
                    QueryParameter::string('key', 'brokercfg_event_script_timeout'),
                    QueryParameter::string('value', '15'),
                ]),
                QueryParameters::create([
                    QueryParameter::string('key', 'brokercfg_event_script_managed_event_ttl'),
                    QueryParameter::string('value', '3600'),
                ]),
                QueryParameters::create([
                    QueryParameter::string('key', 'brokercfg_event_script_script_path'),
                    QueryParameter::string('value', '/usr/share/centreon/bin/console agent-configuration:host:create'),
                ]),
                QueryParameters::create([
                    QueryParameter::string('key', 'brokercfg_event_script_event'),
                    QueryParameter::string('value', 'neb:UnknownHost'),
                ]),
            ])
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully inserted default options for Broker output 'event_script'",
        );
    }

    // Creating type_field relations
    $typeRelationCount = $pearDB->fetchOne(
        <<<'SQL'
            SELECT COUNT(`cb_type_id`) FROM `cb_type_field_relation`
            WHERE `cb_type_id` = :type_id
            SQL,
        QueryParameters::create([QueryParameter::int('type_id', $typeId)])
    );

    if ((int) $typeRelationCount === 4) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Required type_field relations for Broker output 'event_script' already exist, skipping creation",
        );
    } elseif ((int) $typeRelationCount !== 0) {
        // Not supposed to happen
        throw new RuntimeException('Some type_field relations for Broker output "event_script" already exist');
    } else {
        $pearDB->batchInsert(
            'cb_type_field_relation', ['cb_type_id', 'cb_field_id', 'is_required', 'order_display'],
            BatchInsertParameters::create([
                QueryParameters::create([
                    QueryParameter::int('cb_type_id', $typeId),
                    QueryParameter::int('cb_field_id', $fieldIds['script_path']),
                    QueryParameter::int('is_required', 1),
                    QueryParameter::int('order_display', 1),
                ]),
                QueryParameters::create([
                    QueryParameter::int('cb_type_id', $typeId),
                    QueryParameter::int('cb_field_id', $fieldIds['event']),
                    QueryParameter::int('is_required', 0),
                    QueryParameter::int('order_display', 2),
                ]),
                QueryParameters::create([
                    QueryParameter::int('cb_type_id', $typeId),
                    QueryParameter::int('cb_field_id', $fieldIds['timeout']),
                    QueryParameter::int('is_required', 1),
                    QueryParameter::int('order_display', 3),
                ]),
                QueryParameters::create([
                    QueryParameter::int('cb_type_id', $typeId),
                    QueryParameter::int('cb_field_id', $fieldIds['managed_event_ttl']),
                    QueryParameter::int('is_required', 1),
                    QueryParameter::int('order_display', 4),
                ]),
            ])
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully created type_field relations for Broker output 'event_script'",
        );
    }

    // Creating event options list
    $listId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `cb_list_id` FROM `cb_list`
            WHERE `cb_field_id` = :field_id
            SQL,
        QueryParameters::create([QueryParameter::int('field_id', $fieldIds['event'])])
    );

    if ($listId === false) {
        $listId = $pearDB->fetchOne(
            <<<'SQL'
                SELECT MAX(`cb_list_id`) FROM `cb_list`
                SQL
        );
        $listId = (int) $listId + 1;
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_list` (`cb_list_id`, `cb_field_id`, `default_value`)
                VALUES (:list_id, :field_id, NULL)
                SQL,
            QueryParameters::create([
                QueryParameter::int('list_id', $listId),
                QueryParameter::int('field_id', $fieldIds['event']),
            ])
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Event options list for Broker output 'event_script' already exists, skipping creation",
        );
    }

    $eventOptions = [
        'neb:Acknowledgement',
        'neb:AdaptiveHost',
        'neb:AdaptiveHostStatus',
        'neb:AdaptiveService',
        'neb:AdaptiveServiceStatus',
        'neb:AgentStats',
        'neb:Comment',
        'neb:CustomVariables',
        'neb:Downtime',
        'neb:Host',
        'neb:HostCheck',
        'neb:HostGroup',
        'neb:HostGroupMember',
        'neb:HostParent',
        'neb:HostStatus',
        'neb:Instance',
        'neb:InstanceConfiguration',
        'neb:InstanceStatus',
        'neb:LogEntry',
        'neb:OTLMetrics',
        'neb:ResponsiveInstance',
        'neb:Service',
        'neb:ServiceCheck',
        'neb:ServiceGroup',
        'neb:ServiceGroupMember',
        'neb:ServiceStatus',
        'neb:Severity',
        'neb:Tag',
        'neb:UnknownHost',
    ];
    $listHasValue = $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `cb_list_values`
            WHERE `cb_list_id` = :list_id
            SQL,
        QueryParameters::create([QueryParameter::int('list_id', $listId)])
    );

    if ($listHasValue) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Event options for Broker output 'event_script' already exist, skipping creation",
        );
    } else {
        $pearDB->batchInsert(
            'cb_list_values', ['cb_list_id', 'value_name', 'value_value'],
            BatchInsertParameters::create(array_map(
                fn ($option) => QueryParameters::create([
                    QueryParameter::int('cb_list_id', $listId),
                    QueryParameter::string('value_name', $option),
                    QueryParameter::string('value_value', $option),
                ]),
                $eventOptions
            ))
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully created event options list for Broker output 'event_script'",
    );
};

$insertEventScriptOutputForCMA = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to insert event_script output for CMA';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Inserting Broker output 'central-broker-master-event-script' for CMA",
    );

    if ($pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `cfg_broker_input_output`
            WHERE `name` = 'central-broker-master-event-script'
            AND `config_id` = 1
            SQL
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Broker output 'central-broker-master-event-script' for CMA already exists, skipping insertion",
        );

        return;
    }

    $typeId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `cb_type_id` FROM `cb_type`
            WHERE `type_shortname` = 'event_script'
            SQL
    );

    $parameters = json_encode([
        'script_path'       => '/usr/share/centreon/bin/console agent-configuration:host:create',
        'timeout'           => '15',
        'managed_event_ttl' => '3600',
        'filters_event'     => ['neb:UnknownHost'],
    ], JSON_THROW_ON_ERROR);

    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO `cfg_broker_input_output`
                (config_id, tag, type_id, type_name, name, parameters)
            VALUES
                (:configId, 'output', :typeId, 'event_script', 'central-broker-master-event-script', :parameters)
            SQL,
        QueryParameters::create([
            QueryParameter::int('configId', 1),
            QueryParameter::int('typeId', $typeId),
            QueryParameter::string('parameters', $parameters),
        ])
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully inserted Broker output 'central-broker-master-event-script' for CMA",
    );
};

$dropCfgCentreonbrokerInfoTable = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to drop table cfg_centreonbroker_info';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Dropping legacy table cfg_centreonbroker_info",
    );

    $pearDB->executeStatement('DROP TABLE IF EXISTS `cfg_centreonbroker_info`');

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully dropped legacy table cfg_centreonbroker_info",
    );
};

/** -------------------------------------- Command redesign updates-------------------------------------- */
$addNewCommandPage = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add new command page topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding new command page to topology",
    );
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
            INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url_substitute`)
            VALUES ( 'Commands', '/configuration/commands', '1', '1', 608, 60808, 1, 1, './include/configuration/configObject/command/command.php')
            SQL
    );
};

$updateCommandsParentTopology = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to update parent commands topology';
    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_url` = '/configuration/commands', `is_react` = '1'
            WHERE `topology_page` = 608
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
            VALUES (:acl_topo_id, (SELECT topology_id from topology where topology_page = 60808), :access_right)
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_topo_id', $aclTopologyId),
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

$moveCommandACLTopologyIntoACLActions = function () use ($pearDB, &$errorMessage, $deleteCommandsTopologyRights, $getOrCreateActionGroup, $addCommandRightIntoAction, $insertNewCommandsTopologyRights, $version): void {
    $errorMessage = 'Unable to read acl topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Moving command ACL topology into ACL actions",
    );
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

$deleteOldCommandsTopologies = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to remove old command pages from topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Removing old command pages from topology",
    );
    $pearDB->delete(
        <<<'SQL'
            DELETE FROM `topology`
            WHERE `topology_page` IN (60801, 60802, 60803, 60807)
            SQL
    );
};

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    $createCfgCentreonbrokerInputOutputTable();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $migrateCfgCentreonbrokerInfoData();
    $createBrokerOutputEventScript();
    $insertEventScriptOutputForCMA();

    // Command redesign updates
    $addNewCommandPage();
    $updateCommandsParentTopology();
    $moveCommandACLTopologyIntoACLActions();
    $deleteOldCommandsTopologies();

    if ($pearDB->isTransactionActive()) {
        $pearDB->commitTransaction();
    }

    // DDL: drop the legacy EAV table now that migration is complete
    $dropCfgCentreonbrokerInfoTable();

    try {
        $deployDefaultAgentConfiguration();
    } catch (Throwable $e) {
        CentreonLog::create()->warning(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Default agent configuration deployment failed, it can be done manually",
            exception: $e
        );
    }
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
