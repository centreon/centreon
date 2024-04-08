<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

namespace Migrations;

require_once __DIR__  . '/../../www/class/centreonLog.class.php';
require_once __DIR__ . '/../../www/class/centreonAuth.class.php';

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Core\Security\ProviderConfiguration\Domain\Local\Model\SecurityPolicy;
use Pimple\Container;
use Symfony\Component\Yaml\Yaml;

class Migration000022040000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '22.04.0';

    public function __construct(
        private readonly Container $dependencyInjector,
        private string $centreonEtcPath,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return sprintf(_('Update to %s'), self::VERSION);
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        $pearDB = $this->dependencyInjector['configuration_db'];
        $pearDBO = $this->dependencyInjector['realtime_db'];

        // Update-CSTG-22.04.0-beta.1.sql

        $pearDBO->query(
            <<<'SQL'
                CREATE TABLE `severities` (
                `severity_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `id` bigint(20) unsigned NOT NULL,
                `type` tinyint(4) unsigned NOT NULL COMMENT '0=service, 1=host',
                `name` varchar(255) NOT NULL,
                `level` int(11) unsigned NOT NULL,
                `icon_id` bigint(20) unsigned NOT NULL,
                PRIMARY KEY (`severity_id`),
                UNIQUE KEY `severities_id_type_uindex` (`id`,`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                CREATE TABLE `resources` (
                `resource_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `internal_id` bigint(20) unsigned DEFAULT NULL COMMENT 'id of linked metaservice or business-activity',
                `id` bigint(20) unsigned NOT NULL,
                `parent_id` bigint(20) unsigned NOT NULL,
                `type` tinyint(3) unsigned NOT NULL COMMENT '0=service, 1=host',
                `status` tinyint(3) unsigned DEFAULT NULL COMMENT 'service: 0=OK, 1=WARNING, 2=CRITICAL, 3=UNKNOWN, 4=PENDING\nhost: 0=UP, 1=DOWN, 2=UNREACHABLE, 4=PENDING',
                `status_ordered` tinyint(3) unsigned DEFAULT NULL COMMENT '0=OK=UP\n1=PENDING\n2=UNKNOWN=UNREACHABLE\n3=WARNING\n4=CRITICAL=DOWN',
                `in_downtime` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=false, 1=true',
                `acknowledged` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=false, 1=true',
                `status_confirmed` tinyint(1) DEFAULT NULL COMMENT '0=FALSE=SOFT\n1=TRUE=HARD',
                `check_attempts` tinyint(3) unsigned DEFAULT NULL,
                `max_check_attempts` tinyint(3) unsigned DEFAULT NULL,
                `poller_id` bigint(20) unsigned NOT NULL,
                `severity_id` bigint(20) unsigned DEFAULT NULL,
                `name` varchar(255) DEFAULT NULL,
                `alias` varchar(255) DEFAULT NULL,
                `address` varchar(255) DEFAULT NULL,
                `parent_name` varchar(255) DEFAULT NULL,
                `notes_url` varchar(255) DEFAULT NULL,
                `notes` varchar(255) DEFAULT NULL,
                `action_url` varchar(255) DEFAULT NULL,
                `has_graph` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=false, 1=true',
                `notifications_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=false, 1=true',
                `passive_checks_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=false, 1=true',
                `active_checks_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=false, 1=true',
                `last_check_type` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0=active check, 1=passive check',
                `last_check` bigint(20) unsigned DEFAULT NULL COMMENT 'the last check timestamp',
                `last_status_change` bigint(20) unsigned DEFAULT NULL COMMENT 'the last status change timestamp',
                `output` text DEFAULT NULL,
                `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=resource disabled, 1=resource enabled',
                `icon_id` bigint(20) unsigned DEFAULT NULL,
                PRIMARY KEY (`resource_id`),
                UNIQUE KEY `resources_id_parent_id_type_uindex` (`id`,`parent_id`,`type`),
                KEY `resources_severities_severity_id_fk` (`severity_id`),
                CONSTRAINT `resources_severities_severity_id_fk` FOREIGN KEY (`severity_id`) REFERENCES `severities` (`severity_id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                CREATE TABLE `tags` (
                `tag_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `id` bigint(20) unsigned NOT NULL,
                `type` tinyint(3) unsigned NOT NULL COMMENT '0=servicegroup, 1=hostgroup, 2=servicecategory, 3=hostcategory',
                `name` varchar(255) NOT NULL,
                PRIMARY KEY (`tag_id`),
                UNIQUE KEY `tags_id_type_uindex` (`id`,`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                CREATE TABLE `resources_tags` (
                `tag_id` bigint(20) unsigned NOT NULL,
                `resource_id` bigint(20) unsigned NOT NULL,
                PRIMARY KEY (`tag_id`,`resource_id`),
                KEY `resources_tags_resources_resource_id_fk` (`resource_id`),
                KEY `resources_tags_tag_id_fk` (`tag_id`),
                CONSTRAINT `resources_tags_resources_resource_id_fk` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`resource_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `resources_tags_tag_id_fk` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
        );

        // Update-22.04.0-beta.1.php

        $centreonLog = new \CentreonLog();

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 22.04.0-beta.1: ';

        /**
         * Insert SSO configuration.
         *
         * @param \CentreonDB $pearDB
         */
        $insertWebSSOConfiguration = function (\CentreonDB $pearDB): void
        {
            $customConfiguration = [
                'trusted_client_addresses' => [],
                'blacklist_client_addresses' => [],
                'login_header_attribute' => 'HTTP_AUTH_USER',
                'pattern_matching_login' => null,
                'pattern_replace_login' => null,
            ];
            $isActive = false;
            $isForced = false;
            $statement = $pearDB->query("SELECT * FROM options WHERE `key` LIKE 'sso_%'");
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if (! empty($result)) {
                foreach ($result as $configLine) {
                    switch ($configLine['key']) {
                        case 'sso_enable':
                            $isActive = $configLine['value'] === '1';
                            break;
                        case 'sso_mode':
                            $isForced = $configLine['value'] === '0'; // '0' SSO Only, '1' Mixed
                            break;
                        case 'sso_trusted_clients':
                            $customConfiguration['trusted_client_addresses'] = ! empty($configLine['value'])
                                ? explode(',', $configLine['value'])
                                : [];
                            break;
                        case 'sso_blacklist_clients':
                            $customConfiguration['blacklist_client_addresses'] = ! empty($configLine['value'])
                                ? explode(',', $configLine['value'])
                                : [];
                            break;
                        case 'sso_header_username':
                            $customConfiguration['login_header_attribute'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                        case 'sso_username_pattern':
                            $customConfiguration['pattern_matching_login'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                        case 'sso_username_replace':
                            $customConfiguration['pattern_replace_login'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                    }
                }
                $pearDB->query("DELETE FROM options WHERE `key` LIKE 'sso_%'");
            }
            $insertStatement = $pearDB->prepare(
                "INSERT INTO provider_configuration (`type`,`name`,`custom_configuration`,`is_active`,`is_forced`)
                VALUES ('web-sso','web-sso', :customConfiguration, :isActive, :isForced)"
            );
            $insertStatement->bindValue(':customConfiguration', json_encode($customConfiguration), \PDO::PARAM_STR);
            $insertStatement->bindValue(':isActive', $isActive ? '1' : '0', \PDO::PARAM_STR);
            $insertStatement->bindValue(':isForced', $isForced ? '1' : '0', \PDO::PARAM_STR);
            $insertStatement->execute();
        };

        /**
         * insert OpenId Configuration Default configuration.
         *
         * @param \CentreonDB $pearDB
         */
        $insertOpenIdConfiguration = function (\CentreonDB $pearDB): void
        {
            $customConfiguration = [
                'trusted_client_addresses' => [],
                'blacklist_client_addresses' => [],
                'base_url' => null,
                'authorization_endpoint' => null,
                'token_endpoint' => null,
                'introspection_token_endpoint' => null,
                'userinfo_endpoint' => null,
                'endsession_endpoint' => null,
                'connection_scopes' => [],
                'login_claim' => null,
                'client_id' => null,
                'client_secret' => null,
                'authentication_type' => 'client_secret_post',
                'verify_peer' => true,
            ];
            $isActive = false;
            $isForced = false;
            $statement = $pearDB->query("SELECT * FROM options WHERE `key` LIKE 'openid_%'");
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if (! empty($result)) {
                foreach ($result as $configLine) {
                    switch ($configLine['key']) {
                        case 'openid_connect_enable':
                            $isActive = $configLine['value'] === '1';
                            break;
                        case 'openid_connect_mode':
                            $isForced = $configLine['value'] === '0'; // '0' OpenId Connect Only, '1' Mixed
                            break;
                        case 'openid_connect_trusted_clients':
                            $customConfiguration['trusted_client_addresses'] = ! empty($configLine['value'])
                                ? explode(',', $configLine['value'])
                                : [];
                            break;
                        case 'openid_connect_blacklist_clients':
                            $customConfiguration['blacklist_client_addresses'] = ! empty($configLine['value'])
                                ? explode(',', $configLine['value'])
                                : [];
                            break;
                        case 'openid_connect_base_url':
                            $customConfiguration['base_url'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                        case 'openid_connect_authorization_endpoint':
                            $customConfiguration['authorization_endpoint'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                        case 'openid_connect_token_endpoint':
                            $customConfiguration['token_endpoint'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                        case 'openid_connect_introspection_endpoint':
                            $customConfiguration['introspection_token_endpoint'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                        case 'openid_connect_userinfo_endpoint':
                            $customConfiguration['userinfo_endpoint'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                        case 'openid_connect_end_session_endpoint':
                            $customConfiguration['endsession_endpoint'] = ! empty($configLine['value'])
                                ? $configLine['value']
                                : null;
                            break;
                        case 'openid_connect_scope':
                            $customConfiguration['connection_scopes'] = ! empty($configLine['value'])
                                ? explode(' ', $configLine['value'])
                                : [];
                            break;
                        case 'openid_connect_login_claim':
                            $customConfiguration['login_claim'] = ! empty($configLine['value']) ? $configLine['value'] : null;
                            break;
                        case 'openid_connect_client_id':
                            $customConfiguration['client_id'] = ! empty($configLine['value']) ? $configLine['value'] : null;
                            break;
                        case 'openid_connect_client_secret':
                            $customConfiguration['client_secret'] = ! empty($configLine['value']) ? $configLine['value'] : null;
                            break;
                        case 'openid_connect_client_basic_auth':
                            $customConfiguration['authentication_type'] = $configLine['value'] === '1'
                                ? 'client_secret_basic'
                                : 'client_secret_post';
                            break;
                        case 'openid_connect_verify_peer':
                            // '1' is Verify Peer disable
                            $customConfiguration['verify_peer'] = $configLine['value'] === '1' ? false : true;
                            break;
                    }
                }
                $pearDB->query("DELETE FROM options WHERE `key` LIKE 'open_id%'");
            }
            $insertStatement = $pearDB->prepare(
                "INSERT INTO provider_configuration (`type`,`name`,`custom_configuration`,`is_active`,`is_forced`)
                VALUES ('openid','openid', :customConfiguration, :isActive, :isForced)"
            );
            $insertStatement->bindValue(':customConfiguration', json_encode($customConfiguration), \PDO::PARAM_STR);
            $insertStatement->bindValue(':isActive', $isActive ? '1' : '0', \PDO::PARAM_STR);
            $insertStatement->bindValue(':isForced', $isForced ? '1' : '0', \PDO::PARAM_STR);
            $insertStatement->execute();
        };

        /**
         * Handle new broker output creation 'unified_sql'.
         *
         * @param \CentreonDB $pearDB
         */
        $addNewUnifiedSqlOutput = function (\CentreonDB $pearDB): void
        {
            // Add new output type 'unified_sql'
            $statement = $pearDB->query("SELECT cb_module_id FROM cb_module WHERE name = 'Storage'");
            $module = $statement->fetch();
            if ($module === false) {
                throw new \Exception("Cannot find 'Storage' module in cb_module table");
            }
            $moduleId = $module['cb_module_id'];

            $stmt = $pearDB->prepare(
                "INSERT INTO `cb_type` (`type_name`, `type_shortname`, `cb_module_id`)
                VALUES ('Unified SQL', 'unified_sql', :cb_module_id)"
            );
            $stmt->bindValue(':cb_module_id', $moduleId, \PDO::PARAM_INT);
            $stmt->execute();
            $typeId = $pearDB->lastInsertId();

            // Link new type to tag 'output'
            $statement = $pearDB->query("SELECT cb_tag_id FROM cb_tag WHERE tagname = 'Output'");
            $tag = $statement->fetch();
            if ($tag === false) {
                throw new \Exception("Cannot find 'Output' tag in cb_tag table");
            }
            $tagId = $tag['cb_tag_id'];

            $stmt = $pearDB->prepare(
                'INSERT INTO `cb_tag_type_relation` (`cb_tag_id`, `cb_type_id`, `cb_type_uniq`)
                VALUES (:cb_tag_id, :cb_type_id, 0)'
            );
            $stmt->bindValue(':cb_tag_id', $tagId, \PDO::PARAM_INT);
            $stmt->bindValue(':cb_type_id', $typeId, \PDO::PARAM_INT);
            $stmt->execute();

            // Create new field 'unified_sql_db_type' with fixed value
            $pearDB->query("INSERT INTO options VALUES ('unified_sql_db_type', 'mysql')");

            $pearDB->query(
                "INSERT INTO `cb_field` (fieldname, displayname, description, fieldtype, external)
                VALUES ('db_type', 'DB type', 'Target DBMS.', 'text', 'T=options:C=value:CK=key:K=unified_sql_db_type')"
            );
            $fieldId = $pearDB->lastInsertId();

            // Add form fields for 'unified_sql' output
            $inputs = [];
            $statement = $pearDB->query(
                "SELECT DISTINCT(tfr.cb_field_id), tfr.is_required FROM cb_type_field_relation tfr, cb_type t, cb_field f
                WHERE tfr.cb_type_id = t.cb_type_id
                AND t.type_shortname in ('sql', 'storage')
                AND tfr.cb_field_id = f.cb_field_id
                AND f.fieldname NOT LIKE 'db_type'"
            );
            $inputs = $statement->fetchAll();
            if (empty($inputs)) {
                throw new \Exception('Cannot find fields in cb_type_field_relation table');
            }

            $inputs[] = ['cb_field_id' => $fieldId, 'is_required' => 1];

            $query = 'INSERT INTO `cb_type_field_relation` (`cb_type_id`, `cb_field_id`, `is_required`, `order_display`)';
            $bindedValues = [];
            foreach ($inputs as $key => $input) {
                $query .= $key === 0 ? ' VALUES ' : ', ';
                $query .= "(:cb_type_id_{$key}, :cb_field_id_{$key}, :is_required_{$key}, :order_display_{$key})";

                $bindedValues[':cb_type_id_' . $key] = $typeId;
                $bindedValues[':cb_field_id_' . $key] = $input['cb_field_id'];
                $bindedValues[':is_required_' . $key] = $input['is_required'];
                $bindedValues[':order_display_' . $key] = (int) $key + 1;
            }
            $stmt = $pearDB->prepare($query);
            foreach ($bindedValues as $key => $value) {
                $stmt->bindValue($key, $value, \PDO::PARAM_INT);
            }
            $stmt->execute();
        };

        /**
         * Insert security policy configuration into local provider custom configuration.
         *
         * @param \CentreonDB $pearDB
         */
        $updateSecurityPolicyConfiguration = function (\CentreonDB $pearDB): void
        {
            $localProviderConfiguration = json_encode([
                'password_security_policy' => [
                    'password_length' => 12,
                    'has_uppercase_characters' => true,
                    'has_lowercase_characters' => true,
                    'has_numbers' => true,
                    'has_special_characters' => true,
                    'attempts' => 5,
                    'blocking_duration' => 900,
                    'password_expiration_delay' => 15552000,
                    'delay_before_new_password' => null,
                    'can_reuse_passwords' => false,
                ],
            ]);
            $statement = $pearDB->prepare(
                "UPDATE `provider_configuration`
                SET `custom_configuration` = :localProviderConfiguration
                WHERE `name` = 'local'"
            );
            $statement->bindValue(':localProviderConfiguration', $localProviderConfiguration, \PDO::PARAM_STR);
            $statement->execute();
        };

        /**
         * Migrate broker outputs 'sql' and 'storage' to a unique output 'unified_sql'.
         *
         * @param \CentreonDB $pearDB
         *
         * @throws \Exception
         */
        $migrateBrokerConfigOutputsToUnifiedSql = function (\CentreonDB $pearDB): void
        {
            $outputTag = 1;

            // Determine blockIds for output of type sql and storage
            $dbResult = $pearDB->query("SELECT cb_type_id FROM cb_type WHERE type_shortname IN ('sql', 'storage')");
            $typeIds = $dbResult->fetchAll(\PDO::FETCH_COLUMN, 0);
            if (empty($typeIds) || count($typeIds) !== 2) {
                throw new \Exception("Error while retrieving 'sql' and 'storage' in cb_type table");
            }
            $blockIds = array_map(fn ($typeId) => "{$outputTag}_{$typeId}", $typeIds);

            // Retrieve broker config ids to migrate
            $bindedValues = [];
            foreach ($blockIds as $key => $blockId) {
                $bindedValues[":blockId_{$key}"] = $blockId;
            }
            $stmt = $pearDB->prepare(
                "SELECT config_value, config_id FROM cfg_centreonbroker_info
                WHERE config_group = 'output' AND config_key = 'blockId'
                AND config_value IN (" . implode(', ', array_keys($bindedValues)) . ')'
            );
            foreach ($bindedValues as $param => $value) {
                $stmt->bindValue($param, $value, \PDO::PARAM_STR);
            }
            $stmt->execute();

            $configResults = $stmt->fetchAll(\PDO::FETCH_COLUMN | \PDO::FETCH_GROUP);
            $configIds = array_intersect($configResults[$blockIds[0]], $configResults[$blockIds[1]]);
            if (empty($configIds)) {
                throw new \Exception('Cannot find broker config ids to migrate');
            }

            // Retrieve unified_sql type id
            $dbResult = $pearDB->query("SELECT cb_type_id FROM cb_type WHERE type_shortname = 'unified_sql'");
            $unifiedSqlType = $dbResult->fetch(\PDO::FETCH_COLUMN, 0);
            if (empty($unifiedSqlType)) {
                throw new \Exception("Cannot find 'unified_sql' in cb_type table");
            }
            $unifiedSqlTypeId = (int) $unifiedSqlType;

            foreach ($configIds as $configId) {
                // Find next config group id
                $dbResult = $pearDB->query(
                    "SELECT MAX(config_group_id) as max_config_group_id FROM cfg_centreonbroker_info
                    WHERE config_id = {$configId} AND config_group = 'output'"
                );
                $maxConfigGroupId = $dbResult->fetch(\PDO::FETCH_COLUMN, 0);
                if (empty($maxConfigGroupId)) {
                    throw new \Exception('Cannot find max config group id in cfg_centreonbroker_info table');
                }
                $nextConfigGroupId = (int) $maxConfigGroupId + 1;
                $blockIdsQueryBinds = [];
                foreach ($blockIds as $key => $value) {
                    $blockIdsQueryBinds[':block_id_' . $key] = $value;
                }
                $blockIdBinds = implode(',', array_keys($blockIdsQueryBinds));
                // Find config group ids of outputs to replace
                $grpIdStatement = $pearDB->prepare("SELECT config_group_id FROM cfg_centreonbroker_info
                    WHERE config_id = :configId AND config_key = 'blockId'
                    AND config_value IN ({$blockIdBinds})");
                $grpIdStatement->bindValue(':configId', (int) $configId, \PDO::PARAM_INT);
                foreach ($blockIdsQueryBinds as $key => $value) {
                    $grpIdStatement->bindValue($key, $value, \PDO::PARAM_STR);
                }
                $grpIdStatement->execute();
                $configGroupIds = $grpIdStatement->fetchAll(\PDO::FETCH_COLUMN, 0);
                if (empty($configGroupIds)) {
                    throw new \Exception('Cannot find config group ids in cfg_centreonbroker_info table');
                }

                // Build unified sql output config from outputs to replace
                $unifiedSqlOutput = [];
                $statement = $pearDB->prepare("SELECT * FROM cfg_centreonbroker_info
                        WHERE config_id = :configId AND config_group = 'output' AND config_group_id = :configGroupId");
                foreach ($configGroupIds as $configGroupId) {
                    $statement->bindValue(':configId', (int) $configId, \PDO::PARAM_INT);
                    $statement->bindValue(':configGroupId', (int) $configGroupId, \PDO::PARAM_INT);
                    $statement->execute();
                    while ($row = $statement->fetch()) {
                        $unifiedSqlOutput[$row['config_key']] = array_merge($unifiedSqlOutput[$row['config_key']] ?? [], $row);
                        $unifiedSqlOutput[$row['config_key']]['config_group_id'] = $nextConfigGroupId;
                    }
                }
                if (empty($unifiedSqlOutput)) {
                    throw new \Exception('Cannot find conf for unified sql from cfg_centreonbroker_info table');
                }

                $unifiedSqlOutput['name']['config_value'] = str_replace(
                    ['sql', 'perfdata'],
                    'unified-sql',
                    $unifiedSqlOutput['name']['config_value']
                );
                $unifiedSqlOutput['type']['config_value'] = 'unified_sql';
                $unifiedSqlOutput['blockId']['config_value'] = "{$outputTag}_{$unifiedSqlTypeId}";

                // Insert new output
                $queryRows = [];
                $bindedValues = [];
                $columnNames = null;
                foreach ($unifiedSqlOutput as $configKey => $configInput) {
                    $columnNames ??= implode(', ', array_keys($configInput));

                    $queryKeys = [];
                    foreach ($configInput as $key => $value) {
                        $queryKeys[] = ':' . $configKey . '_' . $key;
                        if (in_array($key, ['config_key', 'config_value', 'config_group'], true)) {
                            $bindedValues[':' . $configKey . '_' . $key] = ['value' => $value, 'type' => \PDO::PARAM_STR];
                        } else {
                            $bindedValues[':' . $configKey . '_' . $key] = ['value' => $value, 'type' => \PDO::PARAM_INT];
                        }
                    }
                    $queryRows[] = '(' . implode(', ', $queryKeys) . ')';
                }

                if ($columnNames !== null) {
                    $query = "INSERT INTO cfg_centreonbroker_info ({$columnNames}) VALUES ";
                    $query .= implode(', ', $queryRows);

                    $stmt = $pearDB->prepare($query);
                    foreach ($bindedValues as $key => $value) {
                        $stmt->bindValue($key, $value['value'], $value['type']);
                    }
                    $stmt->execute();
                }

                // Delete deprecated outputs
                $bindedValues = [];
                foreach ($configGroupIds as $index => $configGroupId) {
                    $bindedValues[':id_' . $index] = $configGroupId;
                }

                $stmt = $pearDB->prepare(
                    "DELETE FROM cfg_centreonbroker_info
                    WHERE config_id = {$configId}
                    AND config_group = 'output'
                    AND config_group_id IN (" . implode(', ', array_keys($bindedValues)) . ')'
                );
                foreach ($bindedValues as $key => $value) {
                    $stmt->bindValue($key, $value, \PDO::PARAM_INT);
                }
                $stmt->execute();
            }
        };

        /**
         * Generate random password
         * 12 characters length with at least 1 uppercase, 1 lowercase, 1 number and 1 special character.
         *
         * @return string
         */
        $generatePassword = function (): string
        {
            $ruleSets = [
                implode('', range('a', 'z')),
                implode('', range('A', 'Z')),
                implode('', range(0, 9)),
                SecurityPolicy::SPECIAL_CHARACTERS_LIST,
            ];
            $allRuleSets = implode('', $ruleSets);
            $passwordLength = 12;

            $password = '';
            foreach ($ruleSets as $ruleSet) {
                $password .= $ruleSet[random_int(0, mb_strlen($ruleSet) - 1)];
            }

            for ($i = 0; $i < ($passwordLength - count($ruleSets)); $i++) {
                $password .= $allRuleSets[random_int(0, mb_strlen($allRuleSets) - 1)];
            }

            return str_shuffle($password);
        };

        /**
         * Get centreon-gorgone api configuration file path if found and readable.
         *
         * @return string|null
         */
        $getGorgoneApiConfigurationFilePath = function (): ?string
        {
            $gorgoneEtcPath = $this->centreonEtcPath . '/../centreon-gorgone';

            $apiConfigurationFile = $gorgoneEtcPath . '/config.d/31-centreon-api.yaml';

            if (file_exists($apiConfigurationFile) && is_readable($apiConfigurationFile)) {
                return $apiConfigurationFile;
            }

            return null;
        };

        /**
         * Create centreon-gorgone user in database.
         *
         * @param \CentreonDB $pearDB
         * @param string $userAlias
         * @param string $hashedPassword
         */
        $createGorgoneUser = function (\CentreonDB $pearDB, string $userAlias, string $hashedPassword): void
        {
            $statementCreateUser = $pearDB->prepare(
                "INSERT INTO `contact`
                (`timeperiod_tp_id`, `timeperiod_tp_id2`, `contact_name`, `contact_alias`,
                `contact_lang`, `contact_host_notification_options`, `contact_service_notification_options`,
                `contact_email`, `contact_pager`, `contact_comment`, `contact_oreon`, `contact_admin`, `contact_type_msg`,
                `contact_activate`, `contact_auth_type`, `contact_ldap_dn`, `contact_enable_notifications`)
                VALUES(1, 1, :gorgoneUser, :gorgoneUser, 'en_US.UTF-8', 'n', 'n', 'gorgone@localhost', NULL, NULL,
                '0', '1', 'txt', '1', 'local', NULL, '0')"
            );
            $statementCreateUser->bindValue(':gorgoneUser', $userAlias, \PDO::PARAM_STR);
            $statementCreateUser->execute();

            $statementCreatePassword = $pearDB->prepare(
                'INSERT INTO `contact_password` (`password`, `contact_id`, `creation_date`)
                SELECT :gorgonePassword, c.contact_id, (SELECT UNIX_TIMESTAMP(NOW()))
                FROM contact c
                WHERE c.contact_alias = :gorgoneUser'
            );
            $statementCreatePassword->bindValue(':gorgoneUser', $userAlias, \PDO::PARAM_STR);
            $statementCreatePassword->bindValue(':gorgonePassword', $hashedPassword, \PDO::PARAM_STR);
            $statementCreatePassword->execute();
        };

        /**
         * Configure api user in centreon gorgone configuration file
         * and create user in database if needed.
         *
         * @param \CentreonDB $pearDB
         */
        $configureGorgoneApiUser = function (\CentreonDB $pearDB) use ($getGorgoneApiConfigurationFilePath, $createGorgoneUser, $generatePassword): void
        {
            $gorgoneUser = null;

            $apiConfigurationFile = $getGorgoneApiConfigurationFilePath();
            if ($apiConfigurationFile !== null && is_writable($apiConfigurationFile)) {
                $apiConfigurationContent = file_get_contents($apiConfigurationFile);
                if (
                    preg_match('/@GORGONE_USER@/', $apiConfigurationContent)
                    && preg_match('/@GORGONE_PASSWORD@/', $apiConfigurationContent)
                ) {
                    $gorgoneUser = 'centreon-gorgone';
                    $gorgonePassword = $generatePassword();
                    file_put_contents(
                        $apiConfigurationFile,
                        str_replace(
                            ['@GORGONE_USER@', '@GORGONE_PASSWORD@'],
                            [$gorgoneUser, $gorgonePassword],
                            $apiConfigurationContent,
                        ),
                    );

                    $createGorgoneUser(
                        $pearDB,
                        $gorgoneUser,
                        password_hash($gorgonePassword, \CentreonAuth::PASSWORD_HASH_ALGORITHM)
                    );
                }
            }
        };

        /**
         * Get centreon-gorgone api user from configuration file.
         *
         * @return string|null
         */
        $getGorgoneApiUser = function () use ($getGorgoneApiConfigurationFilePath): ?string
        {
            $gorgoneUser = null;

            $apiConfigurationFile = $getGorgoneApiConfigurationFilePath();
            if ($apiConfigurationFile !== null) {
                $configuration = Yaml::parseFile($apiConfigurationFile);

                if (isset($configuration['gorgone']['tpapi'][0]['username'])) {
                    $gorgoneUser = $configuration['gorgone']['tpapi'][0]['username'];
                } elseif (isset($configuration['gorgone']['tpapi'][1]['username'])) {
                    $gorgoneUser = $configuration['gorgone']['tpapi'][1]['username'];
                }
            }

            return $gorgoneUser;
        };

        /**
         * Exclude Gorgone / MBI / MAP users from password policy.
         *
         * @param \CentreonDB $pearDB
         */
        $excludeUsersFromPasswordPolicy = function (\CentreonDB $pearDB) use ($getGorgoneApiUser): void
        {
            $usersToExclude = [
                ':bi' => 'CBIS',
                ':map' => 'centreon-map',
            ];

            $gorgoneUser = $getGorgoneApiUser();
            if ($gorgoneUser !== null) {
                $usersToExclude[':gorgone'] = $gorgoneUser;
            }

            $statement = $pearDB->prepare(
                "INSERT INTO `password_expiration_excluded_users` (provider_configuration_id, user_id)
                SELECT pc.id, c.contact_id
                FROM `provider_configuration` pc, `contact` c
                WHERE pc.name = 'local'
                AND c.contact_alias IN (" . implode(',', array_keys($usersToExclude)) . ')
                GROUP BY pc.id, c.contact_id
                ON DUPLICATE KEY UPDATE provider_configuration_id = provider_configuration_id'
            );

            foreach ($usersToExclude as $userToExcludeParam => $usersToExcludeValue) {
                $statement->bindValue($userToExcludeParam, $usersToExcludeValue, \PDO::PARAM_STR);
            }

            $statement->execute();
        };

        try {
            /**
             * Create Tables.
             */
            $errorMessage = "Unable to create 'password_expiration_excluded_users' table";
            $pearDB->query(
                'CREATE TABLE IF NOT EXISTS `password_expiration_excluded_users` (
                `provider_configuration_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                PRIMARY KEY (`provider_configuration_id`, `user_id`),
                CONSTRAINT `password_expiration_excluded_users_provider_configuration_id_fk`
                FOREIGN KEY (`provider_configuration_id`)
                REFERENCES `provider_configuration` (`id`) ON DELETE CASCADE,
                CONSTRAINT `password_expiration_excluded_users_provider_user_id_fk`
                FOREIGN KEY (`user_id`)
                REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );

            $errorMessage = "Unable to create table 'contact_password'";
            $pearDB->query(
                'CREATE TABLE IF NOT EXISTS `contact_password` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `password` varchar(255) NOT NULL,
                `contact_id` int(11) NOT NULL,
                `creation_date` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                KEY `contact_password_contact_id_fk` (`contact_id`),
                INDEX `creation_date_index` (`creation_date`),
                CONSTRAINT `contact_password_contact_id_fk` FOREIGN KEY (`contact_id`)
                REFERENCES `contact` (`contact_id`) ON DELETE CASCADE)'
            );

            /**
             * Alter Tables.
             */
            if (
                $pearDB->isColumnExist('contact', 'login_attempts') !== 1
                && $pearDB->isColumnExist('contact', 'blocking_time') !== 1
            ) {
                // Add login blocking mechanism to contact
                $errorMessage = 'Impossible to add "login_attempts" and "blocking_time" columns to "contact" table';
                $pearDB->query(
                    'ALTER TABLE `contact`
                    ADD `login_attempts` INT(11) UNSIGNED DEFAULT NULL,
                    ADD `blocking_time` BIGINT(20) UNSIGNED DEFAULT NULL'
                );
            }

            $errorMessage = 'Unable to find constraint unique_index from security_token';
            $constraintExistStatement = $pearDB->query(
                'SELECT CONSTRAINT_NAME from INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_NAME="security_token" AND CONSTRAINT_NAME="unique_token"'
            );
            if ($constraintExistStatement->fetch() !== false) {
                $errorMessage = 'Unable to remove unique_index from security_token';
                $pearDB->query('ALTER TABLE `security_token` DROP INDEX `unique_token`');
            }

            $errorMessage = 'Unable to find key token_index from security_token';
            $tokenIndexKeyExistsStatement = $pearDB->query(
                <<<'SQL'
                    SHOW indexes
                        FROM security_token
                        WHERE Key_name='token_index'
                    SQL
            );
            if ($tokenIndexKeyExistsStatement->fetch() !== false) {
                $errorMessage = 'Unable to remove key token_index from security_token';
                $pearDB->query(
                    <<<'SQL'
                        DROP INDEX token_index
                            ON security_token
                        SQL
                );
            }

            $errorMessage = 'Unable to alter table security_token';
            $pearDB->query('ALTER TABLE `security_token` MODIFY `token` varchar(4096)');

            if ($pearDB->isColumnExist('provider_configuration', 'custom_configuration') !== 1) {
                // Add custom_configuration to provider configurations
                $errorMessage = "Unable to add column 'custom_configuration' to table 'provider_configuration'";
                $pearDB->query(
                    'ALTER TABLE `provider_configuration` ADD COLUMN `custom_configuration` JSON NOT NULL AFTER `name`'
                );
            }

            /**
             * Transactional queries.
             */
            $pearDB->beginTransaction();

            $errorMessage = "Unable to select existing passwords from 'contact' table";
            if ($pearDB->isColumnExist('contact', 'contact_passwd') === 1) {
                $getPasswordResult = $pearDB->query(
                    'SELECT `contact_id`, `contact_passwd` FROM `contact` WHERE `contact_passwd` IS NOT NULL'
                );

                // Move old password from contact to contact_password
                $errorMessage = "Unable to insert password in 'contact_password' table";
                $statement = $pearDB->prepare(
                    'INSERT INTO `contact_password` (`password`, `contact_id`, `creation_date`)
                    VALUES (:password, :contactId, :creationDate)'
                );
                while ($row = $getPasswordResult->fetch()) {
                    $statement->bindValue(':password', $row['contact_passwd'], \PDO::PARAM_STR);
                    $statement->bindValue(':contactId', $row['contact_id'], \PDO::PARAM_INT);
                    $statement->bindValue(':creationDate', time(), \PDO::PARAM_INT);
                    $statement->execute();
                }
            }

            // Insert default providers configurations
            $errorMessage = 'Impossible to add default OpenID provider configuration';
            $insertOpenIdConfiguration($pearDB);
            $errorMessage = 'Impossible to add default WebSSO provider configuration';
            $insertWebSSOConfiguration($pearDB);
            $errorMessage = 'Unable to insert default local security policy configuration';
            $updateSecurityPolicyConfiguration($pearDB);

            /**
             * Add new UnifiedSQl broker output.
             */
            $errorMessage = 'Unable to update cb_type table ';
            $pearDB->query(
                "UPDATE `cb_type` set type_name = 'Perfdata Generator (Centreon Storage) - DEPRECATED'
                WHERE type_shortname = 'storage'"
            );
            $pearDB->query(
                "UPDATE `cb_type` set type_name = 'Broker SQL database - DEPRECATED'
                WHERE type_shortname = 'sql'"
            );

            $errorMessage = "Unable to add 'unified_sql' broker configuration output";
            $addNewUnifiedSqlOutput($pearDB);
            $errorMessage = 'Unable to migrate broker config to unified_sql';
            $migrateBrokerConfigOutputsToUnifiedSql($pearDB);

            $errorMessage = 'Unable to configure centreon-gorgone api user';
            $configureGorgoneApiUser($pearDB);

            $errorMessage = 'Unable to exclude Gorgone / MBI / MAP users from password policy';
            $excludeUsersFromPasswordPolicy($pearDB);

            $pearDB->commit();
            if ($pearDB->isColumnExist('contact', 'contact_passwd') === 1) {
                $errorMessage = "Unable to drop column 'contact_passwd' from 'contact' table";
                $pearDB->query('ALTER TABLE `contact` DROP COLUMN `contact_passwd`');
            }
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

        // Update-DB-22.04.0-beta.1.sql

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_group`, `topology_order`)
                VALUES ('Authentication', '/administration/authentication', '1', '1', 5, 509, 1, 10)
                SQL
        );

        // Update-22.04.0-beta.2.php

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 22.04.0-beta.2: ';
        $errorMessage = '';

        try {
            // Centengine logger v2
            if (
                $pearDB->isColumnExist('cfg_nagios', 'log_archive_path') === 1
                && $pearDB->isColumnExist('cfg_nagios', 'log_rotation_method') === 1
                && $pearDB->isColumnExist('cfg_nagios', 'daemon_dumps_core') === 1
            ) {
                $errorMessage = 'Unable to remove log_archive_path,log_rotation_method,daemon_dumps_core from cfg_nagios table';
                $pearDB->query(
                    'ALTER TABLE `cfg_nagios`
                    DROP COLUMN `log_archive_path`,
                    DROP COLUMN `log_rotation_method`,
                    DROP COLUMN `daemon_dumps_core`'
                );
            }
            if ($pearDB->isColumnExist('cfg_nagios', 'logger_version') !== 1) {
                $errorMessage = 'Unable to add logger_version to cfg_nagios table';
                $pearDB->query(
                    "ALTER TABLE `cfg_nagios`
                    ADD COLUMN `logger_version` enum('log_v2_enabled', 'log_legacy_enabled') DEFAULT 'log_v2_enabled'"
                );
            }

            // Add contact_theme column to contact table
            if ($pearDB->isColumnExist('contact', 'contact_theme') !== 1) {
                $errorMessage = "Unable to add column 'contact_theme' to table 'contact'";
                $pearDB->query(
                    'ALTER TABLE `contact` ADD COLUMN '
                    . "`contact_theme` enum('light','dark') DEFAULT 'light' AFTER `contact_js_effects`"
                );
            }

            if ($pearDB->isColumnExist('cfg_centreonbroker', 'bbdo_version') !== 1) {
                $errorMessage = "Unable to add 'bbdo_version' column to 'cfg_centreonbroker' table";
                $pearDB->query('ALTER TABLE `cfg_centreonbroker` ADD `bbdo_version` VARCHAR(50) DEFAULT "3.0.0"');
            }

            $errorMessage = 'Unable to update logger_version from cfg_nagios table';
            $pearDB->query(
                "UPDATE `cfg_nagios` set logger_version = 'log_legacy_enabled'"
            );
        } catch (\Exception $e) {
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage
                . ' - Code : ' . (int) $e->getCode()
                . ' - Error : ' . $e->getMessage()
                . ' - Trace : ' . $e->getTraceAsString()
            );

            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
        }

        // Update-DB-22.04.0-beta.2.sql

        $pearDB->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `cfg_nagios_logger` (
                    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `cfg_nagios_id` int(11) NOT NULL,
                    `log_v2_logger` enum('file', 'syslog') DEFAULT 'file',
                    `log_level_functions` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err',
                    `log_level_config` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'info',
                    `log_level_events` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'info',
                    `log_level_checks` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'info',
                    `log_level_notifications` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err',
                    `log_level_eventbroker` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err',
                    `log_level_external_command` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'info',
                    `log_level_commands` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err',
                    `log_level_downtimes` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err',
                    `log_level_comments` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err',
                    `log_level_macros` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err',
                    `log_level_process` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'info',
                    `log_level_runtime` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err',
                    PRIMARY KEY (`id`),
                    CONSTRAINT `cfg_nagios_logger_cfg_nagios_id_fk`
                        FOREIGN KEY (`cfg_nagios_id`)
                        REFERENCES `cfg_nagios` (`nagios_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
