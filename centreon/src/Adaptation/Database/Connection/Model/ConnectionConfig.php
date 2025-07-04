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

namespace Adaptation\Database\Connection\Model;

use Adaptation\Database\Connection\Enum\ConnectionDriverEnum;

/**
 * Class.
 *
 * @class   ConnectionConfig
 */
final readonly class ConnectionConfig
{
    /**
     * ConnectionConfig constructor.
     *
     * @param string $databaseNameConfiguration Configuration database name of Centreon
     * @param string $databaseNameRealTime Real time database name of Centreon
     */
    public function __construct(
        private string $host,
        private string $user,
        private string $password,
        private string $databaseNameConfiguration,
        private string $databaseNameRealTime,
        private int $port = 3306,
        private string $charset = 'utf8mb4',
        private ConnectionDriverEnum $driver = ConnectionDriverEnum::DRIVER_PDO_MYSQL
    ) {
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getDatabaseNameConfiguration(): string
    {
        return $this->databaseNameConfiguration;
    }

    public function getDatabaseNameRealTime(): string
    {
        return $this->databaseNameRealTime;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getDriver(): ConnectionDriverEnum
    {
        return $this->driver;
    }

    public function getMysqlDsn(): string
    {
        return \sprintf(
            'mysql:dbname=%s;host=%s;port=%s;charset=%s',
            $this->getDatabaseNameConfiguration(),
            $this->getHost(),
            $this->getPort(),
            $this->getCharset()
        );
    }

    public function getOracleDsn(): string
    {
        return \sprintf(
            'oci:dbname=//%s:%s/%s',
            $this->getHost(),
            $this->getPort(),
            $this->getDatabaseNameConfiguration()
        );
    }

    public function getPgsqlDsn(): string
    {
        return \sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
            $this->getHost(),
            $this->getPort(),
            $this->getDatabaseNameConfiguration(),
            $this->getUser(),
            $this->getPassword(),
        );
    }
}
