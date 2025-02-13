<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Adaptation\Database\Model;

use Adaptation\Database\Enum\ConnectionDriverEnum;

/**
 * Class
 *
 * @class   ConnectionConfig
 * @package Adaptation\Database\Model
 */
final readonly class ConnectionConfig
{
    /**
     * ConnectionConfig constructor
     *
     * @param string               $host
     * @param string               $user
     * @param string               $password
     * @param string               $databaseName
     * @param ConnectionDriverEnum $driver
     * @param string               $charset
     * @param int                  $port
     */
    public function __construct(
        private string $host,
        private string $user,
        private string $password,
        private string $databaseName,
        private int $port = 0,
        private string $charset = '',
        private ConnectionDriverEnum $driver = ConnectionDriverEnum::DRIVER_MYSQL
    ) {}

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @return ConnectionDriverEnum
     */
    public function getDriver(): ConnectionDriverEnum
    {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getMysqlDsn(): string
    {
        return sprintf(
            "mysql:dbname=%s;host=%s;port=%s",
            $this->getDatabaseName(),
            $this->getHost(),
            $this->getPort()
        );
    }

    /**
     * @return string
     */
    public function getOracleDsn(): string
    {
        return sprintf(
            "oci:dbname=//%s:%s/%s",
            $this->getHost(),
            $this->getPort(),
            $this->getDatabaseName()
        );
    }

    /**
     * @return string
     */
    public function getPgsqlDsn(): string
    {
        return sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s",
            $this->getHost(),
            $this->getPort(),
            $this->getDatabaseName(),
            $this->getUser(),
            $this->getPassword(),
        );
    }

}
