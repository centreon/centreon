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

// file centreon.config.php may not exist in test environment
$configFile = realpath(__DIR__ . '/../../config/centreon.config.php');
if ($configFile !== false) {
    include_once $configFile;
}

require_once realpath(__DIR__ . '/centreonDB.class.php');

/**
 * Class
 *
 * @class CentreonDBInstance
 */
class CentreonDBInstance
{
    /** @var CentreonDBInstance */
    private static $dbCentreonInstance;

    /** @var CentreonDBInstance */
    private static $dbCentreonStorageInstance;

    /** @var CentreonDB */
    private $instance;

    /**
     * CentreonDBInstance constructor.
     *
     * @param string $db
     *
     * @throws Exception
     */
    private function __construct($db = 'centreon')
    {
        $this->instance = new CentreonDB($db);
    }

    /**
     * @return CentreonDB
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @return CentreonDB
     */
    public static function getDbCentreonInstance()
    {
        if (is_null(self::$dbCentreonInstance)) {
            self::$dbCentreonInstance = new CentreonDBInstance('centreon');
        }

        return self::$dbCentreonInstance->getInstance();
    }

    /**
     * @return CentreonDB
     */
    public static function getDbCentreonStorageInstance()
    {
        if (is_null(self::$dbCentreonStorageInstance)) {
            self::$dbCentreonStorageInstance = new CentreonDBInstance('centstorage');
        }

        return self::$dbCentreonStorageInstance->getInstance();
    }
}
