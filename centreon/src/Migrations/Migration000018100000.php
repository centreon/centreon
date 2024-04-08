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

class Migration000018100000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '18.10.0';

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

        // Update-DB-18.10.0.sql

        // Create remote servers table for keeping track of remote instances
        $pearDB->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `remote_servers` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `ip` VARCHAR(16) NOT NULL,
                `app_key` VARCHAR(40) NOT NULL,
                `version` VARCHAR(16) NOT NULL,
                `is_connected` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL,
                `connected_at` TIMESTAMP NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        // Add column to topology table to mark which pages are with React
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `topology`
                ADD COLUMN `is_react` ENUM('0', '1') NOT NULL DEFAULT '0' AFTER `readonly`
                SQL
        );

        // Change informations lengths
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `informations`
                MODIFY COLUMN `value` varchar (255) NULL
                SQL
        );

        // Move "Graphs" & "Broker Statistics" as "Server status" sub menu
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_parent = '505'
                WHERE topology_page = '10205'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_page = '50501'
                WHERE topology_page = '10205'
                AND topology_parent = '505'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_parent = '505'
                WHERE topology_page = '10201'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_page = '50502'
                WHERE topology_page = '10201'
                AND topology_parent = '505'
                SQL
        );

        // Rename "Graphs" menu to "Engine Statistics"
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_name = 'Engine Statistics'
                WHERE topology_page = '50502'
                SQL
        );

        // Rename "Server Status" to "Platform Status"
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_name = 'Platform Status'
                WHERE topology_page = '505'
                SQL
        );

        // Change default page of "Platform Status" menu to "Broker Statistics"
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_url = './include/Administration/brokerPerformance/brokerPerformance.php'
                WHERE topology_page = '505'
                AND topology_name = 'Platform Status'
                SQL
        );

        // Delete old entries
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_page = '102'
                SQL
        );

        // Remove Zend support
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `options`
                WHERE `key` = 'backup_zend_conf'
                SQL
        );

        // Create tasks table
        $pearDB->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `task` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `type` VARCHAR(40) NOT NULL,
                `status` VARCHAR(40) NOT NULL,
                `parent_id` INT(11) NULL,
                `params` BLOB NULL,
                `created_at` TIMESTAMP NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        // Add column to nagios_server table for remote-poller relation
        $pearDB->query(
            <<<'SQL'
                SET SESSION innodb_strict_mode=OFF
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                ADD COLUMN `remote_id` int(11) NULL AFTER `centreonbroker_logs_path`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                SET SESSION innodb_strict_mode=ON
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                ADD CONSTRAINT `nagios_server_remote_id_id` FOREIGN KEY (`remote_id`) REFERENCES `nagios_server` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                SQL
        );

        // Update the "About" menu
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_url = './include/Administration/about/about.php',
                    topology_modules = '0',
                    topology_popup = '0'
                WHERE topology_page = 506
                AND topology_parent = 5
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_parent = 506
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `remote_servers`
                ADD COLUMN `centreon_path` varchar(255) NULL
                SQL
        );

        // Insert Multi-step Wizard into Topology
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (topology_name, topology_url, readonly, is_react)
                VALUES ('Poller/Remote Wizard', '/poller-wizard/1', '0', '1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (topology_name, topology_url, readonly, is_react)
                VALUES ('Remote Wizard Step 2', '/poller-wizard/2', '0', '1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (topology_name, topology_url, readonly, is_react)
                VALUES ('Remote Wizard Step 3', '/poller-wizard/3', '0', '1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (topology_name, topology_url, readonly, is_react)
                VALUES ('Remote Wizard Final Step', '/poller-wizard/4', '0', '1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (topology_name, topology_url, readonly, is_react)
                VALUES ('Poller Wizard Step 2', '/poller-wizard/5', '0', '1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (topology_name, topology_url, readonly, is_react)
                VALUES ('Poller Wizard Step 3', '/poller-wizard/6', '0', '1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (topology_name, topology_url, readonly, is_react)
                VALUES ('Poller Wizard Final Step', '/poller-wizard/7', '0', '1')
                SQL
        );

        // Update-18.10.0.post.php

        // Generate random key for application key
        $uniqueKey = md5(uniqid((string) mt_rand(), true));
        $query = "INSERT INTO `informations` (`key`,`value`) VALUES ('appKey', '{$uniqueKey}')";
        $pearDB->query($query);
        $query = "INSERT INTO `informations` (`key`,`value`) VALUES ('isRemote', 'no')";
        $pearDB->query($query);
        $query = "INSERT INTO `informations` (`key`,`value`) VALUES ('isCentral', 'no')";
        $pearDB->query($query);

        // Retrieve current Nagios plugins path.
        $query = "SELECT value FROM options WHERE `key`='nagios_path_plugins'";
        $result = $pearDB->query($query);
        $row = $result->fetchRow();

        // Update to new path if necessary.
        if ($row
            && preg_match('#/usr/lib/nagios/plugins/?#', $row['value'])
            && is_dir('/usr/lib64/nagios/plugins')
        ) {
            // options table.
            $query = "UPDATE options SET value='/usr/lib64/nagios/plugins/' WHERE `key`='nagios_path_plugins'";
            $pearDB->query($query);

            // USER1 resource.
            $pearDB->query(
                "UPDATE cfg_resource SET resource_line='/usr/lib64/nagios/plugins'
                WHERE resource_line='/usr/lib/nagios/plugins'"
            );
        }

        // fix menu acl when child is checked but its parent is not checked

        // get all acl menu configurations
        $aclTopologies = $pearDB->query('SELECT acl_topo_id FROM acl_topology');
        while ($aclTopology = $aclTopologies->fetch()) {
            $aclTopologyId = $aclTopology['acl_topo_id'];

            // get parents of topologies which are at least read only
            $statement = $pearDB->prepare(
                'SELECT t.topology_page, t.topology_id, t.topology_parent '
                . 'FROM acl_topology_relations atr, topology t '
                . 'WHERE acl_topo_id = :topologyId '
                . 'AND atr.topology_topology_id = t.topology_id '
                . 'AND atr.access_right IN (1,2) ' // read/write and read only
            );
            $statement->bindParam(':topologyId', $aclTopologyId, \PDO::PARAM_INT);
            $statement->execute();
            $topologies = $statement->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

            // get missing parent topology relations
            $aclToInsert = [];
            foreach ($topologies as $topologyPage => $topologyParameters) {
                if (isset($topologyParameters['topology_parent'])
                    && ! isset($topologies[$topologyParameters['topology_parent']])
                    && ! in_array($topologyParameters['topology_parent'], $aclToInsert, true)
                ) {
                    if (mb_strlen($topologyPage) === 5) { // level 3
                        $levelOne = mb_substr($topologyPage, 0, 1); // get level 1 from beginning of topology_page
                        if (! in_array($levelOne, $aclToInsert, true)) {
                            $aclToInsert[] = $levelOne;
                        }
                        $levelTwo = mb_substr($topologyPage, 0, 3); // get level 2 from beginning of topology_page
                        if (! in_array($levelTwo, $aclToInsert, true)) {
                            $aclToInsert[] = $levelTwo;
                        }
                    } elseif (mb_strlen($topologyPage) === 3) { // level 2
                        $levelOne = mb_substr($topologyPage, 0, 1); // get level 1 from beginning of topology_page
                        if (! in_array($levelOne, $aclToInsert, true)) {
                            $aclToInsert[] = $levelOne;
                        }
                    }
                }
            }

            // insert missing parent topology relations
            if (count($aclToInsert)) {
                $bindedValues = [];
                foreach ($aclToInsert as $aclIndex => $aclValue) {
                    $bindedValues[':acl_' . $aclIndex] = (int) $aclValue;
                }
                $bindedQueries = implode(', ', array_keys($bindedValues));
                $statement = $pearDB->prepare(
                    'INSERT INTO acl_topology_relations(acl_topo_id, topology_topology_id) '
                    . 'SELECT :acl_topology_id, t.topology_id '
                    . 'FROM topology t '
                    . "WHERE t.topology_page IN ({$bindedQueries})"
                );
                $statement->bindValue(':acl_topology_id', (int) $aclTopologyId, \PDO::PARAM_INT);
                foreach ($bindedValues as $bindedIndex => $bindedValue) {
                    $statement->bindValue($bindedIndex, $bindedValue, \PDO::PARAM_INT);
                }
                $statement->execute();
            }
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
