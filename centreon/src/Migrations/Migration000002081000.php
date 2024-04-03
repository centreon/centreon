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

class Migration000002081000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '2.8.10';

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


        /* Update-2.8.10.php */

        $res = $pearDB->query(
            "SELECT * " .
            "FROM INFORMATION_SCHEMA.COLUMNS " .
            "WHERE TABLE_NAME = 'nagios_server' " .
            "AND COLUMN_NAME = 'description' "
        );
        if ($res->rowCount() > 0) {
            $pearDB->query("ALTER TABLE `nagios_server` DROP COLUMN `description`");
        }


        /* Update-DB-2.8.10.sql */

        $pearDB->query(
            <<<'SQL'
                DELETE FROM nagios_macro
                WHERE macro_name IN ('$_HOSTLOCATION$', '$_HOSTHOST_ID$', '$_SERVICESERVICE_ID$')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `nagios_macro` (`macro_name`)
                VALUES ('$HOSTID$')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `nagios_macro` (`macro_name`)
                VALUES ('$SERVICEID$')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `nagios_macro` (`macro_name`)
                VALUES ('$HOSTTIMEZONE$')
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cfg_nagios`
                ADD COLUMN `use_timezone` int(11) unsigned DEFAULT NULL AFTER `nagios_name`,
                ADD CONSTRAINT `cfg_nagios_ibfk_27` FOREIGN KEY (`use_timezone`) REFERENCES `timezone` (`timezone_id`) ON DELETE CASCADE
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
