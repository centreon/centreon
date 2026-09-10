<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\Upgrade\Infrastructure\Legacy;

use Adaptation\Database\Connection\Model\ConnectionConfig;

/**
 * Temporary bridge, kept isolated under Infrastructure/Legacy so it stays easy to trace and remove.
 *
 * Core (Update-*.php) and module (upgrade.php) upgrade scripts are exposed a $pearDB / $pearDBO
 * connection and still call legacy CentreonDB/PDO methods (query, prepare, beginTransaction,
 * isColumnExist, lastInsertId...) that the ConnectionInterface-only DbalConnectionAdapter does not
 * provide. This factory builds the superset \CentreonDB connection those scripts require.
 *
 * TODO: remove once every upgrade script is migrated to ConnectionInterface.
 */
final readonly class LegacyConnectionFactory
{
    public function __construct(
        private ConnectionConfig $connectionConfig,
    ) {
    }

    /**
     * Builds the connection to the configuration database, exposed to upgrade scripts as $pearDB.
     */
    public function createConfigurationConnection(): \CentreonDB
    {
        return new \CentreonDB(connectionConfig: $this->connectionConfig);
    }

    /**
     * Builds the connection to the real-time (storage) database, exposed to upgrade scripts as $pearDBO.
     */
    public function createRealtimeConnection(): \CentreonDB
    {
        return new \CentreonDB(connectionConfig: new ConnectionConfig(
            host: $this->connectionConfig->getHost(),
            user: $this->connectionConfig->getUser(),
            password: $this->connectionConfig->getPassword(),
            databaseNameConfiguration: $this->connectionConfig->getDatabaseNameRealTime(),
            databaseNameRealTime: $this->connectionConfig->getDatabaseNameRealTime(),
            port: $this->connectionConfig->getPort(),
            charset: $this->connectionConfig->getCharset(),
            driver: $this->connectionConfig->getDriver(),
        ));
    }
}
