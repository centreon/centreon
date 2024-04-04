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

class Migration000020040000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '20.04.0';

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


        /* Update-CSTG-20.04.0-beta.1.sql */

        $pearDBO->query(
            <<<'SQL'
                DELETE `comments` FROM `comments`
                LEFT OUTER JOIN (
                    SELECT MIN(comment_id) as comment_id, `entry_time`,`host_id`,`service_id`,`instance_id`,`internal_id`
                    FROM `comments`
                    GROUP BY `entry_time`,`host_id`,`service_id`,`instance_id`,`internal_id`
                ) AS t1
                ON t1.comment_id = comments.comment_id
                WHERE t1.comment_id IS NULL
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                UPDATE `comments`
                SET `service_id` = 0
                WHERE `service_id` IS NULL
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE `comments`
                MODIFY COLUMN `service_id` int(11) NOT NULL DEFAULT 0
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                DELETE `downtimes` FROM `downtimes`
                LEFT OUTER JOIN (
                    SELECT MIN(downtime_id) as downtime_id, `entry_time`,`host_id`,`service_id`,`instance_id`,`internal_id`
                    FROM `downtimes`
                    GROUP BY `entry_time`,`host_id`,`service_id`,`instance_id`,`internal_id`
                ) AS t1
                ON t1.downtime_id = downtimes.downtime_id
                WHERE t1.downtime_id IS NULL
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                UPDATE `downtimes`
                SET `service_id` = 0
                WHERE `service_id` IS NULL
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE `downtimes`
                MODIFY COLUMN `service_id` int(11) NOT NULL DEFAULT 0,
                DROP INDEX `entry_time`,
                ADD UNIQUE KEY `entry_time` (`entry_time`, `host_id`, `service_id`, `instance_id`, `internal_id`)
                SQL
        );


        /* Update-20.04.0-beta.1.php */

        $centreonLog = new \CentreonLog();

        //error specific content
        $versionOfTheUpgrade = 'UPGRADE - 20.04.0-beta.1 : ';
        $errorMessage = '';

        /**
         * Queries needing exception management and rollback if failing
         */
        try {
            $pearDB->beginTransaction();

            /*
            * Move broker xml files to json format
            */
            $errorMessage = "Unable to replace broker configuration from xml format to json format";
            $result = $pearDB->query(
                "SELECT config_id, config_filename
                FROM cfg_centreonbroker"
            );

            $statement = $pearDB->prepare(
                "UPDATE cfg_centreonbroker
                SET config_filename = :value
                WHERE config_id = :id"
            );

            $configFilenames = [];
            while ($row = $result->fetch()) {
                $fileName = str_replace('.xml', '.json', $row['config_filename']);

                // saving data for next engine module modifications
                $configFilenames[$row['config_filename']] = $fileName;

                $statement->bindValue(':value', $fileName, \PDO::PARAM_STR);
                $statement->bindValue(':id', $row['config_id'], \PDO::PARAM_INT);

                $statement->execute();
            }

            /*
            * Move engine module xml files to json format
            */
            $errorMessage = "Unable to replace engine's broker modules configuration from xml to json format";
            $result = $pearDB->query(
                "SELECT bk_mod_id, broker_module
                FROM cfg_nagios_broker_module"
            );

            $statement = $pearDB->prepare(
                "UPDATE cfg_nagios_broker_module
                SET broker_module = :value
                WHERE bk_mod_id = :id"
            );
            while ($row = $result->fetch()) {
                $fileName = $row['broker_module'];
                foreach ($configFilenames as $oldName => $newName) {
                    $fileName = str_replace($oldName, $newName, $fileName);
                }
                $statement->bindValue(':value', $fileName, \PDO::PARAM_STR);
                $statement->bindValue(':id', $row['bk_mod_id'], \PDO::PARAM_INT);

                $statement->execute();
            }

            /*
            * Change broker sql output form
            */
            // set common error message on failure
            $partialErrorMessage = $errorMessage;

            // reorganise existing input form
            $errorMessage = $partialErrorMessage . " - While trying to update 'cb_type_field_relation' table data";
            $pearDB->query(
                "UPDATE cb_type_field_relation AS A INNER JOIN cb_type_field_relation AS B ON A.cb_type_id = B.cb_type_id
                SET A.`order_display` = 8
                WHERE B.`cb_field_id` = (SELECT f.cb_field_id FROM cb_field f WHERE f.fieldname = 'buffering_timeout')"
            );

            // add new connections_count input
            $errorMessage = $partialErrorMessage . " - While trying to insert in 'cb_field' table new values";
            $pearDB->query(
                "INSERT INTO `cb_field` (`fieldname`, `displayname`, `description`, `fieldtype`, `external`) 
                VALUES ('connections_count', 'Number of connection to the database', 'Usually cpus/2', 'int', NULL)"
            );

            // add relation
            $errorMessage = $partialErrorMessage . " - While trying to insert in 'cb_type_field_relation' table new values";
            $pearDB->query(
                "INSERT INTO `cb_type_field_relation` (
                    `cb_type_id`,
                    `cb_field_id`,
                    `is_required`,
                    `order_display`,
                    `jshook_name`,
                    `jshook_arguments`
                )
                VALUES (
                    (SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'sql'),
                    (SELECT `cb_field_id` FROM `cb_field` WHERE `fieldname` = 'connections_count'),
                    0,
                    7,
                    'countConnections',
                    '{\"target\": \"connections_count\"}'
                ),
                (
                    (SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'storage'),
                    (SELECT `cb_field_id` FROM `cb_field` WHERE `fieldname` = 'connections_count'),
                    0,
                    7,
                    'countConnections',
                    '{\"target\": \"connections_count\"}'
                )"
            );

            $pearDB->commit();
            $errorMessage = "";
        } catch (\Exception $e) {
            $pearDB->rollBack();
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage .
                " - Code : " . (int)$e->getCode() .
                " - Error : " . $e->getMessage() .
                " - Trace : " . $e->getTraceAsString()
            );
            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int)$e->getCode(), $e);
        }

        /**
         * Queries which don't need rollback and won't throw an exception
         */
        try {
            /*
            * replace autologin keys using NULL instead of empty string
            */
            $pearDB->query("UPDATE `contact` SET `contact_autologin_key` = NULL WHERE `contact_autologin_key` = ''");
        } catch (\Exception $e) {
            $errorMessage = "Unable to set default contact_autologin_key.";
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage .
                " - Code : " . (int)$e->getCode() .
                " - Error : " . $e->getMessage() .
                " - Trace : " . $e->getTraceAsString()
            );
            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int)$e->getCode(), $e);
        }

        /* Update-DB-20.04.0-beta.1.sql */

        // Remove broker correlation mechanism
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cfg_centreonbroker`
                DROP COLUMN `correlation_activate`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `cb_field`
                WHERE `displayname` = 'Correlation file'
                OR `description` LIKE 'File where correlation%'
                OR `displayname` = 'Correlation passive'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `cb_type`
                WHERE `type_shortname` = 'correlation'
                SQL
        );

        // Resolve radio button broker form
        $pearDB->query(
            <<<'SQL'
                INSERT INTO cb_list_values (cb_list_id, value_name, value_value) VALUES
                ((SELECT cb_list_id FROM cb_list WHERE cb_field_id = (SELECT cb_field_id FROM cb_field WHERE fieldtype ='radio' AND fieldname ='error') LIMIT 1),'No','no'),
                ((SELECT cb_list_id FROM cb_list WHERE cb_field_id = (SELECT cb_field_id FROM cb_field WHERE fieldtype ='radio' AND fieldname ='error') LIMIT 1),'Yes','yes')
                SQL
        );

        // Update topology of service grid / by host group / by service group
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_url_opt = '&o=svcOV_pb'
                WHERE topology_page = 20204
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_url_opt = '&o=svcOVHG_pb'
                WHERE topology_page = 20209
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_url_opt = '&o=svcOVSG_pb'
                WHERE topology_page = 20212
                SQL
        );

        // Add unified view page entry
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_group`, `topology_order`)
                VALUES ('Events view (beta)', '/monitoring/events', '1', '1', 1, 104, 1, 2)
                SQL
        );

        // Delete legacy engine parameters
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cfg_nagios`
                DROP COLUMN `check_result_path`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cfg_nagios`
                DROP COLUMN `use_check_result_path`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cfg_nagios`
                DROP COLUMN `max_check_result_file_age`
                SQL
        );

        // Update nagios_server to add gorgone connection
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                ADD `gorgone_communication_type` enum('1','2') NOT NULL DEFAULT '1' AFTER `centreonconnector_path`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                ADD `gorgone_port` INT(11) DEFAULT NULL AFTER `gorgone_communication_type`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `nagios_server`
                SET `gorgone_port` = `ssh_port`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                CHANGE `remote_server_centcore_ssh_proxy` `remote_server_use_as_proxy` enum('0','1') NOT NULL DEFAULT '1'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                DROP COLUMN `ssh_private_key`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `nagios_server`
                SET `gorgone_communication_type` = '2'
                SQL
        );

        // Update options for gorgone
        $pearDB->query(
            <<<'SQL'
                UPDATE options
                SET `key` = 'gorgone_illegal_characters'
                WHERE `key` = 'centcore_illegal_characters'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE options
                SET `key` = 'gorgone_cmd_timeout'
                WHERE `key` = 'centcore_cmd_timeout'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                SELECT 'gorgone_cmd_timeout', '5'
                FROM DUAL
                WHERE NOT EXISTS (SELECT `value` FROM `options` WHERE `key` = 'gorgone_cmd_timeout')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_url_opt = '&o=gorgone', topology_name = 'Gorgone'
                WHERE topology_page = 50117
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE options
                SET `key` = 'debug_gorgone'
                WHERE `key` = 'debug_centcore'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `options`
                WHERE `key` = 'enable_perfdata_sync'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `options`
                WHERE `key` = 'enable_logs_sync'
                SQL
        );

        // Gorgone API default
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES ('gorgone_api_address', '127.0.0.1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES ('gorgone_api_port', '8085')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES ('gorgone_api_ssl', '0')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES ('gorgone_api_allow_self_signed', '1')
                SQL
        );

        // Add default value for enable_broker_stats if not set
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                SELECT 'enable_broker_stats', '0'
                FROM DUAL
                WHERE NOT EXISTS (SELECT `value` FROM `options` WHERE `key` = 'enable_broker_stats')
                SQL
        );

        // Add missing index on ods_view_details
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `ods_view_details`
                ADD KEY `index_id` (`index_id`)
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
