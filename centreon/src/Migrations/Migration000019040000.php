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

class Migration000019040000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '19.04.0';

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

        // Update-19.04.0.php

        $centreonLog = new \CentreonLog();

        /**
         * New configuration options for Centreon Engine.
         */
        try {
            $pearDB->query('SET SESSION innodb_strict_mode=OFF');
            if (! $pearDB->isColumnExist('cfg_nagios', 'enable_macros_filter')) {
                $pearDB->query(
                    "ALTER TABLE `cfg_nagios` ADD COLUMN `enable_macros_filter` ENUM('0', '1') DEFAULT '0'"
                );
            }
            if (! $pearDB->isColumnExist('cfg_nagios', 'macros_filter')) {
                $pearDB->query(
                    "ALTER TABLE `cfg_nagios` ADD COLUMN `macros_filter` TEXT DEFAULT ('')"
                );
            }
        } catch (\PDOException $e) {
            $centreonLog->insertLog(
                2,
                'UPGRADE : 19.04.0 Unable to modify centreon engine in the database'
            );

            throw $e;
        } finally {
            $pearDB->query('SET SESSION innodb_strict_mode=ON');
        }

        // Update-DB-19.04.0.sql

        // updating the side menus
        // removing or renaming unfriendly titles from performance menu
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_name = "Graphs"
                AND topology_parent = 204
                AND topology_page IS NULL
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_name = "Templates"
                AND topology_parent = 204
                AND topology_page IS NULL
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_name = "Parameters"
                WHERE topology_name = "Virtuals"
                AND topology_parent = 204
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_name = "Virtual Metrics"
                WHERE topology_page = 20408
                SQL
        );

        // grouping the menus under Parameters
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_group = 46
                WHERE topology_page IN (20404, 20405, 20408)
                SQL
        );

        // removing unfriendly titles from Configuration menu
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_name = "Services"
                AND topology_parent = 602
                AND topology_page IS NULL
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_name = "Meta Services"
                AND topology_parent = 602
                AND topology_page IS NULL
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_name = "Commands"
                AND topology_parent = 608
                AND topology_page IS NULL
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_name = "Connectors"
                AND topology_parent = 608
                AND topology_page IS NULL
                SQL
        );

        // removing the CSS page from Administration menu
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_name = "CSS"
                AND topology_parent = 501
                AND topology_page = 50116
                SQL
        );

        // removing unfriendly title from Logs menu
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_name = "Visualisation"
                AND topology_parent = 508
                AND topology_page = 50801
                SQL
        );

        // Remove unused options
        $pearDB->query(
            <<<'SQL'
                DELETE FROM options
                WHERE options.key IN ('rrdtool_title_font', 'rrdtool_title_fontsize', 'rrdtool_unit_font', 'rrdtool_unit_fontsize', 'rrdtool_axis_font', 'rrdtool_axis_fontsize', 'rrdtool_watermark_font', 'rrdtool_watermark_fontsize', 'rrdtool_legend_font', 'rrdtool_legend_fontsize')
                SQL
        );

        // Add new Extensions Page entry
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_group`)
                VALUES ('Manager', '/administration/extensions/manager', '1', '1', 507, 50709, 1)
                SQL
        );

        // Remove old Extensions Page menus
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `topology`
                WHERE (`topology_page` = '50701')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `topology`
                WHERE (`topology_page` = '50703')
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
