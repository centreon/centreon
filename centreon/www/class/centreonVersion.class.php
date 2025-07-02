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
 * @class CentreonVersion
 */
class CentreonVersion
{
    /** @var CentreonDB */
    private $db;

    /** @var CentreonDB|null */
    private $dbStorage;

    /**
     * CentreonVersion constructor
     *
     * @param CentreonDB $db
     * @param CentreonDB|null $dbStorage
     */
    public function __construct($db, $dbStorage = null)
    {
        $this->db = $db;

        if (! is_null($dbStorage)) {
            $this->dbStorage = $dbStorage;
        }
    }

    /**
     * Get Centreon core version
     *
     * @throws PDOException
     * @return array
     */
    public function getCore()
    {
        $data = [];

        // Get version of the centreon-web
        $query = 'SELECT i.value FROM informations i '
            . 'WHERE i.key = "version"';
        $result = $this->db->query($query);
        if ($row = $result->fetch()) {
            $data['centreon-web'] = $row['value'];
        }

        // Get version of the centreon-broker
        $cmd = shell_exec('cbd -v');

        if (preg_match('/^.*(.\d+\.\d+\.\d+)$/', $cmd, $matches)) {
            $data['centreon-broker'] = $matches[1];
        }

        // Get version of the centreon-engine
        $queryProgram = 'SELECT DISTINCT instance_id, version AS program_version, engine AS program_name, '
            . '`name` AS instance_name FROM instances WHERE deleted = 0 ';
        $result = $this->dbStorage->query($queryProgram);

        while ($info = $result->fetch()) {
            $data['centreon-engine'] = $info['program_version'];
        }

        return $data;
    }

    /**
     * Get all Centreon modules
     *
     * @throws PDOException
     * @return array
     */
    public function getModules()
    {
        $data = [];

        $query = 'SELECT name, mod_release FROM modules_informations';
        $result = $this->db->query($query);
        while ($row = $result->fetch()) {
            $data[$row['name']] = $row['mod_release'];
        }

        return $data;
    }

    /**
     * Get all Centreon widgets
     *
     * @throws PDOException
     * @return array
     */
    public function getWidgets()
    {
        $data = [];

        $query = 'SELECT title, version FROM widget_models';
        $result = $this->db->query($query);
        while ($row = $result->fetch()) {
            $data[$row['title']] = $row['version'];
        }

        return $data;
    }

    /**
     * Get versions of the system processus
     *
     * @throws PDOException
     * @return array
     */
    public function getSystem()
    {
        $data = ['OS' => php_uname()];

        $query = 'SHOW VARIABLES LIKE "version"';
        $result = $this->db->query($query);
        if ($row = $result->fetch()) {
            $data['mysql'] = $row['Value'];
        }

        return array_merge($data, $this->getVersionSystem());
    }

    /**
     * get system information
     *
     * @return array $data An array composed with the name and version of the OS
     */
    public function getVersionSystem()
    {
        $data = [];

        if (function_exists('shell_exec') && is_readable('/etc/os-release')) {
            $result = shell_exec('cat /etc/os-release');

            preg_match_all('/(.*)="?(.*)"?/', $result, $matches, PREG_PATTERN_ORDER);
            $osRelease = array_combine($matches[1], $matches[2]);

            $data['OS_name'] = $osRelease['NAME'];
            $data['OS_version'] = $osRelease['VERSION_ID'];
        }

        return $data;
    }

    /**
     * Get all Centreon widgets
     *
     * @throws PDOException
     * @return array $data Widgets statistics
     */
    public function getWidgetsUsage()
    {
        $data = [];

        $query = 'SELECT wm.title AS name, version, COUNT(widget_id) AS count
            FROM widgets AS w
            INNER JOIN widget_models AS wm ON (w.widget_model_id = wm.widget_model_id)
            GROUP BY name';
        $result = $this->db->query($query);
        while ($row = $result->fetch()) {
            $data[] = ['name' => $row['name'], 'version' => $row['version'], 'used' => $row['count']];
        }

        return $data;
    }
}
