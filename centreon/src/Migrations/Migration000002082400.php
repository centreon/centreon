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

class Migration000002082400 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.24';

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

        // Update-2.8.24.php

        // Create tempory table to delete duplicate entries
        $query = 'CREATE TABLE `centreon_acl_new` ( '
            . '`group_id` int(11) NOT NULL, '
            . '`host_id` int(11) NOT NULL, '
            . '`service_id` int(11) DEFAULT NULL, '
            . 'UNIQUE KEY (`group_id`,`host_id`,`service_id`), '
            . 'KEY `index1` (`host_id`,`service_id`,`group_id`) '
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8 ';
        $pearDBO->query($query);

        /**
         * Checking if centAcl.php is running and waiting 2min for it to stop before locking cron_operation table.
         */
        $query = "SELECT running FROM cron_operation WHERE `name` = 'centAcl.php'";
        $i = 0;
        while ($i < 120) {
            $i++;
            $result = $pearDB->query($query);
            while ($row = $result->fetchRow()) {
                if ($row['running'] === '1') {
                    sleep(1);
                } else {
                    break 2;
                }
            }
        }

        /**
         * Lock centAcl cron during upgrade.
         */
        $query = "UPDATE cron_operation SET running = '1' WHERE `name` = 'centAcl.php'";
        $pearDB->query($query);

        /**
         * Copy data from old table to new table with duplicate entries deletion.
         */
        $query = 'INSERT INTO centreon_acl_new (group_id, host_id, service_id) '
            . 'SELECT group_id, host_id, service_id FROM centreon_acl '
            . 'GROUP BY group_id, host_id, service_id';
        $pearDBO->query($query);

        /**
         * Drop old table with duplicate entries.
         */
        $query = 'DROP TABLE centreon_acl';
        $pearDBO->query($query);

        /**
         * Rename temporary table to stable table.
         */
        $query = 'ALTER TABLE centreon_acl_new RENAME TO centreon_acl';
        $pearDBO->query($query);

        /**
         * Unlock centAcl cron during upgrade.
         */
        $query = "UPDATE cron_operation SET running = '0' WHERE `name` = 'centAcl.php'";
        $pearDB->query($query);
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
