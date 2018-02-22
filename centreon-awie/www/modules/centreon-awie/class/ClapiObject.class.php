<?php
/**
 * Copyright 2018 Centreon
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
 */


class ClapiObject
{

    protected $dbConfigCentreon = array();
    protected $dbConfigCentreonStorage = array();
    protected $db;
    protected $dbMon;
    protected $clapiParameters = array();
    protected $clapiConnector;

    public function __construct($dbConfig, $clapiParameters)
    {
        $this->clapiParameters = $clapiParameters;
        $this->buildDatabaseConfigurations($dbConfig);
        $this->initCentreonConnection();
        $this->initCentreonStorageConnection();
        $this->connectToClapi();

    }

    /**
     * @param $dbConfig
     */
    private function buildDatabaseConfigurations($dbConfig)
    {
        $this->dbConfigCentreon = $dbConfig;
        $dbConfig['dbname'] = $dbConfig['storage'];
        $this->dbConfigCentreonStorage = $dbConfig;
    }

    /**
     * @param $key
     * @param $value
     */
    public function addClapiParameter($key, $value)
    {
        $this->clapiParameters[$key] = $value;
    }

    /**
     *
     */
    private function initCentreonConnection()
    {
        $this->db = Centreon_Db_Manager::factory('centreon', 'pdo_mysql', $this->dbConfigCentreon);
        $this->testDatabaseConnection('db');
    }

    /**
     *
     */
    private function initCentreonStorageConnection()
    {
        $this->dbMon = Centreon_Db_Manager::factory('storage', 'pdo_mysql', $this->dbConfigCentreonStorage);
        $this->testDatabaseConnection('dbMon');
    }

    /**
     * @param $dbName
     */
    private function testDatabaseConnection($dbName)
    {
        try {
            $this->$dbName->getConnection();
        } catch (Exception $e) {
            echo sprintf("Could not connect to database. Check your configuration file %s\n",
                _CENTREON_ETC_ . '/centreon.conf.php');
        }
    }

    /**
     *
     */
    private function connectToClapi()
    {
        \CentreonClapi\CentreonUtils::setUserName($this->clapiParameters['username']);
        $this->clapiConnector = \CentreonClapi\CentreonAPI::getInstance(
            '',
            '',
            '',
            _CENTREON_PATH_,
            $this->clapiParameters
        );
    }

    /**
     * @return mixed
     */
    public function export()
    {
        $this->clapiConnector->setOption($this->clapiParameters);
        $export = $this->clapiConnector->export();
        return $export;
    }

    /**
     * @return mixed
     */
    public function import($fileName)
    {
        $import = $this->clapiConnector->import($fileName);
        return $import;
    }


}
