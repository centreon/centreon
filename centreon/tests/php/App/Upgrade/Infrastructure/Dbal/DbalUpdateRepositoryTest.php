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

namespace Tests\App\Upgrade\Infrastructure\Dbal;

use Adaptation\Database\Connection\Model\ConnectionConfig;
use App\Upgrade\Infrastructure\Dbal\DbalUpdateRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final class DbalUpdateRepositoryTest extends TestCase
{
    private const VERSION = '24.10.1';

    private Connection&MockObject $configConnection;

    private Connection&MockObject $realtimeConnection;

    private ConnectionConfig $connectionConfig;

    private Filesystem $realFilesystem;

    private string $baseDir;

    private string $installDir;

    private string $libDir;

    protected function setUp(): void
    {
        $this->configConnection = $this->createMock(Connection::class);
        $this->realtimeConnection = $this->createMock(Connection::class);
        $this->connectionConfig = new ConnectionConfig(
            'localhost',
            'centreon',
            'password',
            'centreon',
            'centreon_storage',
        );
        $this->realFilesystem = new Filesystem();

        $this->baseDir = sys_get_temp_dir() . '/centreon-update-test-' . uniqid();
        $this->installDir = $this->baseDir . '/install';
        $this->libDir = $this->baseDir . '/lib';
        $this->realFilesystem->mkdir([$this->installDir, $this->libDir]);
    }

    protected function tearDown(): void
    {
        $this->realFilesystem->remove($this->baseDir);
    }

    public function testUpdateVersionInformationRunsTheVersionUpdate(): void
    {
        $executed = [];
        $this->configConnection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$executed): int {
                $executed[] = $sql;

                return 1;
            });

        $this->repository()->updateVersionInformation(self::VERSION);

        self::assertStringContainsString('UPDATE `informations`', implode(' | ', $executed));
    }

    public function testRunConfigurationSqlResumesFromProgressCountAndSkipsAlreadyExecutedStatements(): void
    {
        // A configuration SQL file with two statements, the first already applied on a previous run.
        $this->createConfigurationSqlFile("-- a comment\nSELECT 1;\nSELECT 2;\n");
        file_put_contents($this->progressFile(), '1');

        $executed = [];
        $this->configConnection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$executed): int {
                $executed[] = $sql;

                return 1;
            });

        $this->repository()->runConfigurationSql(self::VERSION);

        // The already-applied first statement is skipped; only the second one runs.
        $statementsRun = implode(' | ', $executed);
        self::assertStringContainsString('SELECT 2', $statementsRun);
        self::assertStringNotContainsString('SELECT 1', $statementsRun);

        // The progress file is advanced to the new count.
        self::assertSame('2', file_get_contents($this->progressFile()));
    }

    public function testRunConfigurationSqlFailsWhenProgressFileIsNotWritable(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('Permission bits are bypassed when running as root.');
        }

        $this->createConfigurationSqlFile("SELECT 1;\n");
        // The progress file exists but is read-only: writing the new count must fail loudly.
        $progressFile = $this->progressFile();
        file_put_contents($progressFile, '0');
        chmod($progressFile, 0o444);

        $this->configConnection->method('executeStatement')->willReturn(1);

        try {
            $this->repository()->runConfigurationSql(self::VERSION);
            self::fail('Expected the non-writable progress file to abort the step');
        } catch (\RuntimeException $exception) {
            self::assertMatchesRegularExpression('/temporary file/', $exception->getMessage());
        } finally {
            chmod($progressFile, 0o644);
        }
    }

    public function testInstallDirectoryExistsReflectsTheFilesystem(): void
    {
        self::assertTrue($this->repository()->installDirectoryExists());

        $this->realFilesystem->remove($this->installDir);

        self::assertFalse($this->repository()->installDirectoryExists());
    }

    public function testBackupInstallDirectoryThrowsWhenBackupDirectoryIsMissing(): void
    {
        // installDir exists (created in setUp) but libDir/installs does not.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/backup directory/');

        $this->repository()->backupInstallDirectory(self::VERSION);
    }

    public function testBackupInstallDirectoryCopiesTheInstallContents(): void
    {
        $this->realFilesystem->mkdir($this->libDir . '/installs');
        file_put_contents($this->installDir . '/marker.txt', 'data');

        $this->repository()->backupInstallDirectory(self::VERSION);

        // A timestamped backup copy holding the install contents now exists under installs/.
        $backups = glob($this->libDir . '/installs/install-' . self::VERSION . '-*') ?: [];
        self::assertCount(1, $backups);
        self::assertFileExists($backups[0] . '/marker.txt');
        // The install directory itself is left untouched by the backup step.
        self::assertDirectoryExists($this->installDir);
    }

    public function testRemoveInstallDirectoryDeletesIt(): void
    {
        $this->repository()->removeInstallDirectory();

        self::assertDirectoryDoesNotExist($this->installDir);
    }

    public function testBackupInstallDirectoryPropagatesFilesystemFailures(): void
    {
        $this->realFilesystem->mkdir($this->libDir . '/installs');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('mirror')->willThrowException(new IOException('Cannot mirror the install directory'));

        $this->expectException(IOException::class);
        $this->expectExceptionMessage('Cannot mirror the install directory');

        $this->repository($filesystem)->backupInstallDirectory(self::VERSION);
    }

    private function repository(?Filesystem $filesystem = null): DbalUpdateRepository
    {
        return new DbalUpdateRepository(
            $this->configConnection,
            $this->realtimeConnection,
            $this->connectionConfig,
            $this->libDir,
            $this->installDir,
            $filesystem ?? $this->realFilesystem,
        );
    }

    private function createConfigurationSqlFile(string $contents): void
    {
        $sqlDir = $this->installDir . '/sql/centreon';
        $this->realFilesystem->mkdir([$sqlDir, $this->installDir . '/tmp']);
        file_put_contents($sqlDir . '/Update-DB-' . self::VERSION . '.sql', $contents);
    }

    private function progressFile(): string
    {
        return $this->installDir . '/tmp/Update-DB-' . self::VERSION . '.sql';
    }
}
