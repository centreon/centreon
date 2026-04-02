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

use App\Upgrade\Domain\Repository\UpdateRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class DbalUpdateRepository implements UpdateRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $configConnection,
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $realtimeConnection,
        #[Autowire(param: 'upgrade.lib_dir')]
        private string $libDir,
        #[Autowire(param: 'upgrade.install_dir')]
        private string $installDir,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
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
        $this->logger->info('Running update', ['version' => $version]);
        $this->runMonitoringSql($version);
        $this->runScript($version);
        $this->runConfigurationSql($version);
        $this->runPostScript($version);
        $this->updateVersionInformation($version);
    }

    public function runPostUpdate(string $currentVersion): void
    {
        if (! $this->filesystem->exists($this->installDir)) {
            return;
        }

        $backupDirectory = $this->libDir . '/installs/install-' . $currentVersion . '-' . date('Ymd_His');

        $this->logger->info('Backing up installation directory', [
            'source' => $this->installDir,
            'destination' => $backupDirectory,
        ]);

        $this->filesystem->mirror($this->installDir, $backupDirectory);

        $this->logger->info('Removing installation directory', [
            'installation_directory' => $this->installDir,
        ]);

        $this->filesystem->remove($this->installDir);
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
        $pearDB = $this->configConnection->getNativeConnection();
        $pearDBO = $this->realtimeConnection->getNativeConnection();

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
        $pearDB = $this->configConnection->getNativeConnection();
        $pearDBO = $this->realtimeConnection->getNativeConnection();

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
                        try {
                            $connection->executeStatement($query);
                        } catch (\Throwable $ex) {
                            $this->logger->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

                            throw $ex;
                        }
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
        if (! file_exists($tmpFile) || is_writable($tmpFile)) {
            file_put_contents($tmpFile, $count);
        } else {
            $this->logger->warning('Cannot write in temporary file', ['path' => $tmpFile]);
        }
    }
}
