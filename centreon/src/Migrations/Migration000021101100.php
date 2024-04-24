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

class Migration000021101100 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '21.10.11';

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
        $pearDBO = $this->dependencyInjector['realtime_db'];

        // Update-21.10.11.php

        $centreonLog = new \CentreonLog();

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 21.10.11: ';
        $errorMessage = '';

        try {
            $errorMessage = "Impossible to update 'hosts' table";
            if (! str_contains(mb_strtolower($pearDBO->getColumnType('hosts', 'notification_number')), 'bigint')) {
                $pearDBO->beginTransaction();
                $pearDBO->query('UPDATE `hosts` SET `notification_number`= 0 WHERE `notification_number`< 0');
                $pearDBO->query('ALTER TABLE `hosts` MODIFY `notification_number` BIGINT(20) UNSIGNED DEFAULT NULL');
            }

            $errorMessage = "Impossible to update 'services' table";
            if (! str_contains(mb_strtolower($pearDBO->getColumnType('services', 'notification_number')), 'bigint')) {
                $pearDBO->beginTransaction();
                $pearDBO->query('UPDATE `services` SET `notification_number`= 0 WHERE `notification_number`< 0');
                $pearDBO->query('ALTER TABLE `services` MODIFY `notification_number` BIGINT(20) UNSIGNED DEFAULT NULL');
            }
        } catch (\Exception $e) {
            if ($pearDBO->inTransaction()) {
                $pearDBO->rollBack();
            }

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
