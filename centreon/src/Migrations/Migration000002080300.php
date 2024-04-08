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

class Migration000002080300 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.3';

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

        // Update-DB-2.8.3.sql

        // Move recurrent downtimes configuration to monitoring menu
        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology
                WHERE topology_page = 60216
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_page = 21003
                WHERE topology_page = 60106
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET topology_parent = 210
                WHERE topology_page = 21003
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE topology
                SET
                    topology_url = './include/monitoring/recurrentDowntime/downtime.php',
                    topology_name = 'Recurrent downtimes',
                    topology_order = 20
                WHERE topology_page = 21003
                SQL
        );

        // broker option
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `options`
                WHERE `key` = 'broker'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `options` (`key`, `value`)
                VALUES ('broker', 'broker')
                SQL
        );

        // Remove relations between contact templates and contactgroups
        $pearDB->query(
            <<<'SQL'
                DELETE FROM contactgroup_contact_relation
                WHERE contact_contact_id IN (
                    SELECT contact_id FROM contact WHERE contact_register = '0'
                )
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
