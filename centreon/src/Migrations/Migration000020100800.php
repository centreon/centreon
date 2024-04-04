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

class Migration000020100800 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '20.10.8';

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

        /* Update-20.10.8.php */

        $centreonLog = new \CentreonLog();

        //error specific content
        $versionOfTheUpgrade = 'UPGRADE - 20.10.8 : ';

        /**
         * Query with transaction
         */
        try {
            $pearDB->beginTransaction();
            /**
             * Retreive Meta Host Id
             */
            $statement = $pearDB->query(
                "SELECT `host_id` FROM `host` WHERE `host_name` = '_Module_Meta'"
            );

            /*
            * Add missing relation
            */
            if ($moduleMeta = $statement->fetch()) {
                $moduleMetaId = $moduleMeta['host_id'];
                $errorMessage = "Unable to add relation between Module Meta and default poller.";
                $statement = $pearDB->prepare(
                    "INSERT INTO ns_host_relation(`nagios_server_id`, `host_host_id`)
                    VALUES(
                        (SELECT id FROM nagios_server WHERE localhost = '1'),
                        (:moduleMetaId)
                    )
                    ON DUPLICATE KEY UPDATE nagios_server_id = nagios_server_id"
                );
                $statement->bindValue(':moduleMetaId', (int) $moduleMetaId, \PDO::PARAM_INT);
                $statement->execute();
            }
            $pearDB->commit();
        } catch (\Exception $e) {
            $pearDB->rollBack();
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage .
                " - Code : " . (int)$e->getCode() .
                " - Error : " . $e->getMessage() .
                " - Trace : " . $e->getTraceAsString()
            );
            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int)$e->getCode(), $e);
        }


        /* Update-DB-20.10.8.sql */

        // Delete obsolete topologies
        $pearDB->query(
            <<<'SQL'
                DELETE FROM `topology`
                WHERE `topology_page` IN (6090901, 6090902)
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
