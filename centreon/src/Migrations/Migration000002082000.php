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

class Migration000002082000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.20';

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

        // Update-2.8.20.php

        // Set default poller with localhost if it is not set
        $res = $pearDB->query('SELECT `name` FROM `nagios_server` WHERE `is_default` = 1');

        if ($res->rowCount() === 0) {
            $res = $pearDB->query("UPDATE `nagios_server` SET `is_default` = 1 WHERE `localhost` = '1'");
        }

        // Update-DB-2.8.20.sql

        $pearDB->query(
            <<<'SQL'
                UPDATE `cb_fieldgroup`
                SET `groupname` = 'lua_parameter',`displayname` = 'lua parameter'
                WHERE `groupname` = 'lua_parameters'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `cb_type_field_relation`
                SET `jshook_arguments` = '{"target": "lua_parameter__value_%d"}'
                WHERE `jshook_arguments` = '{"target": "lua_parameters__value_%d"}'
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
