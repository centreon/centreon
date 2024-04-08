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

class Migration000019040100 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '19.04.1';

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

        // Update-19.04.1.php

        $centreonLog = new \CentreonLog();

        try {
            $pearDB->query('SET SESSION innodb_strict_mode=OFF');

            // Add HTTPS connexion to Remote Server
            if (! $pearDB->isColumnExist('remote_servers', 'http_method')) {
                $pearDB->query(
                    "ALTER TABLE remote_servers ADD COLUMN `http_method` enum('http','https') NOT NULL DEFAULT 'http'"
                );
            }
            if (! $pearDB->isColumnExist('remote_servers', 'http_port')) {
                $pearDB->query(
                    'ALTER TABLE remote_servers ADD COLUMN `http_port` int(11) NULL DEFAULT NULL'
                );
            }
            if (! $pearDB->isColumnExist('remote_servers', 'no_check_certificate')) {
                $pearDB->query(
                    "ALTER TABLE remote_servers ADD COLUMN `no_check_certificate` enum('0','1') NOT NULL DEFAULT '0'"
                );
            }
            if (! $pearDB->isColumnExist('remote_servers', 'no_proxy')) {
                $pearDB->query(
                    "ALTER TABLE remote_servers ADD COLUMN `no_proxy` enum('0','1') NOT NULL DEFAULT '0'"
                );
            }
        } catch (\PDOException $e) {
            $centreonLog->insertLog(
                2,
                'UPGRADE : Unable to process 19.04.1 upgrade'
            );

            throw $e;
        } finally {
            $pearDB->query('SET SESSION innodb_strict_mode=ON');
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
