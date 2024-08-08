<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

/**
 * Class CentreonDbConfig
 *
 * @class CentreonDbConfig
 */
final class CentreonDbConfig
{

    /**
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbPassword
     * @param string $dbName
     * @param int $dbPort
     */
    public function __construct(
        public readonly string $dbHost,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $dbName,
        public readonly int $dbPort
    ) {
    }

    /**
     * @return string
     */
    public function getMysqlDsn(): string
    {
        return sprintf(
            "mysql:dbname=%s;host=%s;port=%s",
            $this->dbName,
            $this->dbHost,
            $this->dbPort
        );
    }

    /**
     * @return string
     */
    public function getOracleDsn(): string
    {
        return sprintf(
            "oci:dbname=//%s:%s/%s",
            $this->dbHost,
            $this->dbPort,
            $this->dbName
        );
    }

    /**
     * @return string
     */
    public function getPgsqlDsn(): string
    {
        return sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s",
            $this->dbHost,
            $this->dbPort,
            $this->dbName,
            $this->dbUser,
            $this->dbPassword,
        );
    }
}
