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

/**
 * Class
 *
 * @class CentreonPdo
 */
class CentreonPdo extends PDO
{
    /**
     * CentreonPdo constructor
     *
     * @param $dsn
     * @param $username
     * @param $password
     * @param $options
     *
     * @throws PDOException
     */
    public function __construct($dsn, $username = null, $password = null, $options = [])
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['CentreonPdoStatement', [$this]]);
    }

    /**
     * @return void
     */
    public function disconnect()
    {
    }

    /**
     * returns error info
     *
     * @return string
     */
    public function toString()
    {
        $errString = '';
        $errTab = $this->errorInfo();
        if (count($errTab)) {
            $errString = implode(';', $errTab);
        }

        return $errString;
    }
}
