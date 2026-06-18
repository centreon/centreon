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

namespace App\Upgrade\Infrastructure\Dbal;

use Adaptation\Database\Connection\Adapter\Dbal\DbalConnectionAdapter;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Log\LoggerUpgrade;
use App\Upgrade\Domain\Repository\UpdateRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class DbalUpdateRepository implements UpdateRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $configConnection,
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $realtimeConnection,
        private ConnectionConfig $connectionConfig,
        #[Autowire(param: 'upgrade.lib_dir')]
        private string $libDir,
        #[Autowire(param: 'upgrade.install_dir')]
        private string $installDir,
        private Filesystem $filesystem,
    ) {
    }

    public function findCurrentVersion(): ?string
    {
        $result = $this->configConnection->fetchOne(
            "SELECT `value` FROM `informations` WHERE `key` = 'version'"
        );

        return is_scalar($result) ? (string) $result : null;
    }

    public function runUpdate(string $version): void
    {
        $this->runStep($version, 'monitoring_sql', fn () => $this->runMonitoringSql($version));
        $this->runStep($version, 'php_script', fn () => $this->runScript($version));
        $this->runStep($version, 'configuration_sql', fn () => $this->runConfigurationSql($version));
        $this->runStep($version, 'php_post_script', fn () => $this->runPostScript($version));
        $this->runStep($version, 'update_version_information', fn () => $this->updateVersionInformation($version));
    }

    public function runPostUpdate(string $currentVersion): void
    {
        if (! $this->filesystem->exists($this->installDir)) {
            return;
        }

        $installsDir = $this->libDir . '/installs';
        if (! is_dir($installsDir) || ! is_writable($installsDir)) {
            throw new \RuntimeException(
                'The installs backup directory does not exist or is not writable. '
                . 'Please create it with write permissions for the web server user.'
            );
        }

        $backupDirectory = $installsDir . '/install-' . $currentVersion . '-' . date('Ymd_His');

        $this->runStep(
            $currentVersion,
            'backup_install_directory',
            fn () => $this->filesystem->mirror($this->installDir, $backupDirectory),
        );

        $this->runStep(
            $currentVersion,
            'remove_install_directory',
            fn () => $this->filesystem->remove($this->installDir),
        );
    }

    private function runStep(string $version, string $step, callable $action): void
    {
        LoggerUpgrade::create()->step($version, $step, "Starting step '{$step}'");
        $startedAt = microtime(true);
        try {
            $action();
        } catch (\Throwable $exception) {
            LoggerUpgrade::create()->stepFailure($exception->getMessage(), $version, $step, $exception);

            throw $exception;
        }
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        LoggerUpgrade::create()->stepCompleted($version, $step, $durationMs, "Step '{$step}' completed in {$durationMs}ms");
    }

    private function runMonitoringSql(string $version): void
    {
        $filePath = $this->installDir . '/sql/centstorage/Update-CSTG-' . $version . '.sql';

        if (is_readable($filePath)) {
            $this->runSqlFile($this->realtimeConnection, $filePath);
        }
    }

    private function runScript(string $version): void
    {
        // $pearDB and $pearDBO are exposed as local variables to the included update script.
        // Scripts expect ConnectionInterface (Adaptation), not raw PDO.
        $pearDB = DbalConnectionAdapter::createFromDbalConnection($this->configConnection, $this->connectionConfig);
        $pearDBO = DbalConnectionAdapter::createFromDbalConnection($this->realtimeConnection, $this->connectionConfig);

        $filePath = $this->installDir . '/php/Update-' . $version . '.php';
        if (is_readable($filePath)) {
            include_once $filePath;
        }
    }

    private function runConfigurationSql(string $version): void
    {
        $filePath = $this->installDir . '/sql/centreon/Update-DB-' . $version . '.sql';

        if (is_readable($filePath)) {
            $this->runSqlFile($this->configConnection, $filePath);
        }
    }

    private function runPostScript(string $version): void
    {
        // $pearDB and $pearDBO are exposed as local variables to the included post-update script.
        // Scripts expect ConnectionInterface (Adaptation), not raw PDO.
        $pearDB = DbalConnectionAdapter::createFromDbalConnection($this->configConnection, $this->connectionConfig);
        $pearDBO = DbalConnectionAdapter::createFromDbalConnection($this->realtimeConnection, $this->connectionConfig);

        $filePath = $this->installDir . '/php/Update-' . $version . '.post.php';
        if (is_readable($filePath)) {
            include_once $filePath;
        }
    }

    private function updateVersionInformation(string $version): void
    {
        $this->configConnection->executeStatement(
            "UPDATE `informations` SET `value` = :version WHERE `key` = 'version'",
            ['version' => $version]
        );
    }

    private function runSqlFile(Connection $connection, string $filePath): void
    {
        set_time_limit(0);

        $tmpFile = $this->installDir . '/tmp/' . basename($filePath);
        $alreadyExecutedCount = $this->getAlreadyExecutedQueriesCount($tmpFile);

        $fileStream = fopen($filePath, 'r');
        if (! is_resource($fileStream)) {
            return;
        }

        $query = '';
        $executedCount = 0;

        try {
            while (! feof($fileStream)) {
                $currentLine = fgets($fileStream);
                if ($currentLine === false) {
                    continue;
                }

                if (! str_starts_with(trim($currentLine), '--')) {
                    $query .= ' ' . trim($currentLine);
                }

                if (! empty(trim($query)) && preg_match('/;\s*$/', $query)) {
                    $executedCount++;
                    if ($executedCount > $alreadyExecutedCount) {
                        $connection->executeStatement($query);
                        $this->writeExecutedQueriesCount($tmpFile, $executedCount);
                    }
                    $query = '';
                }
            }
        } finally {
            fclose($fileStream);
        }
    }

    private function getAlreadyExecutedQueriesCount(string $tmpFile): int
    {
        if (is_readable($tmpFile)) {
            $content = file_get_contents($tmpFile);
            if (is_numeric($content)) {
                return (int) $content;
            }
        }

        return 0;
    }

    private function writeExecutedQueriesCount(string $tmpFile, int $count): void
    {
        if (file_exists($tmpFile) && ! is_writable($tmpFile)) {
            throw new \RuntimeException(sprintf('Cannot write in temporary file: %s', $tmpFile));
        }
        file_put_contents($tmpFile, $count);
    }
}
