<?php
/*
 * Copyright 2005-2015 Centreon
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

// file centreon.config.php may not exist in test environment
$configFile = realpath(dirname(__FILE__) . "/../../../config/centreon.config.php");
if ($configFile !== false) {
    require_once $configFile;
}

define('TMP_DIR_PREFIX', 'tmpdir_');
define('TMP_DIR_SUFFIX', '.d');

/**
 * Class
 *
 * @class Backend
 */
class Backend
{
    /** @var */
    public $stmt_central_poller;
    /** @var null */
    private static $_instance = null;
    /** @var string */
    public $generate_path = '/var/cache/centreon/config';
    /** @var string */
    public $engine_sub = 'engine';
    /** @var string */
    public $broker_sub = 'broker';

    /** @var CentreonDB|null  */
    public $db = null;
    /** @var mixed|null */
    public $db_cs = null;

    /** @var null */
    private $tmp_file = null;
    /** @var null */
    private $tmp_dir = null;
    /** @var null */
    private $full_path = null;
    /** @var string */
    private $whoaim = 'unknown';

    /** @var null */
    private $poller_id = null;
    /** @var null */
    private $central_poller_id = null;

    /**
     * Backend constructor
     *
     * @param \Pimple\Container $dependencyInjector
     */
    private function __construct(\Pimple\Container $dependencyInjector)
    {
        $this->generate_path = _CENTREON_CACHEDIR_ . '/config';
        $this->db = $dependencyInjector['configuration_db'];
        $this->db_cs = $dependencyInjector['realtime_db'];
    }

    /**
     * @param \Pimple\Container $dependencyInjector
     *
     * @return Backend|null
     */
    public static function getInstance(\Pimple\Container $dependencyInjector)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new Backend($dependencyInjector);
        }

        return self::$_instance;
    }

    /**
     * @param $path
     *
     * @return bool
     */
    private function deleteDir($path)
    {
        if (is_dir($path) === true) {
            $files = array_diff(scandir($path), array('.', '..'));
            foreach ($files as $file) {
                $this->deleteDir(realpath($path) . '/' . $file);
            }

            return rmdir($path);
        } elseif (is_file($path) === true) {
            return unlink($path);
        }

        return false;
    }

    /**
     * @param $paths
     *
     * @return string
     * @throws Exception
     */
    public function createDirectories($paths)
    {
        $dir = '';
        $dir_append = '';
        foreach ($paths as $path) {
            $dir .= $dir_append . $path;
            $dir_append .= '/';

            if (file_exists($dir)) {
                if (!is_dir($dir)) {
                    throw new Exception("Generation path '" . $dir . "' is not a directory.");
                }
                if (posix_getuid() === fileowner($dir)) {
                    chmod($dir, 0770);
                }
            } else {
                if (!mkdir($dir, 0770, true)) {
                    throw new Exception("Cannot create directory '" . $dir . "'");
                }
            }
        }

        return $dir;
    }

    /**
     * @return string
     */
    public function getEngineGeneratePath()
    {
        return $this->generate_path . '/' . $this->engine_sub;
    }

    /**
     * @param $poller_id
     * @param $engine
     *
     * @return void
     * @throws Exception
     */
    public function initPath($poller_id, $engine = 1)
    {
        if ($engine == 1) {
            $this->createDirectories(array($this->generate_path, $this->engine_sub));
            $this->full_path = $this->generate_path . '/' . $this->engine_sub;
        } else {
            $this->createDirectories(array($this->generate_path, $this->broker_sub));
            $this->full_path = $this->generate_path . '/' . $this->broker_sub;
        }
        if (is_dir($this->full_path . '/' . $poller_id) && !is_writable($this->full_path . '/' . $poller_id)) {
            throw new Exception("Not writeable directory '" . $this->full_path . '/' . $poller_id . "'");
        }

        if (!is_writable($this->full_path)) {
            throw new Exception("Not writeable directory '" . $this->full_path . "'");
        }
        $this->tmp_file = basename(tempnam($this->full_path, TMP_DIR_PREFIX));
        $this->tmp_dir = $this->tmp_file . TMP_DIR_SUFFIX;
        $this->full_path .= '/' . $this->tmp_dir;
        if (! mkdir($this->full_path)) {
            throw new Exception("Cannot create directory '" . $this->full_path . "'");
        }
        // rights cannot be set in mkdir function (2nd argument) because current sgid bit on parent directory override it
        chmod($this->full_path, 0770);
    }

    /**
     * @return null
     */
    public function getPath()
    {
        return $this->full_path;
    }

    /**
     * @param $poller_id
     *
     * @return void
     */
    public function movePath($poller_id)
    {
        $subdir = dirname($this->full_path);
        $this->deleteDir($subdir . '/' . $poller_id);
        unlink($subdir . '/' . $this->tmp_file);
        rename($this->full_path, $subdir . '/' . $poller_id);
    }

    /**
     * @return void
     */
    public function cleanPath()
    {
        $subdir = dirname($this->full_path);
        if (is_dir($this->full_path)) {
            $this->deleteDir($this->full_path);
        }

        @unlink($subdir . '/' . $this->tmp_file);
    }

    /**
     * @param $username
     *
     * @return void
     */
    public function setUserName($username)
    {
        $this->whoaim = $username;
    }

    /**
     * @return string
     */
    public function getUserName()
    {
        return $this->whoaim;
    }

    /**
     * @param $poller_id
     *
     * @return void
     */
    public function setPollerId($poller_id)
    {
        $this->poller_id = $poller_id;
    }

    /**
     * @return null
     */
    public function getPollerId()
    {
        return $this->poller_id;
    }

    /**
     * @return mixed|null
     * @throws PDOException
     */
    public function getCentralPollerId()
    {
        if (!is_null($this->central_poller_id)) {
            return $this->central_poller_id;
        }
        $this->stmt_central_poller = $this->db->prepare("SELECT id
          FROM nagios_server
          WHERE localhost = '1' AND ns_activate = '1'
        ");
        $this->stmt_central_poller->execute();
        if ($this->stmt_central_poller->rowCount()) {
            $row = $this->stmt_central_poller->fetch(PDO::FETCH_ASSOC);
            $this->central_poller_id = $row['id'];
            return $this->central_poller_id;
        } else {
            throw new Exception("Cannot get central poller id");
        }
    }
}
