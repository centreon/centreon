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
use App\Upgrade\Domain\Event\UpgradeStepCompleted;
use App\Upgrade\Domain\Event\UpgradeStepFailed;
use App\Upgrade\Domain\Event\UpgradeStepStarted;
use App\Upgrade\Infrastructure\Dbal\DbalUpdateRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Tests\App\Upgrade\Infrastructure\Double\FakeEventDispatcher;

final class DbalUpdateRepositoryTest extends TestCase
{
    private const VERSION = '24.10.1';

    private Connection&MockObject $configConnection;

    private Connection&MockObject $realtimeConnection;

    private ConnectionConfig $connectionConfig;

    private FakeEventDispatcher $events;

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
        $this->events = new FakeEventDispatcher();
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

    public function testRunUpdateDispatchesTheStepSequenceOnSuccess(): void
    {
        // No SQL/PHP upgrade files exist in the install dir, so every step is a no-op
        // except update_version_information, which performs the version UPDATE.
        $this->configConnection->method('executeStatement')->willReturn(1);

        $this->repository()->runUpdate(self::VERSION);

        $expectedSteps = [
            'monitoring_sql',
            'php_script',
            'configuration_sql',
            'php_post_script',
            'update_version_information',
        ];
        self::assertSame($expectedSteps, $this->stepNames(UpgradeStepStarted::class));
        self::assertSame($expectedSteps, $this->stepNames(UpgradeStepCompleted::class));
        self::assertSame([], $this->eventsOfType(UpgradeStepFailed::class));

        foreach ($this->eventsOfType(UpgradeStepStarted::class) as $event) {
            self::assertSame(self::VERSION, $event->version);
        }
    }

    public function testRunUpdateDispatchesStepFailedAndRethrowsWhenAStepFails(): void
    {
        $this->configConnection
            ->method('executeStatement')
            ->willThrowException(new \RuntimeException('SQL error while updating the version'));

        try {
            $this->repository()->runUpdate(self::VERSION);
            self::fail('Expected the failing step to propagate as a RuntimeException');
        } catch (\RuntimeException $exception) {
            self::assertSame('SQL error while updating the version', $exception->getMessage());
        }

        // The four file-based steps have no file to run, so they complete; the version
        // update is the one that fails.
        $failed = $this->firstEventOfType(UpgradeStepFailed::class);
        self::assertSame('update_version_information', $failed->step);
        self::assertSame(self::VERSION, $failed->version);
        self::assertSame('SQL error while updating the version', $failed->message);
        self::assertInstanceOf(\RuntimeException::class, $failed->exception);

        // The failing step must never be reported as completed.
        self::assertNotContains('update_version_information', $this->stepNames(UpgradeStepCompleted::class));
    }

    public function testRunPostUpdateReturnsEarlyWhenInstallDirIsAbsent(): void
    {
        $this->realFilesystem->remove($this->installDir);

        $this->repository()->runPostUpdate(self::VERSION);

        self::assertSame([], $this->events->dispatched);
    }

    public function testRunPostUpdateThrowsWhenBackupDirectoryIsMissing(): void
    {
        // installDir exists (created in setUp) but libDir/installs does not.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/backup directory/');

        $this->repository()->runPostUpdate(self::VERSION);
    }

    public function testRunPostUpdateDispatchesStepFailedWhenRemoveFails(): void
    {
        // The backup directory must really exist for the is_dir()/is_writable() guard to pass.
        $this->realFilesystem->mkdir($this->libDir . '/installs');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $filesystem->method('mirror'); // backup succeeds
        $filesystem->method('remove')->willThrowException(new IOException('Cannot remove the install directory'));

        try {
            $this->repository($filesystem)->runPostUpdate(self::VERSION);
            self::fail('Expected the failing removal to propagate');
        } catch (IOException $exception) {
            self::assertSame('Cannot remove the install directory', $exception->getMessage());
        }

        // The backup step completed, the removal step failed.
        self::assertContains('backup_install_directory', $this->stepNames(UpgradeStepCompleted::class));

        $failed = $this->firstEventOfType(UpgradeStepFailed::class);
        self::assertSame('remove_install_directory', $failed->step);
        self::assertSame(self::VERSION, $failed->version);
    }

    public function testWriteExecutedQueriesCountWritesTheCount(): void
    {
        $tmpFile = $this->installDir . '/progress.count';

        $this->invokePrivate('writeExecutedQueriesCount', [$tmpFile, 7]);

        self::assertSame('7', file_get_contents($tmpFile));
    }

    public function testWriteExecutedQueriesCountThrowsWhenTmpFileIsNotWritable(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('Permission bits are bypassed when running as root.');
        }

        $tmpFile = $this->installDir . '/readonly.count';
        file_put_contents($tmpFile, '0');
        chmod($tmpFile, 0o444);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/temporary file/');

            $this->invokePrivate('writeExecutedQueriesCount', [$tmpFile, 1]);
        } finally {
            chmod($tmpFile, 0o644);
        }
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
            $this->events,
        );
    }

    /**
     * @param list<mixed> $arguments
     */
    private function invokePrivate(string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod(DbalUpdateRepository::class, $method);

        return $reflection->invokeArgs($this->repository(), $arguments);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return list<T>
     */
    private function eventsOfType(string $type): array
    {
        return array_values(array_filter(
            $this->events->dispatched,
            static fn (object $event): bool => $event instanceof $type,
        ));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    private function firstEventOfType(string $type): object
    {
        $events = $this->eventsOfType($type);
        self::assertNotEmpty($events, sprintf('Expected at least one %s event to be dispatched', $type));

        return $events[0];
    }

    /**
     * @param class-string<UpgradeStepStarted|UpgradeStepCompleted> $type
     *
     * @return list<string>
     */
    private function stepNames(string $type): array
    {
        return array_map(
            static fn (object $event): string => $event->step,
            $this->eventsOfType($type),
        );
    }
}
