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
class CentreonDbConfig
{

    /**
     * @param string $dbHostCentreon
     * @param string $dbHostCentreonStorage
     * @param string $dbUser
     * @param string $dbPassword
     * @param string $dbNameCentreon
     * @param string $dbNameCentreonStorage
     * @param int $dbPort
     */
    public function __construct(
        private string $dbHostCentreon,
        private string $dbHostCentreonStorage,
        private string $dbUser,
        private string $dbPassword,
        private string $dbNameCentreon,
        private string $dbNameCentreonStorage,
        private int $dbPort,
    ) {}

    /**
     * @return string
     */
    public function getDbHostCentreon(): string
    {
        return $this->dbHostCentreon;
    }

    /**
     * @param string $dbHostCentreon
     * @return $this
     */
    public function setDbHostCentreon(string $dbHostCentreon): CentreonDbConfig
    {
        $this->dbHostCentreon = $dbHostCentreon;
        return $this;
    }

    /**
     * @return string
     */
    public function getDbHostCentreonStorage(): string
    {
        return $this->dbHostCentreonStorage;
    }

    /**
     * @param string $dbHostCentreonStorage
     * @return $this
     */
    public function setDbHostCentreonStorage(string $dbHostCentreonStorage): CentreonDbConfig
    {
        $this->dbHostCentreonStorage = $dbHostCentreonStorage;
        return $this;
    }

    /**
     * @return string
     */
    public function getDbUser(): string
    {
        return $this->dbUser;
    }

    /**
     * @param string $dbUser
     * @return $this
     */
    public function setDbUser(string $dbUser): CentreonDbConfig
    {
        $this->dbUser = $dbUser;
        return $this;
    }

    /**
     * @return string
     */
    public function getDbPassword(): string
    {
        return $this->dbPassword;
    }

    /**
     * @param string $dbPassword
     * @return $this
     */
    public function setDbPassword(string $dbPassword): CentreonDbConfig
    {
        $this->dbPassword = $dbPassword;
        return $this;
    }

    /**
     * @return string
     */
    public function getDbNameCentreon(): string
    {
        return $this->dbNameCentreon;
    }

    /**
     * @param string $dbNameCentreon
     * @return $this
     */
    public function setDbNameCentreon(string $dbNameCentreon): CentreonDbConfig
    {
        $this->dbNameCentreon = $dbNameCentreon;
        return $this;
    }

    /**
     * @return string
     */
    public function getDbNameCentreonStorage(): string
    {
        return $this->dbNameCentreonStorage;
    }

    /**
     * @param string $dbNameCentreonStorage
     * @return $this
     */
    public function setDbNameCentreonStorage(string $dbNameCentreonStorage): CentreonDbConfig
    {
        $this->dbNameCentreonStorage = $dbNameCentreonStorage;
        return $this;
    }

    /**
     * @return int
     */
    public function getDbPort(): int
    {
        return $this->dbPort;
    }

    /**
     * @param int $dbPort
     * @return $this
     */
    public function setDbPort(int $dbPort): CentreonDbConfig
    {
        $this->dbPort = $dbPort;
        return $this;
    }
}
