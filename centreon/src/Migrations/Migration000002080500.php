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

class Migration000002080500 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '2.8.5';

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


        /* Update-DB-2.8.5.sql */

        $pearDB->query(
            <<<'SQL'
                SET SESSION innodb_strict_mode=OFF
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE nagios_server
                ADD COLUMN centreonbroker_logs_path VARCHAR(255)
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE cfg_centreonbroker
                ADD COLUMN daemon TINYINT(1)
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                SET SESSION innodb_strict_mode=ON
                SQL
        );

        // Use service
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = 'cbd'
                WHERE `key` = 'broker_correlator_script'
                AND `value` = '/etc/init.d/cbd'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `nagios_server`
                SET `init_script` = 'centengine'
                WHERE `init_script` = '/etc/init.d/centengine'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `nagios_server`
                SET `init_script_centreontrapd` = 'centreontrapd'
                WHERE `init_script_centreontrapd` = '/etc/init.d/centreontrapd'
                SQL
        );

        // Missing 'integer' type, mostly used for auto-refresh preference.
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `widget_parameters_field_type` (`ft_typename`, `is_connector`)
                VALUES ('integer', 0)
                SQL
        );

        // custom views share options
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE custom_view_user_relation
                ADD is_share tinyint(1) NOT NULL DEFAULT 0 AFTER is_consumed
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE custom_view_user_relation
                SET is_share = 1
                WHERE is_owner = 0
                SQL
        );

        // Remove useless proxy option
        $pearDB->query(
            <<<'SQL'
                DELETE FROM options
                WHERE options.key = 'proxy_protocol'
                SQL
        );

        // Add column to hide acl resources
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE acl_resources
                ADD locked tinyint(1) NOT NULL DEFAULT 0 AFTER changed
                SQL
        );

        // Update broker cache directory column name
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE cfg_centreonbroker
                CHANGE COLUMN `retention_path` `cache_directory` VARCHAR(255) DEFAULT NULL
                SQL
        );

        // change column type
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE downtime_period
                MODIFY COLUMN `dtp_month_cycle` varchar(100)
                SQL
        );

        // Add topology for split
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_show`, `readonly`)
                VALUES ('Chart split', 20401, 2040101, 1, 1, './include/views/graphs/graph-split.php', '0', '1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_show`, `readonly`)
                VALUES ('Chart periods', 20401, 2040102, 1, 1, './include/views/graphs/graph-periods.php', '0', '1')
                SQL
        );

        // Fix problem regarding the recurrent downtimes
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_url_opt = NULL
                WHERE topology_page = 21003
                SQL
        );

        // Update event queue max size
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE cfg_centreonbroker
                CHANGE COLUMN `event_queue_max_size` `event_queue_max_size` int(11) DEFAULT 100000
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE cfg_centreonbroker
                SET event_queue_max_size = 100000
                WHERE event_queue_max_size < 100000
                SQL
        );


        /* Update-2.8.5.post.php */

        // Update comments unique key
        $query = 'SELECT cb.config_id, COUNT(cbi.config_group) AS nb '
        . 'FROM cfg_centreonbroker cb '
        . 'LEFT JOIN cfg_centreonbroker_info cbi '
        . 'ON cbi.config_id = cb.config_id '
        . 'AND cbi.config_group = "input" '
        . 'GROUP BY cb.config_id ';
        $res = $pearDB->query($query);
        while ($row = $res->fetchRow()) {
            $daemon = 0;
            if ($row['nb'] > 0) {
                $daemon = 1;
            }
            $query = 'UPDATE cfg_centreonbroker '
                . 'SET daemon = :daemon '
                . 'WHERE config_id = :config_id ';
            $statement = $pearDB->prepare($query);
            $statement->bindValue(":daemon", $daemon, \PDO::PARAM_INT);
            $statement->bindValue(":config_id", (int) $row['config_id'], \PDO::PARAM_INT);
            $statement->execute();
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
