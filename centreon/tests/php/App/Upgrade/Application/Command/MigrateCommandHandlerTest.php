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

namespace Tests\App\Upgrade\Application\Command;

use App\Upgrade\Application\Command\MigrateCommand;
use App\Upgrade\Application\Command\MigrateCommandHandler;
use App\Upgrade\Application\MigrationLogger;
use App\Upgrade\Application\MigrationSetGuard;
use App\Upgrade\Application\TargetVersionRecorder;
use App\Upgrade\Domain\MigrationSet;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationSetLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;

final class MigrateCommandHandlerTest extends TestCase
{
    private const string TARGET_VERSION = '26.11.0';

    private FakeMigrationRunner $migrationRunner;

    private FakeUpdateLocker $locker;

    private FakeMigrationSetLocker $migrationSetLocker;

    private FakeUpdateRepository $updateRepository;

    private FakeUpdateScriptFinder $scriptFinder;

    private RecordingLogger $logger;

    private MigrateCommandHandler $handler;

    protected function setUp(): void
    {
        $this->migrationRunner = new FakeMigrationRunner();
        $this->locker = new FakeUpdateLocker();
        $this->updateRepository = new FakeUpdateRepository();
        $this->migrationSetLocker = new FakeMigrationSetLocker();
        $this->scriptFinder = new FakeUpdateScriptFinder();
        $this->logger = new RecordingLogger();

        $this->handler = new MigrateCommandHandler(
            $this->migrationRunner,
            new MigrationSetGuard($this->locker, $this->migrationSetLocker, new FakeModuleRepository(['centreon-open-tickets'])),
            new TargetVersionRecorder($this->updateRepository, $this->scriptFinder, self::TARGET_VERSION, new MigrationLogger(new NullLogger(), self::TARGET_VERSION)),
            new MigrationLogger($this->logger, self::TARGET_VERSION),
        );
    }

    public function testMigratesTheCoreSetsInOrderThenRecordsTheTargetVersion(): void
    {
        $executed = ($this->handler)(new MigrateCommand());

        self::assertSame(['migrate:realtime', 'migrate:configuration'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame(['realtime\Version1', 'configuration\Version1'], $executed);
        self::assertSame([self::TARGET_VERSION], $this->updateRepository->updatesRun);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testNeverMovesTheVersionBackwards(): void
    {
        $this->updateRepository->currentVersion = '26.12.0';

        ($this->handler)(new MigrateCommand());

        self::assertSame([], $this->updateRepository->updatesRun);
    }

    public function testMigratesAModuleSetUnderItsOwnLockWhileThePlatformUpdateHoldsTheUpdateLock(): void
    {
        // The platform update holds the update lock while it upgrades the modules, which migrate their own set.
        $this->locker->lockAvailable = false;

        ($this->handler)(new MigrateCommand(MigrationSet::module('centreon-open-tickets')));

        self::assertSame(['migrate:module:centreon-open-tickets'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame(['module:centreon-open-tickets'], $this->migrationSetLocker->released);
        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertFalse($this->locker->unlockCalled, 'A lock it did not take must not be released');
    }

    public function testFailsWhenTheSameModuleSetIsAlreadyBeingMigrated(): void
    {
        $this->migrationSetLocker->lockedSets = ['module:centreon-open-tickets'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "centreon-open-tickets" module migration set is already being migrated');

        try {
            ($this->handler)(new MigrateCommand(MigrationSet::module('centreon-open-tickets')));
        } finally {
            self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
        }
    }

    public function testRefusesAModuleThatIsNotInstalled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The module "centreon-bam-server" is not installed');

        try {
            ($this->handler)(new MigrateCommand(MigrationSet::module('centreon-bam-server')));
        } finally {
            self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
        }
    }

    public function testReleasesTheLockOfAModuleSetWhoseMigrationFails(): void
    {
        $this->migrationRunner->failingSets = ['module:centreon-open-tickets'];

        try {
            ($this->handler)(new MigrateCommand(MigrationSet::module('centreon-open-tickets')));
            self::fail('Expected the migration failure to propagate');
        } catch (\RuntimeException) {
        }

        self::assertSame(['module:centreon-open-tickets'], $this->migrationSetLocker->released);
    }

    public function testMigratesASingleCoreSetUnderTheUpdateLockWithoutTouchingThePlatformVersion(): void
    {
        self::assertSame(['configuration\Version1'], ($this->handler)(new MigrateCommand(MigrationSet::core(MigrationSet::CONFIGURATION))));

        self::assertSame(['migrate:configuration'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testStopsAtTheFirstFailureAndReleasesTheLock(): void
    {
        $this->migrationRunner->failingSets = [MigrationSet::REALTIME];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the migration failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame('Migration of "realtime" failed', $exception->getMessage());
        }

        self::assertSame(['migrate:realtime'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testRefusesTheCoreSetsWhileLegacyUpdateScriptsArePending(): void
    {
        $this->scriptFinder->availableUpdates = ['26.04.0', '26.10.0'];

        foreach ([new MigrateCommand(), new MigrateCommand(MigrationSet::core(MigrationSet::CONFIGURATION))] as $command) {
            try {
                ($this->handler)($command);
                self::fail('The core sets must wait for the legacy update scripts');
            } catch (\RuntimeException $exception) {
                self::assertSame('The update scripts of 26.04.0, 26.10.0 are pending: update the platform first, with the upgrade wizard or the API.', $exception->getMessage());
            }
        }

        self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun, 'Recording the version would skip the legacy update scripts for good');
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testTheCoreSetsFailClearlyWhenThePlatformVersionCannotBeRead(): void
    {
        // Every legacy script would otherwise be listed as pending.
        $this->updateRepository->currentVersion = null;
        $this->scriptFinder->availableUpdates = ['24.10.1', '24.10.2'];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the missing version to be reported');
        } catch (\RuntimeException $exception) {
            self::assertSame('Cannot retrieve the current platform version', $exception->getMessage());
        }

        self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testAModuleSetDoesNotWaitForTheLegacyUpdateScripts(): void
    {
        $this->scriptFinder->availableUpdates = ['26.10.0'];

        ($this->handler)(new MigrateCommand(MigrationSet::module('centreon-open-tickets')));

        self::assertSame(['migrate:module:centreon-open-tickets'], $this->migrationRunner->journal->getArrayCopy());
    }

    public function testTheCoreSetsFailWhenAnUpdateIsAlreadyInProgress(): void
    {
        $this->locker->lockAvailable = false;

        foreach ([new MigrateCommand(), new MigrateCommand(MigrationSet::core(MigrationSet::CONFIGURATION))] as $command) {
            try {
                ($this->handler)($command);
                self::fail('A core set requires the update lock');
            } catch (\RuntimeException $exception) {
                self::assertSame('An update is already in progress', $exception->getMessage());
            }
        }

        self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
    }

    public function testLogsTheMigrationLifecycle(): void
    {
        ($this->handler)(new MigrateCommand());

        self::assertSame(['migration.start', 'migration.success'], array_column(array_column($this->logger->records(), 'context'), 'event'));
        $context = $this->logger->lastRecord()['context'] ?? [];
        self::assertSame('core', $context['set'] ?? null);
        self::assertSame(self::TARGET_VERSION, $context['version'] ?? null);
        self::assertSame(['realtime\\Version1', 'configuration\\Version1'], $context['migrations'] ?? null);
        self::assertIsInt($context['duration_ms'] ?? null);
    }

    public function testLogsARefusalBeforeRethrowingIt(): void
    {
        $this->scriptFinder->availableUpdates = ['26.10.0'];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('The core sets must wait for the legacy update scripts');
        } catch (\RuntimeException $exception) {
            $record = $this->logger->lastRecord();
            self::assertSame('error', $record['level'] ?? null);
            self::assertSame('migration.failure', $record['context']['event'] ?? null);
            self::assertSame($exception, $record['context']['exception'] ?? null);
        }
    }
}
