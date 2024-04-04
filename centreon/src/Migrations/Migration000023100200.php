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

class Migration000023100200 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '23.10.2';

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


        /* Update-23.10.2.php */

        $centreonLog = new \CentreonLog();

        //error specific content
        $versionOfTheUpgrade = 'UPGRADE - 23.10.2: ';
        $errorMessage = '';

        $createNotificationContactgroupRelationTable = function (\CentreonDB $pearDB) use (&$errorMessage): void
        {
            $errorMessage = 'Unable to create table: notification_contactgroup_relation';
            $pearDB->query(
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS `notification_contactgroup_relation` (
                    `notification_id` INT UNSIGNED NOT NULL,
                    `contactgroup_id` INT NOT NULL,
                    UNIQUE KEY `notification_contactgroup_relation_unique_index` (`notification_id`,`contactgroup_id`),
                    CONSTRAINT `notification_contactgroup_relation_notification_id`
                        FOREIGN KEY (`notification_id`)
                        REFERENCES `notification` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `notification_contactgroup_relation_contactgroup_id`
                        FOREIGN KEY (`contactgroup_id`)
                        REFERENCES `contactgroup` (`cg_id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                SQL
            );
        };

        try {
            $createNotificationContactgroupRelationTable($pearDB);
        } catch (\Exception $e) {
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
