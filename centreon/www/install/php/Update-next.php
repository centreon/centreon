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

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/** -------------------------------------- Host Group Topology -------------------------------------- */
$fixDuplicateHostGroupTopology = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to fix duplicate Host Groups topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [topology] Fixing duplicate Host Groups menu entries",
    );

    $pearDB->update(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_url` = '/configuration/hosts/groups',
                `is_react` = '1',
                `topology_show` = '1'
            WHERE `topology_page` = 60102
            SQL
    );

    // Remove duplicate topology entry 60105 introduced by 25.05 migration
    $pearDB->delete(
        <<<'SQL'
            DELETE FROM `topology`
            WHERE `topology_page` = 60105
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [topology] Successfully removed duplicate Host Groups topology entry",
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
    } elseif ($countFields !== 4) {
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
                    QueryParameter::string('description', 'Maximum execution time of the script in seconds'),
                    QueryParameter::string('fieldtype', 'int'),
                    QueryParameter::int('cb_fieldgroup_id', null),
                    QueryParameter::string('external', 'T=options:C=value:CK=key:K=brokercfg_event_script_timeout'),
                ]),
                QueryParameters::create([
                    QueryParameter::string('fieldname', 'managed_event_ttl'),
                    QueryParameter::string('displayname', 'Managed event TTL'),
                    QueryParameter::string('description', 'Minimum delay before a new call to the script can be made in seconds'),
                    QueryParameter::string('fieldtype', 'int'),
                    QueryParameter::int('cb_fieldgroup_id', null),
                    QueryParameter::string('external', 'T=options:C=value:CK=key:K=brokercfg_event_script_managed_event_ttl'),
                ]),
                QueryParameters::create([
                    QueryParameter::string('fieldname', 'event'),
                    QueryParameter::string('displayname', 'Event'),
                    QueryParameter::string('description', 'Filtered event type'),
                    QueryParameter::string('fieldtype', 'select'),
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
            'options', ['key', 'value'],
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
    if ($typeRelationCount === 4) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Required type_field relations for Broker output 'event_script' already exist, skipping creation",
        );
    } elseif ($typeRelationCount !== 0) {
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
    $optionString = "'" . implode("','", $eventOptions) . "'";
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

    $pearDB->batchInsert(
        'cfg_centreonbroker_info',
        ['config_id', 'config_key', 'config_value', 'config_group', 'config_group_id', 'grp_level', 'subgrp_id', 'parent_grp_id'],
        BatchInsertParameters::create([
            QueryParameters::create([
                QueryParameter::int('config_id', 1),
                QueryParameter::string('config_key', 'type'),
                QueryParameter::string('config_value', 'event_script'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', 2),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', 1),
                QueryParameter::string('config_key', 'name'),
                QueryParameter::string('config_value', 'central-broker-master-event-script'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', 2),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', 1),
                QueryParameter::string('config_key', 'blockId'),
                QueryParameter::string('config_value', '1_37'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', 2),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', 1),
                QueryParameter::string('config_key', 'script_path'),
                QueryParameter::string('config_value', '/usr/share/centreon/bin/console agent-configuration:host:create'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', 2),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', 1),
                QueryParameter::string('config_key', 'timeout'),
                QueryParameter::string('config_value', '15'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', 2),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', 1),
                QueryParameter::string('config_key', 'managed_event_ttl'),
                QueryParameter::string('config_value', '3600'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', 2),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', null),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', 1),
                QueryParameter::string('config_key', 'filters'),
                QueryParameter::string('config_value', ''),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', 2),
                QueryParameter::int('grp_level', 0),
                QueryParameter::int('subgrp_id', 1),
                QueryParameter::int('parent_grp_id', null),
            ]),
            QueryParameters::create([
                QueryParameter::int('config_id', 1),
                QueryParameter::string('config_key', 'event'),
                QueryParameter::string('config_value', 'neb:UnknownHost'),
                QueryParameter::string('config_group', 'output'),
                QueryParameter::int('config_group_id', 2),
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

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $fixDuplicateHostGroupTopology();
    $createBrokerOutputEventScript();
    $insertEventScriptOutputForCMA();

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
