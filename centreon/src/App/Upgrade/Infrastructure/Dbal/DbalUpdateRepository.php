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
use App\Upgrade\Domain\Repository\UpdateRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs the individual upgrade operations. Step sequencing and logging are owned by the
 * caller (UpdateCommandHandler): this repository only performs each operation.
 */
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

    public function runMonitoringSql(string $version): void
    {
        $filePath = $this->installDir . '/sql/centstorage/Update-CSTG-' . $version . '.sql';

        if (is_readable($filePath)) {
            $this->runSqlFile($this->realtimeConnection, $filePath);
        }
    }

    public function runScript(string $version): void
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

    public function runConfigurationSql(string $version): void
    {
        $filePath = $this->installDir . '/sql/centreon/Update-DB-' . $version . '.sql';

        if (is_readable($filePath)) {
            $this->runSqlFile($this->configConnection, $filePath);
        }
    }

    public function runPostScript(string $version): void
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

    public function updateVersionInformation(string $version): void
    {
        $this->configConnection->executeStatement(
            "UPDATE `informations` SET `value` = :version WHERE `key` = 'version'",
            ['version' => $version]
        );
    }

    public function installDirectoryExists(): bool
    {
        return $this->filesystem->exists($this->installDir);
    }

    public function backupInstallDirectory(string $currentVersion): void
    {
        $installsDir = $this->libDir . '/installs';
        if (! is_dir($installsDir) || ! is_writable($installsDir)) {
            throw new \RuntimeException(
                'The installs backup directory does not exist or is not writable. '
                . 'Please create it with write permissions for the web server user.'
            );
        }

        $backupDirectory = $installsDir . '/install-' . $currentVersion . '-' . date('Ymd_His');
        $this->filesystem->mirror($this->installDir, $backupDirectory);
    }

    public function removeInstallDirectory(): void
    {
        $this->filesystem->remove($this->installDir);
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
        // A failed or partial write (missing parent dir on a fresh run, full disk, interrupted write) must
        // not be silent: the count is the SQL resume cursor, and a stale one re-runs applied statements.
        // Compare the bytes written against the payload byte length to catch a truncated write.
        // file_put_contents() collapses most failures to false; the short-count branch below is
        // defence-in-depth for a genuine partial write (e.g. a full disk) and is hard to trigger in tests.
        $payload = (string) $count;
        $expectedBytes = mb_strlen($payload, '8bit');
        $bytesWritten = file_put_contents($tmpFile, $payload);
        if ($bytesWritten === false || $bytesWritten !== $expectedBytes) {
            throw new \RuntimeException(sprintf(
                'Partial or failed write of the resume cursor (%s of %d bytes) in temporary file: %s',
                $bytesWritten === false ? 'none' : (string) $bytesWritten,
                $expectedBytes,
                $tmpFile
            ));
        }
    }
}
