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

use Pimple\Container;

// file centreon.config.php may not exist in test environment
$configFile = realpath(__DIR__ . '/../../../config/centreon.config.php');
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
    /** @var CentreonDBStatement */
    public $stmt_central_poller;

    /** @var Backend|null */
    private static $_instance = null;

    /** @var string */
    public $generate_path = '/var/cache/centreon/config';

    /** @var string */
    public $engine_sub = 'engine';

    /** @var string */
    public $broker_sub = 'broker';

    public $vmware_sub = 'vmware';

    /** @var CentreonDB|null */
    public $db = null;

    /** @var CentreonDB|null */
    public $db_cs = null;

    /** @var string|null */
    private $tmp_file = null;

    /** @var string|null */
    private $tmp_dir = null;

    /** @var string|null */
    private $full_path = null;

    /** @var string */
    private $whoaim = 'unknown';

    /** @var string|null */
    private $poller_id = null;

    /** @var string|null */
    private $central_poller_id = null;

    /**
     * Backend constructor
     *
     * @param Container $dependencyInjector
     */
    private function __construct(Container $dependencyInjector)
    {
        $this->generate_path = _CENTREON_CACHEDIR_ . '/config';
        $this->db = $dependencyInjector['configuration_db'];
        $this->db_cs = $dependencyInjector['realtime_db'];
    }

    /**
     * @param Container $dependencyInjector
     *
     * @return Backend|null
     */
    public static function getInstance(Container $dependencyInjector)
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
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $this->deleteDir(realpath($path) . '/' . $file);
            }

            return rmdir($path);
        }
        if (is_file($path) === true) {
            return unlink($path);
        }

        return false;
    }

    /**
     * @param $paths
     *
     * @throws Exception
     * @return string
     */
    public function createDirectories($paths)
    {
        $dir = '';
        $dir_append = '';
        foreach ($paths as $path) {
            $dir .= $dir_append . $path;
            $dir_append .= '/';

            if (file_exists($dir)) {
                if (! is_dir($dir)) {
                    throw new Exception("Generation path '" . $dir . "' is not a directory.");
                }
                if (posix_getuid() === fileowner($dir)) {
                    chmod($dir, 0770);
                }
            } elseif (! mkdir($dir, 0770, true)) {
                throw new Exception("Cannot create directory '" . $dir . "'");
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
     * @throws Exception
     * @return void
     */
    public function initPath($poller_id, $engine = 1): void
    {
        switch ($engine) {
            case 1:
                $this->createDirectories([$this->generate_path, $this->engine_sub]);
                $this->full_path = $this->generate_path . '/' . $this->engine_sub;
                break;
            case 2:
                $this->createDirectories([$this->generate_path, $this->broker_sub]);
                $this->full_path = $this->generate_path . '/' . $this->broker_sub;
                break;
            case 3:
                $this->createDirectories([$this->generate_path, $this->vmware_sub]);
                $this->full_path = $this->generate_path . '/' . $this->vmware_sub;
                break;
            default:
                throw new Exception('Invalid engine type');
        }
        if (is_dir($this->full_path . '/' . $poller_id) && ! is_writable($this->full_path . '/' . $poller_id)) {
            throw new Exception("Not writeable directory '" . $this->full_path . '/' . $poller_id . "'");
        }

        if (! is_writable($this->full_path)) {
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
    public function movePath($poller_id): void
    {
        $subdir = dirname($this->full_path);
        $this->deleteDir($subdir . '/' . $poller_id);
        unlink($subdir . '/' . $this->tmp_file);
        rename($this->full_path, $subdir . '/' . $poller_id);
    }

    /**
     * @return void
     */
    public function cleanPath(): void
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
    public function setUserName($username): void
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
    public function setPollerId($poller_id): void
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
     * @throws PDOException
     * @return mixed|null
     */
    public function getCentralPollerId()
    {
        if (! is_null($this->central_poller_id)) {
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
        }

        throw new Exception('Cannot get central poller id');
    }
}
