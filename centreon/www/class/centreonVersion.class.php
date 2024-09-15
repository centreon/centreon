<?php
/*
 * Copyright 2005-2023 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
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

        if (!is_null($dbStorage)) {
            $this->dbStorage = $dbStorage;
        }
    }

    /**
     * Get Centreon core version
     *
     * @return array
     * @throws PDOException
     */
    public function getCore()
    {
        $data = [];

        // Get version of the centreon-web
        $query = 'SELECT i.value FROM informations i ' .
            'WHERE i.key = "version"';
        $result = $this->db->query($query);
        if ($row = $result->fetch()) {
            $data['centreon-web'] = $row['value'];
        }

        // Get version of the centreon-broker
        $cmd = shell_exec("cbd -v");

        if (preg_match('/^.*(.\d+\.\d+\.\d+)$/', $cmd, $matches)) {
            $data['centreon-broker'] = $matches[1];
        }

        // Get version of the centreon-engine
        $queryProgram = "SELECT DISTINCT instance_id, version AS program_version, engine AS program_name, " .
            "`name` AS instance_name FROM instances WHERE deleted = 0 ";
        $result = $this->dbStorage->query($queryProgram);

        while ($info = $result->fetch()) {
            $data['centreon-engine'] = $info["program_version"];
        }

        return $data;
    }

    /**
     * Get all Centreon modules
     *
     * @return array
     * @throws PDOException
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
     * @return array
     * @throws PDOException
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
     * @return array
     * @throws PDOException
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

        if (function_exists("shell_exec") && is_readable("/etc/os-release")) {
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
     * @return array $data Widgets statistics
     * @throws PDOException
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
