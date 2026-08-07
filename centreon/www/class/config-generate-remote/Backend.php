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

namespace ConfigGenerateRemote;

use CentreonDB;
use Exception;
use PDOException;
use PDOStatement;
use Pimple\Container;

// file centreon.config.php may not exist in test environment
$configFile = realpath(__DIR__ . '/../../../config/centreon.config.php');
if ($configFile !== false) {
    require_once $configFile;
}

/**
 * Class
 *
 * @class Backend
 * @package ConfigGenerateRemote
 */
class Backend
{
    /** Prefix of the temporary generation directory and of its witness file. */
    public const TMP_DIR_PREFIX = 'tmpdir_';

    /** Suffix appended to the temporary generation directory. */
    public const TMP_DIR_SUFFIX = '.d';

    /** @var string */
    public $generatePath;

    /** @var string */
    public $engine_sub;

    /** @var PDOStatement */
    public $stmtCentralPoller;

    /** @var CentreonDB|null */
    public $db = null;

    /** @var CentreonDB|null */
    public $dbCs = null;

    /** @var Backend|null */
    private static $instance = null;

    /** @var string[] */
    private $subdirs = ['configuration', 'media'];

    /** @var string */
    private $fieldSeparatorInfile = '~~~';

    /** @var string */
    private $lineSeparatorInfile = '######';

    /** @var string */
    private $tmpDirPrefix = self::TMP_DIR_PREFIX;

    /** @var string|null */
    private $tmpFile = null;

    /** @var string|null */
    private $tmpDir = null;

    /** @var string */
    private $tmpDirSuffix = self::TMP_DIR_SUFFIX;

    /** @var string|null */
    private $fullPath = null;

    /** @var string */
    private $whoaim = 'unknown';

    /** @var bool */
    private $exportContact = false;

    /** @var int|null */
    private $pollerId = null;

    /** @var int|null */
    private $centralPollerId = null;

    /**
     * Backend constructor
     *
     * @param Container $dependencyInjector
     */
    private function __construct(Container $dependencyInjector)
    {
        $this->generatePath = _CENTREON_CACHEDIR_ . '/config/export';
        $this->db = $dependencyInjector['configuration_db'];
        $this->dbCs = $dependencyInjector['realtime_db'];
    }

    /**
     * Get backend singleton
     *
     * @param Container $dependencyInjector
     *
     * @return Backend|null
     */
    public static function getInstance(Container $dependencyInjector)
    {
        if (is_null(self::$instance)) {
            self::$instance = new Backend($dependencyInjector);
        }

        return self::$instance;
    }

    /**
     * Create multiple directories
     *
     * @param array $paths
     *
     * @throws Exception
     * @return string created directory path
     */
    public function createDirectories(array $paths): string
    {
        $dir = '';
        $dirAppend = '';
        foreach ($paths as $path) {
            $dir .= $dirAppend . $path;
            $dirAppend .= '/';

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
     * generatePath getter
     *
     * @return string
     */
    public function getEngineGeneratePath(): string
    {
        return $this->generatePath . '/' . $this->engine_sub;
    }

    /**
     * Create directories to generation configuration
     *
     * @param int $pollerId
     *
     * @throws Exception
     * @return void
     */
    public function initPath(int $pollerId): void
    {
        $this->createDirectories([$this->generatePath]);
        $this->fullPath = $this->generatePath;

        if (! is_writable($this->fullPath)) {
            throw new Exception("Not writeable directory '" . $this->fullPath . "'");
        }

        if (is_dir($this->fullPath . '/' . $pollerId) && ! is_writable($this->fullPath . '/' . $pollerId)) {
            throw new Exception("Not writeable directory '" . $this->fullPath . '/' . $pollerId . "'");
        }

        $this->tmpFile = basename(tempnam($this->fullPath, $this->tmpDirPrefix));
        $this->tmpDir = $this->tmpFile . $this->tmpDirSuffix;
        $this->fullPath .= '/' . $this->tmpDir;

        $this->createDirectories([$this->fullPath]);
        foreach ($this->subdirs as $subdir) {
            $this->createDirectories([$this->fullPath . '/' . $subdir]);
        }
    }

    /**
     * fieldSeparatorInfile getter
     *
     * @return string
     */
    public function getFieldSeparatorInfile()
    {
        return $this->fieldSeparatorInfile;
    }

    /**
     * lineSeparatorInfile getter
     *
     * @return string
     */
    public function getLineSeparatorInfile()
    {
        return $this->lineSeparatorInfile;
    }

    /**
     * exportContact getter
     *
     * @return bool
     */
    public function isExportContact()
    {
        return $this->exportContact;
    }

    /**
     * fullPath getter
     *
     * @return string|null
     */
    public function getPath()
    {
        return $this->fullPath;
    }

    /**
     * Move poller directory
     *
     * @param int $pollerId
     * @return void
     */
    public function movePath(int $pollerId): void
    {
        $subdir = dirname($this->fullPath);
        $this->deleteDir($subdir . '/' . $pollerId);
        unlink($subdir . '/' . $this->tmpFile);
        rename($this->fullPath, $subdir . '/' . $pollerId);
    }

    /**
     * Clean directory and files
     *
     * @return void
     */
    public function cleanPath(): void
    {
        $subdir = dirname($this->fullPath);
        if (is_dir($this->fullPath)) {
            $this->deleteDir($this->fullPath, true);
        }

        @unlink($subdir . '/' . $this->tmpFile);
    }

    /**
     * Remove orphaned temporary generation entries left behind in the export cache.
     *
     * During a normal export the temporary directory is either renamed by movePath()
     * or removed by cleanPath(). When the worker process is killed before either runs
     * (e.g. gorgone command timeout, OOM), the "tmpdir_*" directory - and the witness
     * file created by tempnam() - is orphaned forever and eventually saturates /var.
     *
     * Only entries whose last modification time is older than $maxAgeSeconds are
     * removed. This is best-effort: filesystem errors never interrupt the caller.
     *
     * @param int $maxAgeSeconds entries strictly older than this (in seconds) are considered orphaned;
     *                           a non-positive value is a no-op (nothing is removed)
     * @param string|null $exportPath base export directory (defaults to the runtime cache path)
     *
     * @return int number of orphaned entries removed
     */
    public static function cleanOrphanedTmpDirs(int $maxAgeSeconds, ?string $exportPath = null): int
    {
        $exportPath ??= _CENTREON_CACHEDIR_ . '/config/export';

        if ($maxAgeSeconds <= 0 || ! is_dir($exportPath)) {
            return 0;
        }

        $threshold = time() - $maxAgeSeconds;
        $removed = 0;

        foreach (glob($exportPath . '/' . self::TMP_DIR_PREFIX . '*') ?: [] as $entry) {
            $mtime = @filemtime($entry);
            if ($mtime === false || $mtime >= $threshold) {
                continue;
            }

            system('rm -rf ' . escapeshellarg($entry), $returnCode);
            if ($returnCode === 0) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * username setter
     *
     * @param string $username
     * @return void
     */
    public function setUserName(string $username): void
    {
        $this->whoaim = $username;
    }

    /**
     * username getter
     *
     * @return string
     */
    public function getUserName(): string
    {
        return $this->whoaim;
    }

    /**
     * poller id setter
     *
     * @param int $pollerId
     * @return void
     */
    public function setPollerId(int $pollerId): void
    {
        $this->pollerId = $pollerId;
    }

    /**
     * poller id getter
     *
     * @return int
     */
    public function getPollerId(): int
    {
        return $this->pollerId;
    }

    /**
     * Get id of central server
     *
     * @throws PDOException
     * @return int
     */
    public function getCentralPollerId(): int
    {
        if (! is_null($this->centralPollerId)) {
            return $this->centralPollerId;
        }
        $this->stmtCentralPoller = $this->db->prepare("SELECT id
          FROM nagios_server
          WHERE localhost = '1' AND ns_activate = '1'
        ");
        $this->stmtCentralPoller->execute();
        if ($this->stmtCentralPoller->rowCount()) {
            $row = $this->stmtCentralPoller->fetch(PDO::FETCH_ASSOC);
            $this->centralPollerId = $row['id'];

            return $this->centralPollerId;
        }

        throw new Exception('Cannot get central poller id');
    }

    /**
     * Delete directory recursively
     *
     * @param string $path
     * @param bool $onlyContent if set to false, do not delete directory itself
     * @return bool
     */
    private function deleteDir(?string $path, bool $onlyContent = false): bool
    {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $this->deleteDir(realpath($path) . '/' . $file);
            }

            if (! $onlyContent) {
                return rmdir($path);
            }

            return true;
        }
        if (is_file($path)) {
            return unlink($path);
        }

        return false;
    }
}
