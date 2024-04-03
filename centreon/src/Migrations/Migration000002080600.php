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

class Migration000002080600 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '2.8.6';

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


        /* Update-DB-2.8.6.sql */

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

        // Change state colors
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '#88b917'
                WHERE `key` IN ('color_up', 'color_ok')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '#e00b3d'
                WHERE `key` IN ('color_down', 'color_critical', 'color_host_down')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '#818285'
                WHERE `key` IN ('color_unreachable', 'color_host_unreachable')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '#ff9a13'
                WHERE `key` = 'color_warning'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '#2ad1d4'
                WHERE `key` = 'color_pending'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '#ae9500'
                WHERE `key` = 'color_ack'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '#bcbdc0'
                WHERE `key` = 'color_unknown'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `options`
                SET `value` = '#cc99ff'
                WHERE `key` = 'color_downtime'
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
