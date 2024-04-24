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

class Migration000022040500 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '22.04.5';

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

        // Update-22.04.5.php

        $centreonLog = new \CentreonLog();

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 22.04.5: ';
        $errorMessage = '';

        /**
         * Manage relations between remote servers and nagios servers.
         *
         * @param \CentreonDB $pearDB
         */
        $migrateRemoteServerRelations = function (\CentreonDB $pearDB): void
        {
            $processedIps = [];

            $selectServerStatement = $pearDB->prepare(
                'SELECT id FROM nagios_server WHERE ns_ip_address = :ip_address'
            );
            $deleteRemoteStatement = $pearDB->prepare(
                'DELETE FROM remote_servers WHERE id = :id'
            );
            $updateRemoteStatement = $pearDB->prepare(
                'UPDATE remote_servers SET server_id = :server_id WHERE id = :id'
            );

            $result = $pearDB->query(
                'SELECT id, ip FROM remote_servers'
            );
            while ($remote = $result->fetch()) {
                $remoteIp = $remote['ip'];
                $remoteId = $remote['id'];
                if (in_array($remoteIp, $processedIps, true)) {
                    $deleteRemoteStatement->bindValue(':id', $remoteId, \PDO::PARAM_INT);
                    $deleteRemoteStatement->execute();
                }

                $processedIps[] = $remoteIp;

                $selectServerStatement->bindValue(':ip_address', $remoteIp, \PDO::PARAM_STR);
                $selectServerStatement->execute();
                if ($server = $selectServerStatement->fetch()) {
                    $updateRemoteStatement->bindValue(':server_id', $server['id'], \PDO::PARAM_INT);
                    $updateRemoteStatement->bindValue(':id', $remoteId, \PDO::PARAM_INT);
                    $updateRemoteStatement->execute();
                } else {
                    $deleteRemoteStatement->bindValue(':id', $remoteId, \PDO::PARAM_INT);
                    $deleteRemoteStatement->execute();
                }
            }
        };

        try {
            if ($pearDB->isColumnExist('remote_servers', 'server_id') === 0) {
                $errorMessage = "Unable to add 'server_id' column to remote_servers table";
                $pearDB->query(
                    'ALTER TABLE remote_servers
                    ADD COLUMN `server_id` int(11) NOT NULL'
                );

                $migrateRemoteServerRelations($pearDB);

                $errorMessage = 'Unable to add foreign key constraint of remote_servers.server_id';
                $pearDB->query(
                    'ALTER TABLE remote_servers
                    ADD CONSTRAINT `remote_server_nagios_server_ibfk_1`
                    FOREIGN KEY(`server_id`) REFERENCES `nagios_server` (`id`)
                    ON DELETE CASCADE'
                );
            }
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
