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

declare(strict_types=1);

namespace Core\Platform\Infrastructure\Repository;

use Adaptation\Log\LoggerUpgrade;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Platform\Application\Repository\WriteUpdateRepositoryInterface;
use Pimple\Container;
use Symfony\Component\Filesystem\Filesystem;

class DbWriteUpdateRepository extends AbstractRepositoryDRB implements WriteUpdateRepositoryInterface
{
    /**
     * @param string $libDir
     * @param string $installDir
     * @param Container $dependencyInjector
     * @param DatabaseConnection $db
     * @param Filesystem $filesystem
     */
    public function __construct(
        private string $libDir,
        private string $installDir,
        private Container $dependencyInjector,
        DatabaseConnection $db,
        private Filesystem $filesystem,
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     *
     * @throws \Throwable any failure raised by a sub-step (SQL execution, included PHP script, version update) re-thrown after the matching upgrade.step_failure event is logged
     */
    public function runUpdate(string $version): void
    {
        $this->executeStep($version, 'monitoring_sql', fn () => $this->runMonitoringSql($version));
        $this->executeStep($version, 'php_script', fn () => $this->runScript($version));
        $this->executeStep($version, 'configuration_sql', fn () => $this->runConfigurationSql($version));
        $this->executeStep($version, 'php_post_script', fn () => $this->runPostScript($version));
        $this->executeStep($version, 'update_version_information', fn () => $this->updateVersionInformation($version));
    }

    /**
     * @inheritDoc
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException when the install directory cannot be mirrored or removed
     */
    public function runPostUpdate(string $currentVersion): void
    {
        if (! $this->filesystem->exists($this->installDir)) {
            return;
        }

        $this->backupInstallDirectory($currentVersion);
        $this->removeInstallDirectory($currentVersion);
    }

    /**
     * @throws \Throwable
     */
    private function executeStep(string $version, string $stepName, callable $callable): void
    {
        LoggerUpgrade::create()->step($version, $stepName, "Starting step '{$stepName}'");
        $startedAt = microtime(true);
        try {
            $callable();
        } catch (\Throwable $exception) {
            LoggerUpgrade::create()->stepFailure(
                "Step '{$stepName}' failed: {$exception->getMessage()}",
                $version,
                $stepName,
                $exception
            );

            throw $exception;
        }
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        LoggerUpgrade::create()->stepCompleted(
            $version,
            $stepName,
            $durationMs,
            "Step '{$stepName}' completed in {$durationMs}ms"
        );
    }

    /**
     * Backup installation directory.
     *
     * @param string $currentVersion
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException when the install directory cannot be mirrored to the backup location
     */
    private function backupInstallDirectory(string $currentVersion): void
    {
        $backupDirectory = $this->libDir . '/installs/install-' . $currentVersion . '-' . date('Ymd_His');

        LoggerUpgrade::create()->step(
            $currentVersion,
            'backup_install_directory',
            "Backing up installation directory from {$this->installDir} to {$backupDirectory}"
        );

        $this->filesystem->mirror(
            $this->installDir,
            $backupDirectory,
        );
    }

    /**
     * Remove installation directory.
     *
     * @param string $currentVersion
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException when the install directory cannot be removed
     */
    private function removeInstallDirectory(string $currentVersion): void
    {
        LoggerUpgrade::create()->step(
            $currentVersion,
            'remove_install_directory',
            "Removing installation directory at {$this->installDir}"
        );

        $this->filesystem->remove($this->installDir);
    }

    /**
     * Run sql queries on monitoring database.
     *
     * @param string $version
     *
     * @throws RepositoryException when a SQL statement fails or the progress file cannot be written
     */
    private function runMonitoringSql(string $version): void
    {
        $upgradeFilePath = $this->installDir . '/sql/centstorage/Update-CSTG-' . $version . '.sql';
        if (is_readable($upgradeFilePath)) {
            $this->db->switchToDb($this->db->getConnectionConfig()->getDatabaseNameRealTime());
            $this->runSqlFile($upgradeFilePath);
        }
    }

    /**
     * Run php upgrade script.
     *
     * @param string $version
     *
     * @throws \Throwable any failure raised by the included Update-<version>.php script
     */
    private function runScript(string $version): void
    {
        $pearDB = $this->dependencyInjector['configuration_db'];
        $pearDBO = $this->dependencyInjector['realtime_db'];

        $upgradeFilePath = $this->installDir . '/php/Update-' . $version . '.php';
        if (is_readable($upgradeFilePath)) {
            include_once $upgradeFilePath;
        }
    }

    /**
     * Run sql queries on configuration database.
     *
     * @param string $version
     *
     * @throws RepositoryException when a SQL statement fails or the progress file cannot be written
     */
    private function runConfigurationSql(string $version): void
    {
        $upgradeFilePath = $this->installDir . '/sql/centreon/Update-DB-' . $version . '.sql';
        if (is_readable($upgradeFilePath)) {
            $this->db->switchToDb($this->db->getConnectionConfig()->getDatabaseNameConfiguration());
            $this->runSqlFile($upgradeFilePath);
        }
    }

    /**
     * Run php post upgrade script.
     *
     * @param string $version
     *
     * @throws \Throwable any failure raised by the included Update-<version>.post.php script
     */
    private function runPostScript(string $version): void
    {
        $pearDB = $this->dependencyInjector['configuration_db'];
        $pearDBO = $this->dependencyInjector['realtime_db'];

        $upgradeFilePath = $this->installDir . '/php/Update-' . $version . '.post.php';
        if (is_readable($upgradeFilePath)) {
            include_once $upgradeFilePath;
        }
    }

    /**
     * Update version information.
     *
     * @param string $version
     *
     * @throws \PDOException when the version update statement fails
     */
    private function updateVersionInformation(string $version): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                "UPDATE `:db`.`informations` SET `value` = :version WHERE `key` = 'version'"
            )
        );
        $statement->bindValue(':version', $version, \PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * Run sql file and use temporary file to store last executed line.
     *
     * @param string $filePath
     *
     * @throws RepositoryException when a SQL statement fails or the temporary progress file cannot be written
     */
    private function runSqlFile(string $filePath): void
    {
        set_time_limit(0);

        $fileName = basename($filePath);
        $tmpFile = $this->installDir . '/tmp/' . $fileName;

        $alreadyExecutedQueriesCount = $this->getAlreadyExecutedQueriesCount($tmpFile);

        if (is_readable($filePath)) {
            $fileStream = fopen($filePath, 'r');
            if (is_resource($fileStream)) {
                $query = '';
                $currentLineNumber = 0;
                $executedQueriesCount = 0;
                try {
                    while (! feof($fileStream)) {
                        $currentLineNumber++;
                        $currentLine = fgets($fileStream);
                        if ($currentLine && ! $this->isSqlComment($currentLine)) {
                            $query .= ' ' . trim($currentLine);
                        }

                        if ($this->isSqlCompleteQuery($query)) {
                            $executedQueriesCount++;
                            if ($executedQueriesCount > $alreadyExecutedQueriesCount) {
                                $this->executeQuery($query);
                                $this->writeExecutedQueriesCountInTemporaryFile($tmpFile, $executedQueriesCount);
                            }
                            $query = '';
                        }
                    }
                } finally {
                    fclose($fileStream);
                }
            }
        }
    }

    /**
     * Get stored executed queries count in temporary file to retrieve next query to run in case of an error occurred.
     *
     * @param string $tmpFile
     *
     * @return int
     */
    private function getAlreadyExecutedQueriesCount(string $tmpFile): int
    {
        $startLineNumber = 0;
        if (is_readable($tmpFile)) {
            $lineNumber = file_get_contents($tmpFile);
            if (is_numeric($lineNumber)) {
                $startLineNumber = (int) $lineNumber;
            }
        }

        return $startLineNumber;
    }

    /**
     * Write executed queries count in temporary file to retrieve upgrade when an error occurred.
     *
     * @param string $tmpFile
     * @param int $count
     *
     * @throws RepositoryException when the temporary file exists but is not writable
     */
    private function writeExecutedQueriesCountInTemporaryFile(string $tmpFile, int $count): void
    {
        if (file_exists($tmpFile) && ! is_writable($tmpFile)) {
            throw new RepositoryException(sprintf('Cannot write in temporary file: %s', $tmpFile));
        }
        // A failed write (missing parent dir on a fresh run, full disk, partial write) must not
        // be silent: the count is the SQL resume cursor, and a stale one re-runs applied statements.
        if (file_put_contents($tmpFile, $count) === false) {
            throw new RepositoryException(sprintf('Cannot write in temporary file: %s', $tmpFile));
        }
    }

    /**
     * Check if a line a sql comment.
     *
     * @param string $line
     *
     * @return bool
     */
    private function isSqlComment(string $line): bool
    {
        return str_starts_with(trim($line), '--');
    }

    /**
     * Check if a query is complete (trailing semicolon).
     *
     * @param string $query
     *
     * @return bool
     */
    private function isSqlCompleteQuery(string $query): bool
    {
        return ! empty(trim($query)) && preg_match('/;\s*$/', $query);
    }

    /**
     * Execute sql query.
     *
     * @param string $query
     *
     * @throws RepositoryException when the underlying query fails
     */
    private function executeQuery(string $query): void
    {
        try {
            $this->db->query($query);
        } catch (\Exception $ex) {
            throw new RepositoryException('Cannot execute query: ' . $query, 0, $ex);
        }
    }
}
