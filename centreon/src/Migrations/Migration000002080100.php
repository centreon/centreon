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

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Pimple\Container;

class Migration000002080100 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.1';

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

        // Update-2.8.1.php

        $query = 'SHOW INDEX FROM comments '
            . 'WHERE column_name = "host_id" '
            . 'AND Key_name = "host_id" ';
        $res = $pearDBO->query($query);
        if (! $res->rowCount()) {
            $pearDBO->query('ALTER TABLE comments ADD KEY host_id(host_id)');
        }

        $query = 'ALTER TABLE `comments` '
            . 'DROP KEY `entry_time`, '
            . 'ADD UNIQUE KEY `entry_time` (`entry_time`,`host_id`,`service_id`, `instance_id`, `internal_id`) ';
        $pearDBO->query($query);

        // Update-DB-2.8.1.sql

        // Drop from nagios configuration
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cfg_nagios`
                DROP COLUMN precached_object_file
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cfg_nagios`
                DROP COLUMN object_cache_file
                SQL
        );

        // Create downtime cache table for recurrent downtimes
        $pearDB->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `downtime_cache` (
                    `downtime_cache_id` int(11) NOT NULL AUTO_INCREMENT,
                    PRIMARY KEY (`downtime_cache_id`),
                    `downtime_id` int(11) NOT NULL,
                    `host_id` int(11) NOT NULL,
                    `service_id` int(11),
                    `start_timestamp` int(11) NOT NULL,
                    `end_timestamp` int(11) NOT NULL,
                    `start_hour` varchar(255) NOT NULL,
                    `end_hour` varchar(255) NOT NULL,
                    CONSTRAINT `downtime_cache_ibfk_1` FOREIGN KEY (`downtime_id`) REFERENCES `downtime` (`dt_id`) ON DELETE CASCADE,
                    CONSTRAINT `downtime_cache_ibfk_2` FOREIGN KEY (`host_id`) REFERENCES `host` (`host_id`) ON DELETE CASCADE,
                    CONSTRAINT `downtime_cache_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        // Add correlation output for Centreon broker
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_module` (`name`, `libname`, `loading_pos`, `is_bundle`, `is_activated`)
                VALUES ('Correlation', 'correlation.so', 30, 0, 1)
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_type` (`type_name`, `type_shortname`, `cb_module_id`)
                VALUES ('Correlation', 'correlation', (SELECT `cb_module_id` FROM `cb_module` WHERE `libname` = 'correlation.so'))
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_field` (`fieldname`, `displayname`, `description`, `fieldtype`, `external`)
                VALUES ('passive', 'Correlation passive', 'The passive mode is for the secondary Centreon Broker.', 'radio', NULL)
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_list` (`cb_list_id`, `cb_field_id`, `default_value`)
                VALUES (1, (SELECT `cb_field_id` FROM `cb_field` WHERE `displayname` = 'Correlation passive'), 'no')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_tag_type_relation` (`cb_tag_id`, `cb_type_id`, `cb_type_uniq`)
                VALUES (1, (SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'correlation'), 1)
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_type_field_relation` (`cb_type_id`, `cb_field_id`, `is_required`, `order_display`)
                VALUES
                    ((SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'correlation'), 29, 1, 1),
                    ((SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'correlation'), (SELECT `cb_field_id` FROM `cb_field` WHERE `displayname` = 'Correlation passive'), 0, 2)
                SQL
        );

        // update broker socket path
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `options`
                WHERE `key` = 'broker_socket_path'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE cfg_centreonbroker
                SET command_file = CONCAT(retention_path, '/command.sock')
                WHERE config_name = 'central-broker-master'
                SQL
        );

        // Insert Macro for PP
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cfg_resource` (`resource_name`, `resource_line`, `resource_comment`, `resource_activate`)
                VALUES ('$CENTREONPLUGINS$', '@CENTREONPLUGINS@', 'Centreon Plugin Path', '1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cfg_resource_instance_relations` (`resource_id`, `instance_id` )
                SELECT r.resource_id, ns.id FROM cfg_resource r, nagios_server ns WHERE r.resource_name = '$CENTREONPLUGINS$'
                SQL
        );

        // KB  double topology_page
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `topology`
                WHERE `topology_page` = 610
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (`topology_id` ,`topology_name` ,`topology_parent` ,`topology_page` ,`topology_order` ,`topology_group` ,`topology_url` ,`topology_url_opt` ,`topology_popup` ,`topology_modules` ,`topology_show` ,`topology_style_class` ,`topology_style_id` ,`topology_OnClick`)
                VALUES (NULL , 'Knowledge Base', '6', '610', '65', '36', NULL, NULL , NULL , '1', '1', NULL , NULL , NULL)
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
