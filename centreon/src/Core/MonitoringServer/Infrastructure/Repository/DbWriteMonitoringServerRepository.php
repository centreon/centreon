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

namespace Core\MonitoringServer\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;

class DbWriteMonitoringServerRepository extends AbstractRepositoryRDB implements WriteMonitoringServerRepositoryInterface
{
    use LoggerTrait;

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
}