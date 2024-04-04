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

class Migration000023040500 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '23.04.5';

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


        /* Update-23.04.5.php */

        $centreonLog = new \CentreonLog();

        //error specific content
        $versionOfTheUpgrade = 'UPGRADE - 23.04.5: ';
        $errorMessage = '';

        //Change the type of check_attempt and max_check_attempts columns from table resources
        $errorMessage = "Couldn't modify resources table";
        $alterResourceTableStmnt = "ALTER TABLE resources MODIFY check_attempts SMALLINT UNSIGNED,
            MODIFY max_check_attempts SMALLINT UNSIGNED";

        try {
            $pearDBO->query($alterResourceTableStmnt);
            $errorMessage = '';
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


        /* Update-DB-23.04.5.sql */

        $pearDB->query(
            <<<'SQL'
                UPDATE `topology`
                SET
                    `topology_url` = '/administration/about',
                    `readonly` = '1',
                    `is_react` = '1',
                    `topology_parent` = 5,
                    `topology_order` = 15,
                    `topology_group` = 1
                WHERE `topology_name` = 'About' AND `topology_page` = 506
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
