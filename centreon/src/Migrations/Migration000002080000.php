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

require_once __DIR__ . '/../../www/class/centreonMeta.class.php';

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Pimple\Container;

class Migration000002080000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.0';

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

        // Update-DB-2.8.0-beta2.sql

        // Remove failover field from graphite broker output
        $pearDB->query(
            <<<'SQL'
                DELETE cbfr FROM cb_type_field_relation cbfr
                INNER JOIN cb_field cbf ON cbfr.cb_field_id=cbf.cb_field_id
                INNER JOIN cb_type cbt ON cbfr.cb_type_id=cbt.cb_type_id
                AND cbf.fieldname = 'failover'
                AND cbt.type_shortname = 'graphite'
                SQL
        );

        // Insert Centreon Backup menu in topology
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (
                    `topology_id`, `topology_name`, `topology_parent`, `topology_page`, `topology_order`,
                    `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`,
                    `topology_style_class`, `topology_style_id`, `topology_OnClick`, `readonly`
                ) VALUES (
                    NULL,'Backup',501,50165,90,
                    1,'./include/Administration/parameters/parameters.php','&o=backup','0','0','1',
                    NULL,NULL,NULL,'1'
                )
                SQL
        );

        // Insert Centreon Backup base conf
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES
                    ('backup_enabled', '0'),
                    ('backup_configuration_files', '1'),
                    ('backup_database_centreon', '1'),
                    ('backup_database_centreon_storage', '1'),
                    ('backup_database_type', '1'),
                    ('backup_database_full', ''),
                    ('backup_database_partial', ''),
                    ('backup_backup_directory', '/var/backup'),
                    ('backup_tmp_directory', '/tmp/backup'),
                    ('backup_retention', '7'),
                    ('backup_mysql_conf', '/etc/my.cnf.d/centreon.cnf'),
                    ('backup_zend_conf', '/etc/php.d/zendguard.ini')
                SQL
        );

        // Insert KB configuration
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES
                    ('kb_db_name', ''),
                    ('kb_db_user', ''),
                    ('kb_db_password', ''),
                    ('kb_db_host', ''),
                    ('kb_db_prefix', ''),
                    ('kb_WikiURL', '')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology`
                    (`topology_id` ,`topology_name` ,`topology_parent` ,`topology_page` ,`topology_order` ,`topology_group` ,`topology_url` ,`topology_url_opt` ,`topology_popup` ,`topology_modules` ,`topology_show` ,`topology_style_class` ,`topology_style_id` ,`topology_OnClick`)
                VALUES
                    (NULL , 'Knowledge Base', '501', '50133', 90, 1, './include/Administration/parameters/parameters.php', '&o=knowledgeBase' , NULL , '1', '1', NULL , NULL , NULL)
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `topology`
                SET topology_name = 'Graphs'
                WHERE topology_name = 'Edit View'
                AND topology_page = '10201'
                SQL
        );

        // Fix influxdb broker output in fresh install of centreon-2.8.0-beta1
        $pearDB->query(
            <<<'SQL'
                DELETE FROM cb_type_field_relation
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO cb_type_field_relation (cb_type_id, is_required, cb_field_id, order_display)
                (SELECT (SELECT cbt.cb_type_id FROM cb_type cbt WHERE cbt.type_shortname = 'influxdb' LIMIT 1), 0, cbf1.cb_field_id, @rownum := @rownum + 1
                FROM cb_field cbf1 CROSS JOIN (SELECT @rownum := 0) r
                WHERE cbf1.cb_field_id IN (SELECT cbf2.cb_field_id FROM cb_field cbf2 WHERE cbf2.fieldname IN (
                    'db_host', 'db_port', 'db_user', 'db_password',
                    'metrics_timeseries')
                )
                ORDER BY FIELD(cbf1.fieldname, 'db_host', 'db_port', 'db_user', 'db_password',
                    'metrics_timeseries')
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO cb_type_field_relation (cb_type_id, is_required, cb_field_id, order_display)
                (SELECT (SELECT cbt.cb_type_id FROM cb_type cbt WHERE cbt.type_shortname = 'influxdb' LIMIT 1), 0, cbf1.cb_field_id, @rownum := @rownum + 6
                FROM cb_field cbf1 CROSS JOIN (SELECT @rownum := 0) r
                WHERE cbf1.cb_fieldgroup_id IN (SELECT cbfg.cb_fieldgroup_id FROM cb_fieldgroup cbfg WHERE cbfg.groupname = 'metrics_column')
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO cb_type_field_relation (cb_type_id, is_required, cb_field_id, order_display)
                (SELECT (SELECT cbt.cb_type_id FROM cb_type cbt WHERE cbt.type_shortname = 'influxdb' LIMIT 1), 0, cbf1.cb_field_id, @rownum := @rownum + 10
                FROM cb_field cbf1 CROSS JOIN (SELECT @rownum := 0) r
                WHERE cbf1.cb_field_id IN (SELECT cbf2.cb_field_id FROM cb_field cbf2 WHERE cbf2.fieldname = 'status_timeseries')
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO cb_type_field_relation (cb_type_id, is_required, cb_field_id, order_display)
                (SELECT (SELECT cbt.cb_type_id FROM cb_type cbt WHERE cbt.type_shortname = 'influxdb' LIMIT 1), 0, cbf1.cb_field_id, @rownum := @rownum + 11
                FROM cb_field cbf1 CROSS JOIN (SELECT @rownum := 0) r
                WHERE cbf1.cb_fieldgroup_id IN (SELECT cbfg.cb_fieldgroup_id FROM cb_fieldgroup cbfg WHERE cbfg.groupname = 'status_column')
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation SET is_required = 1
                WHERE
                    cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                    AND cb_field_id IN (SELECT cb_field_id FROM cb_field where fieldname IN ('db_host', 'metrics_timeseries', 'status_timeseries'))
                SQL
        );

        // Update-CSTG-2.8.0.sql

        // Issue #4649 - [logAnalyserBroker] Doesn't work
        $pearDBO->query(
            <<<'SQL'
                UPDATE `config`
                SET
                    nagios_log_file = '/var/log/centreon-engine/centengine.log',
                    archive_log = 1
                SQL
        );

        // Issue #4624 - improve poller listing loading time
        $pearDBO->query(
            <<<'SQL'
                CREATE INDEX action_log_date_idx
                ON log_action (action_log_date)
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE centreon_acl
                DROP INDEX group_id_by_id
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE centreon_acl
                ADD INDEX `index1` (`group_id`,`host_id`,`service_id`)
                SQL
        );

        // Update-2.8.0.php

        $metaObj = new \CentreonMeta($pearDB);
        $hostId = null;
        $virtualServices = [];

        // Check virtual host
        $queryHost = 'SELECT host_id '
            . 'FROM host '
            . 'WHERE host_register = "2" '
            . 'AND host_name = "_Module_Meta" ';
        $res = $pearDB->query($queryHost);
        if ($res->rowCount()) {
            $row = $res->fetchRow();
            $hostId = $row['host_id'];
        } else {
            $query = 'INSERT INTO host (host_name, host_register) '
                . 'VALUES ("_Module_Meta", "2") ';
            $pearDB->query($query);
            $res = $pearDB->query($queryHost);
            if ($res->rowCount()) {
                $row = $res->fetchRow();
                $hostId = $row['host_id'];
            }
        }

        // Check existing virtual services
        $query = 'SELECT service_id, service_description '
            . 'FROM service '
            . 'WHERE service_description LIKE "meta_%" '
            . 'AND service_register = "2" ';
        $res = $pearDB->query($query);
        while ($row = $res->fetchRow()) {
            if (preg_match('/meta_(\d+)/', $row['service_description'], $matches)) {
                $metaId = $matches[1];
                $virtualServices[$metaId]['service_id'] = $row['service_id'];
            }
        }

        // Check existing relations between virtual services and virtual host
        $query = 'SELECT s.service_id, s.service_description '
            . 'FROM service s, host_service_relation hsr '
            . 'WHERE hsr.host_host_id = :host_id '
            . 'AND s.service_register = "2" '
            . 'AND s.service_description LIKE "meta_%" ';
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':host_id', (int) $hostId, \PDO::PARAM_INT);
        $statement->execute();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (preg_match('/meta_(\d+)/', $row['service_description'], $matches)) {
                $metaId = $matches[1];
                $virtualServices[$metaId]['relation'] = true;
            }
        }

        $query = 'SELECT meta_id, meta_name FROM meta_service';
        $res = $pearDB->query($query);
        while ($row = $res->fetchRow()) {
            if (! isset($virtualServices[$row['meta_id']]) || ! isset($virtualServices[$row['meta_id']]['service_id'])) {
                $serviceId = $metaObj->insertVirtualService($row['meta_id'], $row['meta_name']);
            } else {
                $serviceId = $virtualServices[$row['meta_id']]['service_id'];
            }
            if (! isset($virtualServices[$row['meta_id']]) || ! isset($virtualServices[$row['meta_id']]['relation'])) {
                $query = 'INSERT INTO host_service_relation (host_host_id, service_service_id) '
                    . 'VALUES (:host_id, :service_id) ';
                $statement = $pearDB->prepare($query);
                $statement->bindValue(':host_id', (int) $hostId, \PDO::PARAM_INT);
                $statement->bindValue(':service_id', (int) $serviceId, \PDO::PARAM_INT);
                $statement->execute();
            }
        }

        // Update-DB-2.8.0.sql

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `widget_parameters_field_type` (`ft_typename`, `is_connector`)
                VALUES
                    ('hostCategoriesMulti', 1),
                    ('hostGroupMulti', 1),
                    ('hostMulti', 1),
                    ('metricMulti', 1),
                    ('serviceCategory', 1),
                    ('hostCategory', 1),
                    ('serviceMulti', 1),
                    ('serviceGroupMulti',1),
                    ('pollerMulti',1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value`='/var/cache/centreon/backup'
                WHERE `key`='backup_backup_directory'
                SQL
        );

        // Update influxdb output
        $pearDB->query(
            <<<'SQL'
                INSERT INTO cb_type_field_relation (cb_type_id, cb_field_id, cb_fieldset_id, is_required, order_display)
                VALUES (
                    (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1),
                    (SELECT cb_field_id FROM cb_field WHERE fieldname = 'cache' LIMIT 1),
                    NULL,
                    0,
                    1
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 2
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (SELECT cb_field_id FROM cb_field WHERE fieldname = 'db_host' LIMIT 1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 3
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (SELECT cb_field_id FROM cb_field WHERE fieldname = 'db_port' LIMIT 1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 4,
                is_required = 1
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (SELECT cb_field_id FROM cb_field WHERE fieldname = 'db_user' LIMIT 1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 5
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (SELECT cb_field_id FROM cb_field WHERE fieldname = 'db_password' LIMIT 1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO cb_type_field_relation (cb_type_id, cb_field_id, cb_fieldset_id, is_required, order_display)
                VALUES (
                    (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1),
                    (SELECT cb_field_id FROM cb_field WHERE fieldname = 'db_name' LIMIT 1),
                    NULL,
                    1,
                    6
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 7
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (SELECT cb_field_id FROM cb_field WHERE fieldname = 'metrics_timeseries' LIMIT 1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 8
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (
                    SELECT cbf.cb_field_id
                    FROM cb_field cbf, cb_fieldgroup cbfg
                    WHERE cbf.fieldname = 'name'
                    AND cbf.cb_fieldgroup_id = cbfg.cb_fieldgroup_id
                    AND cbfg.groupname = 'metrics_column' LIMIT 1
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 9
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (
                    SELECT cbf.cb_field_id
                    FROM cb_field cbf, cb_fieldgroup cbfg
                    WHERE cbf.fieldname = 'value'
                    AND cbf.cb_fieldgroup_id = cbfg.cb_fieldgroup_id
                    AND cbfg.groupname = 'metrics_column' LIMIT 1
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 10
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (
                    SELECT cbf.cb_field_id
                    FROM cb_field cbf, cb_fieldgroup cbfg
                    WHERE cbf.fieldname = 'type'
                    AND cbf.cb_fieldgroup_id = cbfg.cb_fieldgroup_id
                    AND cbfg.groupname = 'metrics_column' LIMIT 1
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 11
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (
                    SELECT cbf.cb_field_id
                    FROM cb_field cbf, cb_fieldgroup cbfg
                    WHERE cbf.fieldname = 'is_tag'
                    AND cbf.cb_fieldgroup_id = cbfg.cb_fieldgroup_id
                    AND cbfg.groupname = 'metrics_column' LIMIT 1
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 12
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (SELECT cb_field_id FROM cb_field WHERE fieldname = 'status_timeseries' LIMIT 1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 13
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (
                    SELECT cbf.cb_field_id
                    FROM cb_field cbf, cb_fieldgroup cbfg
                    WHERE cbf.fieldname = 'name'
                    AND cbf.cb_fieldgroup_id = cbfg.cb_fieldgroup_id
                    AND cbfg.groupname = 'status_column' LIMIT 1
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 14
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (
                    SELECT cbf.cb_field_id
                    FROM cb_field cbf, cb_fieldgroup cbfg
                    WHERE cbf.fieldname = 'value'
                    AND cbf.cb_fieldgroup_id = cbfg.cb_fieldgroup_id
                    AND cbfg.groupname = 'status_column' LIMIT 1
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 15
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (
                    SELECT cbf.cb_field_id
                    FROM cb_field cbf, cb_fieldgroup cbfg
                    WHERE cbf.fieldname = 'type'
                    AND cbf.cb_fieldgroup_id = cbfg.cb_fieldgroup_id
                    AND cbfg.groupname = 'status_column' LIMIT 1
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE cb_type_field_relation
                SET order_display = 16
                WHERE cb_type_id = (SELECT cb_type_id FROM cb_type WHERE type_shortname = 'influxdb' LIMIT 1)
                AND cb_field_id = (
                    SELECT cbf.cb_field_id
                    FROM cb_field cbf, cb_fieldgroup cbfg
                    WHERE cbf.fieldname = 'is_tag'
                    AND cbf.cb_fieldgroup_id = cbfg.cb_fieldgroup_id
                    AND cbfg.groupname = 'status_column' LIMIT 1
                )
                SQL
        );

        // Ticket #4687
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE timeperiod
                MODIFY tp_alias varchar(200)
                SQL
        );

        // Update maximum number of chart in performance
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '18'
                WHERE `key` = 'maxGraphPerformances'
                SQL
        );

        // Can enable/disable chart extended information #4679
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES
                    ('display_downtime_chart','0'),
                    ('display_comment_chart','0')
                SQL
        );

        // Add index for better performance on ods_view_details #4670
        $pearDB->query(
            <<<'SQL'
                CREATE INDEX `contact_index`
                ON `ods_view_details` (`contact_id`, `index_id`)
                USING BTREE
                SQL
        );

        // Replace Generate in breadcrumb by Export configuration
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_name = 'Export configuration'
                WHERE topology_page = 60902
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
