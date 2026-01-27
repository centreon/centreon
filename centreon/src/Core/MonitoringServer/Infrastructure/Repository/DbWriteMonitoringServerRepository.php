<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\MonitoringServer\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Model\MonitoringServer;

class DbWriteMonitoringServerRepository extends AbstractRepositoryRDB implements WriteMonitoringServerRepositoryInterface
{
    use LoggerTrait;
    use SqlMultipleBindTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function notifyConfigurationChange(int $monitoringServerId): void
    {
        $this->debug('Signal configuration change on monitoring server with ID #' . $monitoringServerId);

        $request = $this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`nagios_server`
                SET `updated` =  '1'
                WHERE `id` = :monitoringServerId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':monitoringServerId', $monitoringServerId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function notifyConfigurationChanges(array $monitoringServerIds): void
    {
        if ($monitoringServerIds === []) {
            return;
        }

        $this->debug('Signal configuration change on monitoring servers with IDs ' . implode(', ', $monitoringServerIds));

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($monitoringServerIds, ':monitoring_server_id_');

        $request = $this->translateDbName(
            <<<SQL
                UPDATE `:db`.`nagios_server`
                SET `updated` =  '1'
                WHERE `id` IN ({$bindQuery})
                SQL
        );
        $statement = $this->db->prepare($request);

        foreach ($bindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    public function update(MonitoringServer $monitoringServer): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`nagios_server`
                SET `name` = :name,
                    `engine_reload_command` = :engineReloadCommand,
                    `engine_restart_command` = :engineRestartCommand,
                    `engine_stop_command` = :engineStopCommand,
                    `engine_start_command` = :engineStartCommand,
                    `broker_reload_command` = :brokerReloadCommand
                WHERE `id` = :monitoringServerId
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':name', $monitoringServer->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':engineReloadCommand', $monitoringServer->getEngineReloadCommand(), \PDO::PARAM_STR);
        $statement->bindValue(':engineRestartCommand', $monitoringServer->getEngineRestartCommand(), \PDO::PARAM_STR);
        $statement->bindValue(':engineStopCommand', $monitoringServer->getEngineStopCommand(), \PDO::PARAM_STR);
        $statement->bindValue(':engineStartCommand', $monitoringServer->getEngineStartCommand(), \PDO::PARAM_STR);
        $statement->bindValue(':brokerReloadCommand', $monitoringServer->getBrokerReloadCommand(), \PDO::PARAM_STR);
        $statement->bindValue(':monitoringServerId', $monitoringServer->getId(), \PDO::PARAM_INT);
        $statement->execute();
    }

    public function updateAllEncryptionReadyFromRealtime(): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.nagios_server ns
                    INNER JOIN `:dbstg`.instances i
                    ON ns.id = i.instance_id
                SET ns.is_encryption_ready = i.is_encryption_ready
                SQL
        ));
        $statement->execute();
    }
}
