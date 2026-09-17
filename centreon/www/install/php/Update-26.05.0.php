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
use Symfony\Component\Uid\Uuid;

require_once __DIR__ . '/../../../bootstrap.php';

$version = '26.05.0';

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

/** ------------------------------------- Broker output for CMA ------------------------------------- */
$isMachineACentral = function () use ($pearDB, &$errorMessage, $version): bool {
    $errorMessage = 'Unable to check if platform is a Central';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Check if platform is Central",
    );

    $isCentral = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `value` FROM `informations`
            WHERE `key` = 'isCentral'
            SQL
    );

    if ($isCentral === 'yes') {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: server is a central",
        );

        return true;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: server is not a central",
    );

    return false;
};

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

    $configId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT cb.config_id
            FROM cfg_centreonbroker cb
            WHERE config_activate = '1'
                AND ns_nagios_server = (
                    SELECT id FROM nagios_server WHERE localhost = '1'
                )
                AND EXISTS (
                    SELECT 1
                    FROM cfg_centreonbroker_info cbi
                    WHERE cbi.config_id = cb.config_id
                        AND cbi.config_group = 'output'
                        AND cbi.config_key = 'type'
                        AND cbi.config_value = 'unified_sql'
                )
            ORDER BY cb.config_id ASC
            LIMIT 1
            SQL
    );
    if ($configId === false) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Unable to find the Central's broker configuration"
        );

        throw new Exception("Unable to find the Central's broker configuration");
    }

    if ($pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `cfg_centreonbroker_info`
            WHERE `config_key` = 'name'
            AND `config_value` = 'central-broker-master-event-script'
            SQL
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Broker output 'central-broker-master-event-script' for CMA already exists, skipping insertion",
        );

        return;
    }

    $configGroupId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT MAX(`config_group_id`) FROM `cfg_centreonbroker_info` WHERE `config_group` = 'output' AND `config_id` = :config_id
            SQL,
        QueryParameters::create([QueryParameter::int('config_id', $configId)])
    );
    $configGroupId = $configGroupId !== null ? (int) $configGroupId + 1 : 1;
    $typeId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `cb_type_id` FROM `cb_type`
            WHERE `type_shortname` = 'event_script'
            SQL
    );

    $pearDB->batchInsert(
        'cfg_centreonbroker_info',
        ['config_id', 'config_key', 'config_value', 'config_group', 'config_group_id', 'grp_level', 'subgrp_id', 'parent_grp_id'],
        BatchInsertParameters::create([
            QueryParameters::create([
                QueryParameter::int('config_id', $configId),
                QueryParameter::string('config_key', 'type'),
                QueryParameter::string('config_value', 'event_script'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', $configGroupId),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', $configId),
                QueryParameter::string('config_key', 'name'),
                QueryParameter::string('config_value', 'central-broker-master-event-script'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', $configGroupId),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', $configId),
                QueryParameter::string('config_key', 'blockId'),
                QueryParameter::string('config_value', '1_' . $typeId),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', $configGroupId),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', $configId),
                QueryParameter::string('config_key', 'script_path'),
                QueryParameter::string('config_value', '/usr/share/centreon/bin/console agent-configuration:host:create'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', $configGroupId),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', $configId),
                QueryParameter::string('config_key', 'timeout'),
                QueryParameter::string('config_value', '15'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', $configGroupId),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', $configId),
                QueryParameter::string('config_key', 'managed_event_ttl'),
                QueryParameter::string('config_value', '3600'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', $configGroupId),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', $configId),
                QueryParameter::string('config_key', 'filters'),
                QueryParameter::string('config_value', ''),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', $configGroupId),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', 1),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', $configId),
                QueryParameter::string('config_key', 'event'),
                QueryParameter::string('config_value', 'neb:UnknownHost'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', $configGroupId),
                QueryParameter::int('grp_level', 1),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', 1),
            ]),
        ])
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully inserted Broker output 'central-broker-master-event-script' for CMA",
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

$clearDefaultCurveTemplateLegend = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to clear ds_legend for default curve templates';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Clearing ds_legend for default curve templates",
    );
    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE `giv_components_template`
            SET `ds_legend` = NULL
            WHERE `default_tpl1` = '1'
            SQL
    );
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

/** ------------------------------------- Pollers ------------------------------------- */
$addPollerTypeColumn = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add poller_type column to nagios_server';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding poller_type column to nagios_server",
    );

    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'poller_type'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Column poller_type already exists on nagios_server, skipping",
        );

        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server` ADD COLUMN `poller_type` enum('vm','docker') NOT NULL DEFAULT 'vm'
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully added poller_type column to nagios_server",
    );
};

$addPollerUuidColumn = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add uuid column to nagios_server';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding uuid column to nagios_server",
    );

    $hasUuidColumn = $pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'uuid'
    );

    if (! $hasUuidColumn) {
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                    ADD COLUMN `uuid` VARCHAR(36) DEFAULT NULL COMMENT 'UUIDv7 (36 chars with hyphens)'
                SQL
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Column uuid already exists on nagios_server",
        );
    }

    $hasUniqUuidIndex = (bool) $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'nagios_server'
              AND INDEX_NAME = 'uniq_uuid'
            LIMIT 1
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $pearDB->getDatabaseName()),
        ])
    );

    if (! $hasUniqUuidIndex) {
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                    ADD UNIQUE KEY `uniq_uuid` (`uuid`)
                SQL
        );
    }

    $pollersWithoutUuid = $pearDB->fetchAllAssociative(
        'SELECT id FROM `nagios_server` WHERE `uuid` IS NULL'
    );

    foreach ($pollersWithoutUuid as $poller) {
        $pearDB->executeStatement(
            'UPDATE `nagios_server` SET `uuid` = :uuid WHERE `id` = :id',
            QueryParameters::create([
                QueryParameter::string('uuid', Uuid::v7()->toRfc4122()),
                QueryParameter::int('id', (int) $poller['id']),
            ])
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully added uuid column to nagios_server",
    );
};

$addPollerNameUniqueConstraint = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add unique constraint on nagios_server.name';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding unique constraint on nagios_server.name",
    );

    $hasUniqNameIndex = (bool) $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'nagios_server'
              AND INDEX_NAME = 'uniq_name'
            LIMIT 1
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $pearDB->getDatabaseName()),
        ])
    );

    if ($hasUniqNameIndex) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Unique constraint on nagios_server.name already exists, skipping",
        );

        return;
    }

    $duplicates = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT `name`, GROUP_CONCAT(`id` ORDER BY `id`) AS poller_ids
            FROM `nagios_server`
            GROUP BY `name`
            HAVING COUNT(*) > 1
            SQL
    );

    if ($duplicates !== []) {
        $details = array_map(
            static fn (array $row): string => "'{$row['name']}' (ids: {$row['poller_ids']})",
            $duplicates
        );

        throw new RuntimeException(
            'Cannot add unique constraint on nagios_server.name: duplicate poller names found — '
            . implode(', ', $details)
            . '. Please rename the duplicates manually before upgrading.'
        );
    }

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server` ADD UNIQUE KEY `uniq_name` (`name`)
            SQL
    );
};

/** ------------------------------------- SAML ------------------------------------- */
/**
 * Recover SAML provider configurations whose requested_authn_context_comparison field was left in an
 * invalid state by the non-idempotent 25.11.0 migration (MON-198174): a boolean, a missing value, or
 * a string outside RequestedAuthnContextComparisonEnum breaks CustomConfiguration::createFromValues()
 * and the login page. When detected, reset the value to 'exact'.
 */
$fixSamlRequestedAuthnContextComparison = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to recover SAML provider configuration';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: checking SAML provider configuration for invalid requested_authn_context_comparison"
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

    $customConfiguration = json_decode(
        json: $samlConfiguration['custom_configuration'],
        associative: true,
        flags: JSON_THROW_ON_ERROR
    );

    $validComparisonValues = ['minimum', 'exact', 'better', 'maximum'];
    $currentComparison = $customConfiguration['requested_authn_context_comparison'] ?? null;

    if (is_string($currentComparison) && in_array($currentComparison, $validComparisonValues, true)) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context_comparison already valid, no recovery needed"
        );

        return;
    }

    $customConfiguration['requested_authn_context_comparison'] = 'exact';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: requested_authn_context_comparison was not a valid string, reset to 'exact'"
    );

    $query = <<<'SQL'
            UPDATE `provider_configuration`
            SET `custom_configuration` = :custom_configuration
            WHERE `id` = :id
        SQL;
    $queryParameters = QueryParameters::create(
        [
            QueryParameter::string('custom_configuration', json_encode($customConfiguration, JSON_THROW_ON_ERROR)),
            QueryParameter::int('id', (int) $samlConfiguration['id']),
        ]
    );
    $pearDB->update($query, $queryParameters);

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: SAML provider configuration recovered successfully"
    );
};

/** -------------------------------------- Poller tokens -------------------------------------- */
$updateAuthenticationTable = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to update and rename jwt_tokens table';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Updating jwt_tokens table",
    );
    $tableExistWithOldName = $pearDB->fetchOne(
        <<<'SQL'
            SELECT true FROM INFORMATION_SCHEMA.TABLES
            WHERE table_schema = :db_name AND table_name = 'jwt_tokens'
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $pearDB->getDatabaseName()),
        ])
    );
    if ($tableExistWithOldName) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Renaming jwt_tokens table",
        );
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `jwt_tokens` RENAME TO `authentication_tokens`, COMMENT 'Table for tokens not used for api/ui login'
                SQL
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: jwt_tokens table not found, skipping rename",
        );
    }

    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'authentication_tokens',
        'type'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Nothing to update in authentication_tokens table",
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Updating authentication_tokens table",
    );
    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `authentication_tokens`
            ADD COLUMN `type` enum('cma','poller') DEFAULT 'cma' COMMENT 'Define token usage',
            MODIFY COLUMN `token_string` varchar(4096) DEFAULT NULL COMMENT 'token string'
            SQL
    );
};

$createDefaultPollerToken = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create default poller token';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Creating default poller token",
    );

    if ($pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `authentication_tokens` WHERE `token_name` = 'poller-default' AND `type` = 'poller'
            SQL
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Default poller token already exists, skipping creation",
        );

        return;
    }

    $adminInfos = $pearDB->fetchAssociative(
        "SELECT `contact_id`, `contact_alias` FROM `contact` WHERE `contact_admin` = '1' LIMIT 1"
    );
    if ($adminInfos === false) {
        CentreonLog::create()->warning(
            CentreonLog::TYPE_BUSINESS_LOG,
            'No admin contact found, skipping default poller token creation'
        );

        return;
    }

    $pearDB->insert(
        <<<'SQL'
            INSERT INTO `authentication_tokens`
                (`token_string`, `token_name`, `creator_id`, `creator_name`, `encoding_key`, `is_revoked`, `creation_date`, `expiration_date`, `type`)
            VALUES
                (:token_string, 'poller-default', :creator_id, :creator_name, NULL, 0, :creation_date, NULL, 'poller')
            SQL,
        QueryParameters::create([
            QueryParameter::string('token_string', Security\Encryption::generateRandomString()),
            QueryParameter::int('creator_id', (int) $adminInfos['contact_id']),
            QueryParameter::string('creator_name', $adminInfos['contact_alias']),
            QueryParameter::int('creation_date', time()),
        ])
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully created default poller token",
    );
};

/** -------------------------------------- Log Actions -------------------------------------- */
$updateLogActionTable = function () use ($pearDBO, &$errorMessage, $version): void {
    $errorMessage = "Unable to update 'log_action' table";

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Updating log_action table",
    );

    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `log_action` MODIFY COLUMN `log_contact_id` int(11) DEFAULT NULL
            SQL
    );
};

try {
    // DDL statements for real time database
    $updateLogActionTable();

    // DDL statements for configuration database
    $addPollerTypeColumn();
    $addPollerUuidColumn();
    $addPollerNameUniqueConstraint();
    $updateAuthenticationTable();

    // SAML recovery for platforms affected by MON-198174
    $fixSamlRequestedAuthnContextComparison();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $createDefaultPollerToken();

    $createBrokerOutputEventScript();
    if ($isMachineACentral()) {
        $insertEventScriptOutputForCMA();
    }

    $clearDefaultCurveTemplateLegend();

    // Command redesign updates
    $addNewCommandPage();
    $updateCommandsParentTopology();
    $moveCommandACLTopologyIntoACLActions();
    $deleteOldCommandsTopologies();

    if ($pearDB->isTransactionActive()) {
        $pearDB->commitTransaction();
    }

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
