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

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Pimple\Container;

class Migration000019100000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '19.10.0';

    public function __construct(
        private readonly Container $dependencyInjector,
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

        $centreonLog = new \CentreonLog();


        /* Update-19.10.0-beta.1.php */

        // update topology of poller wizard to display breadcrumb
        $pearDB->query(
            'UPDATE topology
            SET topology_parent = 60901,
            topology_page = 60959,
            topology_group = 1,
            topology_show = "0"
            WHERE topology_url LIKE "/poller-wizard/%"'
        );

        try {
            $pearDB->query('SET SESSION innodb_strict_mode=OFF');
            // Add trap regexp matching
            if (!$pearDB->isColumnExist('traps', 'traps_mode')) {
                $pearDB->query(
                    "ALTER TABLE `traps` ADD COLUMN `traps_mode` enum('0','1') DEFAULT '0' AFTER `traps_oid`"
                );
            }
        } catch (\PDOException $e) {
            $centreonLog->insertLog(
                2,
                "UPGRADE : 19.10.0-beta.1 Unable to modify regexp matching in the database"
            );

            throw $e;
        } finally {
            $pearDB->query('SET SESSION innodb_strict_mode=ON');
        }


        /* Update-DB-19.10.0-beta.1.sql */

        // Drop useless ID columns
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_group_actions_relations`
                DROP COLUMN `agar_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_group_contactgroups_relations`
                DROP COLUMN `agcgr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_group_contacts_relations`
                DROP COLUMN `agcr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_group_topology_relations`
                DROP COLUMN `agt_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_res_group_relations`
                DROP COLUMN `argr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_hc_relations`
                DROP COLUMN `arhcr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_hg_relations`
                DROP COLUMN `arhge_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_host_relations`
                DROP COLUMN `arhr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_hostex_relations`
                DROP COLUMN `arhe_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_meta_relations`
                DROP COLUMN `armse_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_poller_relations`
                DROP COLUMN `arpr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_sc_relations`
                DROP COLUMN `arscr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_service_relations`
                DROP COLUMN `arsr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_resources_sg_relations`
                DROP COLUMN `asgr`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_topology_relations`
                DROP COLUMN `agt_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `command_categories_relation`
                DROP COLUMN `cmd_cat_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contact_host_relation`
                DROP COLUMN `chr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contact_hostcommands_relation`
                DROP COLUMN `chr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contact_service_relation`
                DROP COLUMN `csr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contact_servicecommands_relation`
                DROP COLUMN `csc_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contactgroup_contact_relation`
                DROP COLUMN `cgr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contactgroup_host_relation`
                DROP COLUMN `cghr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contactgroup_hostgroup_relation`
                DROP COLUMN `cghgr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contactgroup_service_relation`
                DROP COLUMN `cgsr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contactgroup_servicegroup_relation`
                DROP COLUMN `cgsgr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_hostChild_relation`
                DROP COLUMN `dhcr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_hostParent_relation`
                DROP COLUMN `dhpr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_hostgroupChild_relation`
                DROP COLUMN `dhgcr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_hostgroupParent_relation`
                DROP COLUMN `dhgpr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_metaserviceChild_relation`
                DROP COLUMN `dmscr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_metaserviceParent_relation`
                DROP COLUMN `dmspr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_serviceChild_relation`
                DROP COLUMN `dscr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_serviceParent_relation`
                DROP COLUMN `dspr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_servicegroupChild_relation`
                DROP COLUMN `dsgcr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `dependency_servicegroupParent_relation`
                DROP COLUMN `dsgpr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `escalation_contactgroup_relation`
                DROP COLUMN `ecgr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `escalation_host_relation`
                DROP COLUMN `ehr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `escalation_hostgroup_relation`
                DROP COLUMN `ehgr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `escalation_meta_service_relation`
                DROP COLUMN `emsr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `escalation_service_relation`
                DROP COLUMN `esr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `escalation_servicegroup_relation`
                DROP COLUMN `esgr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `host_hostparent_relation`
                DROP COLUMN `hhr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `hostcategories_relation`
                DROP COLUMN `hcr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `hostgroup_hg_relation`
                DROP COLUMN `hgr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `meta_contactgroup_relation`
                DROP COLUMN `mcr_id`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `traps_service_relation`
                DROP COLUMN `tsr_id`
                SQL
        );

        // Alter existing tables to conform with strict mode.
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_groups`
                MODIFY COLUMN `acl_group_changed` int(11) NOT NULL DEFAULT 1
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `widget_models`
                MODIFY COLUMN `description` TEXT NOT NULL
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `auth_ressource`
                ALTER `ar_type` SET DEFAULT 'ldap'
                SQL
        );

        // Remove modules *_files flags.
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `modules_informations`
                DROP COLUMN `lang_files`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `modules_informations`
                DROP COLUMN `sql_files`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `modules_informations`
                DROP COLUMN `php_files`
                SQL
        );

        // Change IP field from varchar(16) to varchar(255)
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `remote_servers`
                MODIFY COLUMN `ip` VARCHAR(255) NOT NULL
                SQL
        );

        // Improve chart performance
        $pearDB->query(
            <<<'SQL'
                TRUNCATE TABLE ods_view_details
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE ods_view_details MODIFY metric_id int(11)
                SQL
        );

        // Add trap filter
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `traps` MODIFY COLUMN `traps_exec_interval_type` ENUM('0','1','2','3') NULL DEFAULT '0'
                SQL
        );


        /* Update-19.10.0-beta.1.post.php */

        try {
            // Alter existing tables to conform with strict mode.
            $pearDBO->query(
                "ALTER TABLE `log_action_modification` MODIFY COLUMN `field_value` text NOT NULL"
            );
            // Add the audit log retention column for the retention options menu
            if (!$pearDBO->isColumnExist('config', 'audit_log_retention')) {
                $pearDBO->query(
                    "ALTER TABLE `config` ADD COLUMN audit_log_retention int(11) DEFAULT 0"
                );
            }
        } catch (\PDOException $e) {
            $centreonLog->insertLog(
                2,
                "UPGRADE : Unable to process 19.10.0-post-beta 1 upgrade"
            );

            throw $e;
        }


        /* Update-19.10.0-beta.3.php */

        /**
         * LDAP auto or manual synchronization feature
         */
        try {
            $pearDB->query('SET SESSION innodb_strict_mode=OFF');

            // Adding two columns to check last user's LDAP sync timestamp
            if (!$pearDB->isColumnExist('contact', 'contact_ldap_last_sync')) {
                //$pearDB = "centreon"
                //$pearDBO = "realtime"
                $pearDB->query(
                    "ALTER TABLE `contact` ADD COLUMN `contact_ldap_last_sync` INT(11) NOT NULL DEFAULT 0"
                );
            }
            if (!$pearDB->isColumnExist('contact', 'contact_ldap_required_sync')) {
                $pearDB->query(
                    "ALTER TABLE `contact` ADD COLUMN `contact_ldap_required_sync` enum('0','1') NOT NULL DEFAULT '0'"
                );
            }

            // Adding a column to check last specific LDAP sync timestamp
            $needToUpdateValues = false;
            if (!$pearDB->isColumnExist('auth_ressource', 'ar_sync_base_date')) {
                $pearDB->query(
                    "ALTER TABLE `auth_ressource` ADD COLUMN `ar_sync_base_date` INT(11) DEFAULT 0"
                );
                $needToUpdateValues = true;
            }
        } catch (\PDOException $e) {
            $centreonLog->insertLog(
                2,
                "UPGRADE : 19.10.0-beta.3 Unable to add LDAP new feature's tables in the database"
            );

            throw $e;
        } finally {
            $pearDB->query('SET SESSION innodb_strict_mode=ON');
        }

        // Initializing reference synchronization time for all LDAP configurations */
        if ($needToUpdateValues) {
            try {
                $stmt = $pearDB->prepare(
                    "UPDATE `auth_ressource` SET `ar_sync_base_date` = :minusTime"
                );
                $stmt->bindValue(':minusTime', time(), \PDO::PARAM_INT);
                $stmt->execute();
            } catch (\PDOException $e) {
                $centreonLog->insertLog(
                    2,
                    "UPGRADE : 19.10.0-beta.3 Unable to initialize LDAP reference date"
                );

                throw $e;
            }

            /* Adding to each LDAP configuration two new fields */
            try {
                // field to enable the automatic sync at login
                $addSyncStateField = $pearDB->prepare(
                    "INSERT IGNORE INTO auth_ressource_info
                    (`ar_id`, `ari_name`, `ari_value`)
                    VALUES (:arId, 'ldap_auto_sync', '1')"
                );
                // interval between two sync at login
                $addSyncIntervalField = $pearDB->prepare(
                    "INSERT IGNORE INTO auth_ressource_info
                    (`ar_id`, `ari_name`, `ari_value`)
                    VALUES (:arId, 'ldap_sync_interval', '1')"
                );

                $pearDB->beginTransaction();
                $stmt = $pearDB->query("SELECT DISTINCT(ar_id) FROM auth_ressource");
                while ($row = $stmt->fetch()) {
                    $addSyncIntervalField->bindValue(':arId', $row['ar_id'], \PDO::PARAM_INT);
                    $addSyncIntervalField->execute();
                    $addSyncStateField->bindValue(':arId', $row['ar_id'], \PDO::PARAM_INT);
                    $addSyncStateField->execute();
                }
                $pearDB->commit();
            } catch (\PDOException $e) {
                $centreonLog->insertLog(
                    1, // ldap.log
                    "UPGRADE PROCESS : Error - Please open your LDAP configuration and save manually each LDAP form"
                );
                $centreonLog->insertLog(
                    2, // sql-error.log
                    "UPGRADE : 19.10.0-beta.3 Unable to add LDAP new fields"
                );
                $pearDB->rollBack();

                throw $e;
            }
        }

        // update topology of poller wizard to display breadcrumb
        $pearDB->query(
            'UPDATE topology
            SET topology_parent = 60901,
            topology_page = 60959,
            topology_group = 1,
            topology_show = "0"
            WHERE topology_url LIKE "/poller-wizard/%"'
        );


        try {
            // Add trap regexp matching
            if (!$pearDB->isColumnExist('traps', 'traps_mode')) {
                $pearDB->query('SET SESSION innodb_strict_mode=OFF');
                $pearDB->query(
                    "ALTER TABLE `traps` ADD COLUMN `traps_mode` enum('0','1') DEFAULT '0' AFTER `traps_oid`"
                );
            }
        } catch (\PDOException $e) {
            $centreonLog->insertLog(
                2,
                "UPGRADE : 19.10.0-beta.3 Unable to modify regexp matching in the database"
            );

            throw $e;
        } finally {
            $pearDB->query('SET SESSION innodb_strict_mode=ON');
        }


        /**
         * Add columns to manage engine & broker restart/reload process
         */
        try {
            $pearDB->query('SET SESSION innodb_strict_mode=OFF');
            $pearDB->query('
                ALTER TABLE `nagios_server`
                ADD COLUMN `engine_start_command` varchar(255) DEFAULT \'service centengine start\' AFTER `monitoring_engine`
            ');
            $pearDB->query('
                ALTER TABLE `nagios_server`
                ADD COLUMN `engine_stop_command` varchar(255) DEFAULT \'service centengine stop\' AFTER `engine_start_command`
            ');
            $pearDB->query('
                ALTER TABLE `nagios_server`
                ADD COLUMN `engine_restart_command` varchar(255)
                DEFAULT \'service centengine restart\' AFTER `engine_stop_command`
            ');
            $pearDB->query('
                ALTER TABLE `nagios_server`
                ADD COLUMN `engine_reload_command` varchar(255)
                DEFAULT \'service centengine reload\' AFTER `engine_restart_command`
            ');
            $pearDB->query('
                ALTER TABLE `nagios_server`
                ADD COLUMN `broker_reload_command` varchar(255) DEFAULT \'service cbd reload\' AFTER `nagios_perfdata`
            ');
        } catch (\PDOException $e) {
            $centreonLog->insertLog(
                2,
                "UPGRADE : 19.10.0-beta.3 Unable to manage engine & broker restart and reload processes"
            );

            throw $e;
        } finally {
            $pearDB->query('SET SESSION innodb_strict_mode=ON');
        }

        $stmt = $pearDB->prepare('
            UPDATE `nagios_server`
            SET engine_start_command = :engine_start_command,
            engine_stop_command = :engine_stop_command,
            engine_restart_command = :engine_restart_command,
            engine_reload_command = :engine_reload_command,
            broker_reload_command = :broker_reload_command
            WHERE id = :id
        ');

        $result = $pearDB->query('SELECT value FROM `options` WHERE `key` = \'broker_correlator_script\'');
        $brokerServiceName = 'cbd';
        if ($row = $result->fetch()) {
            if (!empty($row['value'])) {
                $brokerServiceName = $row['value'];
            }
        }
        $stmt->bindValue(':broker_reload_command', 'service ' . $brokerServiceName . ' reload', \PDO::PARAM_STR);

        $result = $pearDB->query('SELECT id, init_script FROM `nagios_server`');

        while ($row = $result->fetch()) {
            $engineServiceName = 'centengine';
            if (!empty($row['init_script'])) {
                $engineServiceName = $row['init_script'];
            }
            $stmt->bindValue(':id', $row['id'], \PDO::PARAM_INT);
            $stmt->bindValue(':engine_start_command', 'service ' . $engineServiceName . ' start', \PDO::PARAM_STR);
            $stmt->bindValue(':engine_stop_command', 'service ' . $engineServiceName . ' stop', \PDO::PARAM_STR);
            $stmt->bindValue(':engine_restart_command', 'service ' . $engineServiceName . ' restart', \PDO::PARAM_STR);
            $stmt->bindValue(':engine_reload_command', 'service ' . $engineServiceName . ' reload', \PDO::PARAM_STR);
            $stmt->execute();
        }

        // Remove deprecated engine & broker init script paths
        $pearDB->query('ALTER TABLE `nagios_server` DROP COLUMN `init_script`');
        $pearDB->query('ALTER TABLE `nagios_server` DROP COLUMN `init_system`');
        $pearDB->query('ALTER TABLE `nagios_server` DROP COLUMN `monitoring_engine`');
        $pearDB->query('DELETE FROM `options` WHERE `key` = \'broker_correlator_script\'');
        $pearDB->query('DELETE FROM `options` WHERE `key` = \'monitoring_engine\'');


        /**
         * Manage upgrade of widget preferences
         */

        // set cache for pollers
        $pollers = [];
        $result = $pearDB->query('SELECT id, name FROM nagios_server');
        while ($row = $result->fetch()) {
            $pollerName = strtolower($row['name']);
            $pollers[$pollerName] = $row['id'];
        }

        // get poller preferences of engine-status widget
        $result = $pearDB->query(
            'SELECT wpr.widget_view_id, wpr.parameter_id, wpr.preference_value, wpr.user_id
            FROM widget_preferences wpr
            INNER JOIN widget_parameters wpa ON wpa.parameter_id = wpr.parameter_id
            AND wpa.parameter_code_name = \'poller\'
            INNER JOIN widget_models wm ON wm.widget_model_id = wpa.widget_model_id
            AND wm.title = \'Engine-Status\''
        );

        $statement = $pearDB->prepare(
            'UPDATE widget_preferences
            SET preference_value= :value
            WHERE widget_view_id = :view_id
            AND parameter_id = :parameter_id
            AND user_id = :user_id'
        );

        // update poller preferences from name to id
        while ($row = $result->fetch()) {
            $pollerName = strtolower($row['preference_value']);
            $pollerId = isset($pollers[$pollerName])
                ? $pollers[$pollerName]
                : '';

            $statement->bindValue(':value', $pollerId, \PDO::PARAM_STR);
            $statement->bindValue(':view_id', $row['widget_view_id'], \PDO::PARAM_INT);
            $statement->bindValue(':parameter_id', $row['parameter_id'], \PDO::PARAM_INT);
            $statement->bindValue(':user_id', $row['user_id'], \PDO::PARAM_INT);
            $statement->execute();
        }

        // set cache for severities
        $severities = [];
        $result = $pearDB->query('SELECT sc_id, sc_name FROM service_categories WHERE level IS NOT NULL');
        while ($row = $result->fetch()) {
            $severityName = strtolower($row['sc_name']);
            $severities[$severityName] = $row['sc_id'];
        }

        // get poller preferences (criticality_filter) of service-monitoring widget
        $result = $pearDB->query(
            'SELECT wpr.widget_view_id, wpr.parameter_id, wpr.preference_value, wpr.user_id
            FROM widget_preferences wpr
            INNER JOIN widget_parameters wpa ON wpa.parameter_id = wpr.parameter_id
            AND wpa.parameter_code_name = \'criticality_filter\'
            INNER JOIN widget_models wm ON wm.widget_model_id = wpa.widget_model_id
            AND wm.title = \'Service Monitoring\''
        );

        $statement = $pearDB->prepare(
            'UPDATE widget_preferences
            SET preference_value= :value
            WHERE widget_view_id = :view_id
            AND parameter_id = :parameter_id
            AND user_id = :user_id'
        );

        // update poller preferences from name to id
        while ($row = $result->fetch()) {
            $severityIds = [];
            $severityNames = explode(',', $row['preference_value']);
            foreach ($severityNames as $severityName) {
                $severityName = strtolower($severityName);
                if (isset($severities[$severityName])) {
                    $severityIds[] = $severities[$severityName];
                }
            }

            $severityIds = !empty($severityIds) ? implode(',', $severityIds) : '';

            $statement->bindValue(':value', $severityIds, \PDO::PARAM_STR);
            $statement->bindValue(':view_id', $row['widget_view_id'], \PDO::PARAM_INT);
            $statement->bindValue(':parameter_id', $row['parameter_id'], \PDO::PARAM_INT);
            $statement->bindValue(':user_id', $row['user_id'], \PDO::PARAM_INT);
            $statement->execute();
        }

        // manage rrdcached upgrade
        $result = $pearDB->query("SELECT `value` FROM options WHERE `key` = 'rrdcached_enable' ");
        $cache = $result->fetch();

        if ($cache['value']) {
            try {
                $pearDB->beginTransaction();

                $res = $pearDB->query(
                    "SELECT * FROM cfg_centreonbroker_info WHERE `config_key` = 'type' AND `config_value` = 'rrd'"
                );
                $result = $pearDB->query("SELECT `value` FROM options WHERE `key` = 'rrdcached_port' ");
                $port = $result->fetch();

                while ($row = $res->fetch()) {
                    if ($port['value']) {
                        $brokerInfoData = [
                            [
                                'config_id' => $row['config_id'],
                                'config_key' => 'rrd_cached_option',
                                'config_value' => 'tcp',
                                'config_group' => $row['config_group'],
                                'config_group_id' => $row['config_group_id']
                            ],
                            [
                                'config_id' => $row['config_id'],
                                'config_key' => 'rrd_cached',
                                'config_value' => $port['value'],
                                'config_group' => $row['config_group'],
                                'config_group_id' => $row['config_group_id']
                            ],
                        ];
                        $query = 'INSERT INTO cfg_centreonbroker_info (config_id, config_key, config_value, '
                            . 'config_group, config_group_id ) VALUES '
                            . '( :config_id, :config_key, :config_value, '
                            . ':config_group, :config_group_id)';
                        $statement = $pearDB->prepare($query);
                        foreach ($brokerInfoData as $dataRow) {
                            $statement->bindValue(":config_id", (int) $dataRow['config_id'], \PDO::PARAM_INT);
                            $statement->bindValue(":config_key", $dataRow['config_key']);
                            $statement->bindValue(":config_value", $dataRow['config_value']);
                            $statement->bindValue(":config_group", $dataRow['config_group']);
                            $statement->bindValue(":config_group_id", (int) $dataRow['config_group_id'], \PDO::PARAM_INT);
                            $statement->execute();
                        }
                    } else {
                        $result = $pearDB->query("SELECT `value` FROM options WHERE `key` = 'rrdcached_unix_path' ");
                        $path = $result->fetch();

                        $brokerInfoData = [
                            [
                                'config_id' => $row['config_id'],
                                'config_key' => 'rrd_cached_option',
                                'config_value' => 'unix',
                                'config_group' => $row['config_group'],
                                'config_group_id' => $row['config_group_id']
                            ],
                            [
                                'config_id' => $row['config_id'],
                                'config_key' => 'rrd_cached',
                                'config_value' => $path['value'],
                                'config_group' => $row['config_group'],
                                'config_group_id' => $row['config_group_id']
                            ],
                        ];
                        $query = 'INSERT INTO cfg_centreonbroker_info (config_id, config_key, config_value, '
                            . 'config_group, config_group_id ) VALUES '
                            . '( :config_id, :config_key, :config_value, '
                            . ':config_group, :config_group_id)';
                        $statement = $pearDB->prepare($query);
                        foreach ($brokerInfoData as $rowData) {
                            $statement->bindValue(':config_id', (int) $rowData['config_id'], \PDO::PARAM_INT);
                            $statement->bindValue(':config_key', $rowData['config_key']);
                            $statement->bindValue(':config_value', $rowData['config_value']);
                            $statement->bindValue(':config_group', $rowData['config_group']);
                            $statement->bindValue(':config_group_id', (int) $rowData['config_group_id'], \PDO::PARAM_INT);
                            $statement->execute();
                        }
                    }

                    $statement = $pearDB->prepare(
                        "DELETE FROM cfg_centreonbroker_info WHERE `config_id` = :config_id"
                        . " AND config_group_id = :config_group_id"
                        . " AND config_group = 'output' AND ( config_key = 'port' OR config_key = 'path') "
                    );
                    $statement->bindValue(':config_id', (int) $row['config_id'], \PDO::PARAM_INT);
                    $statement->bindValue(':config_group_id', (int) $row['config_group_id'], \PDO::PARAM_INT);
                    $statement->execute();
                }
                $pearDB->query(
                    "DELETE FROM options WHERE `key` = 'rrdcached_enable'
                        OR `key` = 'rrdcached_port' OR `key` = 'rrdcached_unix_path'"
                );
                $pearDB->commit();
            } catch (\PDOException $e) {
                $centreonLog->insertLog(
                    2, // sql-error.log
                    "UPGRADE : 19.10.0-beta.3 Unable to move rrd global cache option on broker form"
                );
                $pearDB->rollBack();

                throw $e;
            }
        }

        /* Update-DB-19.10.0-beta.3.sql */

        // Add new field for Remote Server option
        $pearDB->query(
            <<<'SQL'
                SET SESSION innodb_strict_mode=OFF
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE nagios_server
                ADD COLUMN `remote_server_centcore_ssh_proxy` enum('0','1') NOT NULL DEFAULT '1'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                SET SESSION innodb_strict_mode=ON
                SQL
        );

        // Add severity preference on host-monitoring and service-monitoring widgets
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `widget_parameters_field_type` (`ft_typename`, `is_connector`)
                VALUES
                ('hostSeverityMulti', 1),
                ('serviceSeverityMulti', 1)
                SQL
        );

        // Update broker form
        $pearDB->query(
            <<<'SQL'
                UPDATE `cb_field`
                SET
                    `fieldname` = 'rrd_cached_option',
                    `displayname` = 'Enable RRDCached',
                    `description` = 'Enable rrdcached option for Centreon, please see Centreon documentation to configure it.',
                    `fieldtype` = 'radio',
                    `external` = NULL
                WHERE `fieldname` = 'path'
                AND `displayname` = 'Unix socket'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `cb_field`
                SET
                    `fieldname` = 'rrd_cached',
                    `displayname` = 'RRDCacheD listening socket/port',
                    `description` = 'The absolute path to unix socket or TCP port for communicating with rrdcached daemon.',
                    `fieldtype` = 'text',
                    `external` = NULL
                    WHERE `fieldname` = 'port'
                    AND `displayname` = 'TCP port'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_list` (`cb_list_id`, `cb_field_id`, `default_value`)
                VALUES((SELECT coalesce(MAX(l.cb_list_id),0)+1 from cb_list l), (SELECT `cb_field_id` FROM `cb_field` WHERE `displayname` = 'Enable RRDCached'), 'disable')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_list_values` (`cb_list_id`, `value_name`, `value_value`)
                VALUES
                ((SELECT `cb_list_id` FROM `cb_list` WHERE `cb_field_id` =
                (SELECT `cb_field_id` FROM `cb_field` WHERE `displayname` = 'Enable RRDCached')), 'Disable', 'disable'
                ),
                ((SELECT `cb_list_id` FROM `cb_list` WHERE `cb_field_id` =
                (SELECT `cb_field_id` FROM `cb_field` WHERE `displayname` = 'Enable RRDCached')), 'UNIX Socket', 'unix'
                ),
                ((SELECT `cb_list_id` FROM `cb_list` WHERE `cb_field_id` =
                (SELECT `cb_field_id` FROM `cb_field` WHERE `displayname` = 'Enable RRDCached')), 'TCP Port ', 'tcp'
                )
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `cb_type_field_relation`
                SET `jshook_name` = 'rrdArguments', `jshook_arguments` = '{"target": "rrd_cached"}', `order_display` = 3
                WHERE `cb_type_id` = (SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'rrd') AND `cb_field_id` = (SELECT `cb_field_id` FROM `cb_field` WHERE `displayname` = 'Enable RRDCached')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `cb_type_field_relation`
                SET `order_display` = 4
                WHERE `cb_type_id` = (SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'rrd') AND `cb_field_id` = (SELECT `cb_field_id` FROM `cb_field` WHERE `displayname` = 'RRDCacheD listening socket/port')
                SQL
        );


        /* Update-DB-19.10.0-rc.1.sql */

        // Create rs_poller_relation for the additional relationship between poller and remote servers
        $pearDB->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `rs_poller_relation` (
                `remote_server_id` int(11) NOT NULL,
                `poller_server_id` int(11) NOT NULL,
                KEY `remote_server_id` (`remote_server_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Relation Table For centreon pollers and remote servers'
                SQL
        );

        // new inheritance mode
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES ('inheritance_mode', '1')
                SQL
        );


        /* Update-CSTG-19.10.0.sql */

        // Remove useless reporting tables
        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE log_archive_host
                DROP COLUMN UPTimeAverageAck,
                DROP COLUMN UPTimeAverageRecovery,
                DROP COLUMN DOWNTimeAverageAck,
                DROP COLUMN DOWNTimeAverageRecovery,
                DROP COLUMN UNREACHABLETimeAverageAck,
                DROP COLUMN UNREACHABLETimeAverageRecovery
                SQL
        );
        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE log_archive_service
                DROP COLUMN OKTimeAverageAck,
                DROP COLUMN OKTimeAverageRecovery,
                DROP COLUMN WARNINGTimeAverageAck,
                DROP COLUMN WARNINGTimeAverageRecovery,
                DROP COLUMN UNKNOWNTimeAverageAck,
                DROP COLUMN UNKNOWNTimeAverageRecovery,
                DROP COLUMN CRITICALTimeAverageAck,
                DROP COLUMN CRITICALTimeAverageRecovery
                SQL
        );


        /* Update-19.10.0.php */

        /**
         * Update session duration value to the max allowed duration set in the php
         * configuration file 50-centreon.ini
         */
        try {
            $stmt = $pearDB->query(
                'SELECT `value` FROM `options` WHERE `key` = "session_expire"'
            );
            $sessionValue = $stmt->fetch();

            if ($sessionValue > 120) {
                $pearDB->query(
                    'UPDATE `options` SET `value` = "120"
                    WHERE `key` = "session_expire"'
                );
            }
        } catch (\PDOException $e) {
            $centreonLog->insertLog(
                2,
                "UPGRADE : 19.10.0 Unable to modify session expiration value"
            );

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
