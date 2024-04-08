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

class Migration000020100000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '20.10.0';

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

        // Update-DB-20.10.0-beta.1.sql

        // Create user_filter table
        $pearDB->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `user_filter` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL,
                    `user_id` int(11) NOT NULL,
                    `page_name` varchar(255) NOT NULL,
                    `criterias` text,
                    `order` int(11) NOT NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `filter_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        // Rename and move events view menu
        $pearDB->query(
            <<<'SQL'
                UPDATE `topology`
                SET `topology_name` = 'Resources Status', `topology_url` = '/monitoring/resources', `topology_parent` = 2, `topology_page` = 200
                WHERE `topology_page` = 104
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `contact`
                SET `default_page` = 200
                WHERE `default_page` = 104 OR `default_page` IS NULL
                SQL
        );

        // Add deprecation column in topology
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `topology`
                ADD COLUMN `is_deprecated` enum('0','1') NOT NULL DEFAULT '0' AFTER `topology_show`
                SQL
        );

        // Set services and hosts monitoring pages to deprecated
        $pearDB->query(
            <<<'SQL'
                UPDATE `topology`
                SET `is_deprecated` = '1'
                WHERE `topology_page` IN (20201, 20202)
                SQL
        );

        // Add page deprecation column to contact
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contact`
                ADD COLUMN `show_deprecated_pages` enum('0','1') DEFAULT '0' AFTER `default_page`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `contact`
                SET `show_deprecated_pages` = '1'
                SQL
        );

        // Create platform_topology table
        $pearDB->query(
            <<<'SQL'
                CREATE TABLE `platform_topology` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `address` varchar(255) NOT NULL,
                    `name` varchar(255) NOT NULL,
                    `type` varchar(255) NOT NULL,
                    `parent_id` int(11),
                    `server_id` int(11),
                    PRIMARY KEY (`id`),
                    CONSTRAINT `platform_topology_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `nagios_server` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `platform_topology_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `platform_topology` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                COMMENT='Registration and parent relation Table used to set the platform topology'
                SQL
        );

        // Modify informations.value column length from 255 to 1024 chars
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `informations`
                MODIFY `value` varchar(1024)
                SQL
        );

        // Update-20.10.0-beta.1.post.php

        $centreonLog = new \CentreonLog();

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 20.10.0-beta.1.post : ';

        $errorMessage = '';

        /**
         * Queries needing exception management and rollback if failing.
         */
        try {
            $pearDB->beginTransaction();
            /**
             * register server to 'platform_status' table.
             */
            // Correct 'isCentral' flag value
            $errorMessage = "Unable to get server data from the 'informations' table.";
            $result = $pearDB->query("
                SELECT count(*) as `count` FROM `informations`
                WHERE (`key` = 'isRemote' AND `value` = 'no') OR (`key` = 'isCentral' AND `value` = 'no')
            ");
            $row = $result->fetch();
            if (2 === (int) $row['count']) {
                $errorMessage = "Unable to modify isCentral flag value in 'informations' table.";
                $stmt = $pearDB->query("UPDATE `informations` SET `value` = 'yes' WHERE `key` = 'isCentral'");
            }
            /**
             * activate remote access page in topology menu.
             */
            $showPage = '0';
            $serverType = $pearDB->query("
                SELECT `value` FROM `informations`
                WHERE `key` = 'isRemote'
            ");
            if ('yes' === $serverType->fetch()['value']) {
                $showPage = '1';
            }
            // Create a new menu page related to remote. Hidden by default on a Central
            // This page is displayed only on remote platforms.
            $errorMessage = "Unable to insert 'Remote access' page in 'topology' table.";
            $stmt = $pearDB->query("
                INSERT INTO `topology` (
                    `topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`,
                    `topology_url`, `topology_url_opt`,
                    `topology_popup`, `topology_modules`, `topology_show`,
                    `topology_style_class`, `topology_style_id`, `topology_OnClick`, `readonly`
                ) VALUES (
                    'Remote access', 501, 50120, 25, 1,
                    './include/Administration/parameters/parameters.php', '&o=remote',
                    '0', '0', '" . $showPage . "',
                    NULL, NULL, NULL, '1'
                )
            ");

            // migrate resource status menu acl
            $errorMessage = 'Unable to update acl of resource status page.';

            $resourceStatusQuery = $pearDB->query(
                'SELECT topology_id, topology_page FROM topology WHERE topology_page IN (2, 200)'
            );

            $topologyAclStatement = $pearDB->prepare(
                'SELECT DISTINCT(tr1.acl_topo_id)
                FROM acl_topology_relations tr1
                WHERE tr1.acl_topo_id NOT IN (
                    SELECT tr2.acl_topo_id
                    FROM acl_topology_relations tr2, topology t2
                    WHERE tr2.topology_topology_id = t2.topology_id
                    AND t2.topology_page = :topology_page
                )
                AND tr1.acl_topo_id IN (
                    SELECT tr3.acl_topo_id
                    FROM acl_topology_relations tr3, topology t3
                    WHERE tr3.topology_topology_id = t3.topology_id
                    AND t3.topology_page IN (20201, 20202)
                )'
            );

            $topologyInsertStatement = $pearDB->prepare('
                INSERT INTO `acl_topology_relations` (
                    `topology_topology_id`,
                    `acl_topo_id`,
                    `access_right`
                ) VALUES (
                    :topology_id,
                    :acl_topology_id,
                    1
                )
            ');

            while ($resourceStatusPage = $resourceStatusQuery->fetch()) {
                $topologyAclStatement->bindValue(':topology_page', (int) $resourceStatusPage['topology_page'], \PDO::PARAM_INT);
                $topologyAclStatement->execute();

                while ($row = $topologyAclStatement->fetch()) {
                    $topologyInsertStatement->bindValue(':topology_id', (int) $resourceStatusPage['topology_id'], \PDO::PARAM_INT);
                    $topologyInsertStatement->bindValue(':acl_topology_id', (int) $row['acl_topo_id'], \PDO::PARAM_INT);
                    $topologyInsertStatement->execute();
                }
            }

            $monitoringTopologyStatement = $pearDB->query(
                'SELECT DISTINCT(tr1.acl_topo_id)
                FROM acl_topology_relations tr1
                WHERE tr1.acl_topo_id NOT IN (
                    SELECT tr2.acl_topo_id
                    FROM acl_topology_relations tr2, topology t2
                    WHERE tr2.topology_topology_id = t2.topology_id
                    AND t2.topology_page = 2
                )
                AND tr1.acl_topo_id IN (
                    SELECT tr3.acl_topo_id
                    FROM acl_topology_relations tr3, topology t3
                    WHERE tr3.topology_topology_id = t3.topology_id
                    AND t3.topology_page = 200
                )'
            );

            $monitoringPageQuery = $pearDB->query(
                'SELECT topology_id FROM topology WHERE topology_page = 2'
            );
            $monitoringPage = $monitoringPageQuery->fetch();

            while ($topology = $monitoringTopologyStatement->fetch()) {
                if ($monitoringPage !== false) {
                    $topologyInsertStatement->bindValue(':topology_id', (int) $monitoringPage['topology_id'], \PDO::PARAM_INT);
                    $topologyInsertStatement->bindValue(':acl_topology_id', (int) $topology['acl_topo_id'], \PDO::PARAM_INT);
                    $topologyInsertStatement->execute();
                }
            }

            $pearDB->commit();
            $errorMessage = '';
        } catch (\Exception $e) {
            $pearDB->rollBack();
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage
                . ' - Code : ' . (int) $e->getCode()
                . ' - Error : ' . $e->getMessage()
                . ' - Trace : ' . $e->getTraceAsString()
            );

            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
        }

        // Update-20.10.0-beta.2.php

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 20.10.0-beta.2 : ';

        /**
         * Queries needing exception management and rollback if failing.
         */
        try {
            $pearDB->beginTransaction();

            // Move keycloak configuration to OpenId Connect one
            $errorMessage = 'Unable to move Keycloak configuration to OpenId Connect';
            $result = $pearDB->query(
                "SELECT * FROM options WHERE options.key IN ('keycloak_enable', 'keycloak_mode', 'keycloak_url',
                'keycloak_redirect_url', 'keycloak_realm', 'keycloak_client_id', 'keycloak_client_secret',
                'keycloak_trusted_clients', 'keycloak_blacklist_clients')"
            );

            $keycloak = [];
            while ($row = $result->fetch()) {
                $keycloak[$row['key']] = $row['value'];
            }

            $keycloakBaseUrl = null;
            if (! empty($keycloak['keycloak_url']) && ! empty($keycloak['keycloak_realm'])) {
                $keycloakUrl = $keycloak['keycloak_url'] . '/realms/'
                    . $keycloak['keycloak_realm'] . '/protocol/openid-connect';
            }
            $openIdConnect = [
                'openid_connect_enable' => $keycloak['keycloak_enable'] ?? null,
                'openid_connect_mode' => $keycloak['keycloak_mode'] ?? null,
                'openid_connect_base_url' => $keycloakBaseUrl,
                'openid_connect_authorization_endpoint' => isset($keycloak['keycloak_url']) ? '/auth' : null,
                'openid_connect_token_endpoint' => isset($keycloak['keycloak_url']) ? '/token' : null,
                'openid_connect_introspection_endpoint' => isset($keycloak['keycloak_url'])  ? '/introspect' : null,
                'openid_connect_redirect_url' => $keycloak['keycloak_redirect_url'] ?? null,
                'openid_connect_client_id' => $keycloak['keycloak_client_id'] ?? null,
                'openid_connect_client_secret' => $keycloak['keycloak_client_secret'] ?? null,
                'openid_connect_trusted_clients' => $keycloak['keycloak_trusted_clients'] ?? null,
                'openid_connect_blacklist_clients' => $keycloak['keycloak_blacklist_clients'] ?? null,
            ];

            $statement = $pearDB->prepare(
                'INSERT INTO options (`key`, `value`) VALUES (:key, :value)'
            );
            foreach ($openIdConnect as $key => $value) {
                if (! is_null($value)) {
                    $statement->bindValue(':key', $key, \PDO::PARAM_STR);
                    $statement->bindValue(':value', $value, \PDO::PARAM_STR);
                    $statement->execute();
                }
            }

            $pearDB->query(
                "DELETE FROM options WHERE options.key IN ('keycloak_enable', 'keycloak_mode', 'keycloak_url',
                'keycloak_redirect_url', 'keycloak_realm', 'keycloak_client_id', 'keycloak_client_secret',
                'keycloak_trusted_clients', 'keycloak_blacklist_clients')"
            );

            $pearDB->commit();
            $errorMessage = '';
        } catch (\Exception $e) {
            $pearDB->rollBack();
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage
                . ' - Code : ' . (int) $e->getCode()
                . ' - Error : ' . $e->getMessage()
                . ' - Trace : ' . $e->getTraceAsString()
            );

            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
        }

        // Update-DB-20.10.0-beta.2.sql

        // Add new column
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `platform_topology`
                ADD COLUMN `hostname` varchar(255) NULL AFTER `address`
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE `cfg_nagios`
                SET `illegal_object_name_chars` = '~!$%^&*"|''<>?,()='
                WHERE `illegal_object_name_chars` = '~!$%^&amp;*&quot;|&#039;&lt;&gt;?,()='
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE `cfg_nagios`
                SET `illegal_macro_output_chars` = '`~$^&"|''<>'
                WHERE `illegal_macro_output_chars` = '`~$^&amp;&quot;|&#039;&lt;&gt;'
                SQL
        );

        // Update-20.10.0-beta.2.post.php

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 20.10.0-beta.2.post : ';

        /**
         * Queries needing exception management and rollback if failing.
         */
        try {
            $pearDB->beginTransaction();
            // Remove data inserted in 20.10.0-beta1
            $pearDB->query('DELETE FROM `platform_topology`');

            /**
             * register server to 'platform_status' table.
             */
            // Check if the server is a Remote or a Central
            $type = 'central';
            $serverType = $pearDB->query("
                SELECT `value` FROM `informations`
                WHERE `key` = 'isRemote'
            ");
            if ('yes' === $serverType->fetch()['value']) {
                $type = 'remote';
            }
            // Check if the server is enabled
            $errorMessage = "Unable to find the server in 'nagios_server' table.";
            $serverQuery = $pearDB->query("
                SELECT `id`, `name` FROM nagios_server
                WHERE localhost = '1' AND ns_activate = '1'
            ");

            $hostName = gethostname() ?: null;

            // Insert the server in 'platform_topology' table
            if ($row = $serverQuery->fetch()) {
                $errorMessage = "Unable to insert server in 'platform_topology' table.";
                $stmt = $pearDB->prepare('
                    INSERT INTO `platform_topology` (`address`, `name`, `hostname`, `type`, `parent_id`, `server_id`)
                    VALUES (:centralAddress, :name, :hostname, :type, NULL, :id)
                ');
                $stmt->bindValue(':centralAddress', $_SERVER['SERVER_ADDR'], \PDO::PARAM_STR);
                $stmt->bindValue(':name', $row['name'], \PDO::PARAM_STR);
                $stmt->bindValue(':hostname', $hostName, \PDO::PARAM_STR);
                $stmt->bindValue(':type', $type, \PDO::PARAM_STR);
                $stmt->bindValue(':id', (int) $row['id'], \PDO::PARAM_INT);
                $stmt->execute();
            }

            // get topology local server id
            $localStmt = $pearDB->query("
                SELECT `platform_topology`.`id` FROM `platform_topology`
                INNER JOIN nagios_server
                ON `platform_topology`.`server_id` = `nagios_server`.`id`
                WHERE `nagios_server`.`localhost` = '1'
            ");
            $parentId = $localStmt->fetchColumn();

            // get nagios_server children
            $childStmt = $pearDB->query(
                "SELECT `id`, `name`, `ns_ip_address`, `remote_id`
                FROM nagios_server WHERE localhost != '1' ORDER BY `remote_id`"
            );
            while ($row = $childStmt->fetch()) {
                // check for remote or poller child types
                $remoteServerQuery = $pearDB->prepare(
                    'SELECT ns.id FROM nagios_server ns
                    INNER JOIN remote_servers rs ON rs.ip = ns.ns_ip_address WHERE ip = :ipAddress'
                );
                $remoteServerQuery->bindValue(':ipAddress', $row['ns_ip_address'], \PDO::PARAM_STR);
                $remoteServerQuery->execute();
                $remoteId = $remoteServerQuery->fetchColumn();
                if (! empty($remoteId)) {
                    // is remote
                    $serverType = 'remote';
                    $parent = $parentId;
                } else {
                    $serverType = 'poller';
                    $findParent = $pearDB->prepare('
                        SELECT id from platform_topology WHERE `server_id` = :remoteId
                    ');
                    $findParent->bindValue(':remoteId', (int) $row['remote_id'], \PDO::PARAM_INT);
                    $findParent->execute();
                    $parent = $findParent->fetchColumn();
                    if ($parent === false) {
                        continue;
                    }
                }

                $errorMessage = 'Unable to insert ' . $serverType . ':' . $row['name'] . " in 'topology' table.";
                $stmt = $pearDB->prepare(
                    'INSERT INTO `platform_topology` (`address`, `name`, `type`, `parent_id`, `server_id`)
                    VALUES (:centralAddress, :name, :serverType, :parent, :id)'
                );
                $stmt->bindValue(':centralAddress', $row['ns_ip_address'], \PDO::PARAM_STR);
                $stmt->bindValue(':name', $row['name'], \PDO::PARAM_STR);
                $stmt->bindValue(':serverType', $serverType, \PDO::PARAM_STR);
                $stmt->bindValue(':parent', (int) $parent, \PDO::PARAM_INT);
                $stmt->bindValue(':id', (int) $row['id'], \PDO::PARAM_INT);
                $stmt->execute();
            }

            $pearDB->commit();
            $errorMessage = '';
        } catch (\Exception $e) {
            $pearDB->rollBack();
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage
                . ' - Code : ' . (int) $e->getCode()
                . ' - Error : ' . $e->getMessage()
                . ' - Trace : ' . $e->getTraceAsString()
            );

            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
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
