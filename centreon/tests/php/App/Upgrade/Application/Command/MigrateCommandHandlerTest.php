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
use App\Upgrade\Application\MigrationSetGuard;
use App\Upgrade\Application\TargetVersionRecorder;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationSetLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpgradeLogger;

final class MigrateCommandHandlerTest extends TestCase
{
    private const string TARGET_VERSION = '26.11.0';

    private FakeMigrationRunner $migrationRunner;

    private FakeUpdateLocker $locker;

    private FakeMigrationSetLocker $migrationSetLocker;

    private FakeUpdateRepository $updateRepository;

    private FakeUpdateScriptFinder $scriptFinder;

    private FakeUpgradeLogger $logger;

    private MigrateCommandHandler $handler;

    protected function setUp(): void
    {
        $this->migrationRunner = new FakeMigrationRunner();
        $this->locker = new FakeUpdateLocker();
        $this->updateRepository = new FakeUpdateRepository();
        $this->migrationSetLocker = new FakeMigrationSetLocker();
        $this->scriptFinder = new FakeUpdateScriptFinder();
        $this->logger = new FakeUpgradeLogger();

        $this->handler = new MigrateCommandHandler(
            $this->migrationRunner,
            new MigrationSetGuard($this->locker, $this->migrationSetLocker, new FakeModuleRepository(['centreon-open-tickets'])),
            new TargetVersionRecorder($this->updateRepository, $this->scriptFinder, self::TARGET_VERSION, new FakeUpgradeLogger()),
            $this->logger, self::TARGET_VERSION,
        );
    }

    public function testMigratesTheWebSetsInOrderThenRecordsTheTargetVersion(): void
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
        // As during a platform update.
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

    public function testMigratesASingleWebSetUnderTheUpdateLockWithoutTouchingThePlatformVersion(): void
    {
        self::assertSame(['configuration\Version1'], ($this->handler)(new MigrateCommand(MigrationSet::web(WebMigrationSetEnum::Configuration))));

        self::assertSame(['migrate:configuration'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testStopsAtTheFirstFailureAndReleasesTheLock(): void
    {
        $this->migrationRunner->failingSets = [WebMigrationSetEnum::Realtime->value];

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

    public function testAFailureInTheSecondWebSetKeepsTheFirstOneAndRecordsNoVersion(): void
    {
        $this->migrationRunner->failingSets = [WebMigrationSetEnum::Configuration->value];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the migration failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame('Migration of "configuration" failed', $exception->getMessage());
        }

        self::assertSame(['migrate:realtime', 'migrate:configuration'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun, 'The platform version waits for every web set');
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testRefusesTheWebSetsWhileLegacyUpdateScriptsArePending(): void
    {
        $this->scriptFinder->availableUpdates = ['26.04.0', '26.10.0'];

        foreach ([new MigrateCommand(), new MigrateCommand(MigrationSet::web(WebMigrationSetEnum::Configuration))] as $command) {
            try {
                ($this->handler)($command);
                self::fail('The Centreon Web sets must wait for the legacy update scripts');
            } catch (\RuntimeException $exception) {
                self::assertSame('The update scripts of 26.04.0, 26.10.0 are pending: update the platform first, with the upgrade wizard or the API.', $exception->getMessage());
            }
        }

        self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun, 'Recording the version would skip the legacy update scripts for good');
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testTheWebSetsFailClearlyWhenThePlatformVersionCannotBeRead(): void
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

    public function testTheWebSetsFailWhenAnUpdateIsAlreadyInProgress(): void
    {
        $this->locker->lockAvailable = false;

        foreach ([new MigrateCommand(), new MigrateCommand(MigrationSet::web(WebMigrationSetEnum::Configuration))] as $command) {
            try {
                ($this->handler)($command);
                self::fail('A Centreon Web set requires the update lock');
            } catch (\RuntimeException $exception) {
                self::assertSame('An update is already in progress', $exception->getMessage());
            }
        }

        self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
    }

    public function testLogsAStepPerSet(): void
    {
        ($this->handler)(new MigrateCommand());

        self::assertSame(
            [
                ['step', 'migrations:realtime'],
                ['stepCompleted', 'migrations:realtime'],
                ['step', 'migrations:configuration'],
                ['stepCompleted', 'migrations:configuration'],
            ],
            array_map(static fn (array $call): array => [$call['method'], $call['stepName'] ?? null], $this->logger->calls),
        );
        self::assertSame([self::TARGET_VERSION], array_unique(array_column($this->logger->calls, 'version')));
    }

    public function testLogsTheFailedStepThenTheFailure(): void
    {
        $this->migrationRunner->failingSets = [WebMigrationSetEnum::Realtime->value];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the migration failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame(['step', 'stepFailure', 'error'], array_column($this->logger->calls, 'method'));
            self::assertSame($exception, $this->logger->calls[2]['exception'] ?? null);
        }
    }

    public function testLogsARefusalOutsideAnyStep(): void
    {
        $this->scriptFinder->availableUpdates = ['26.10.0'];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('The Centreon Web sets must wait for the legacy update scripts');
        } catch (\RuntimeException) {
            self::assertSame(['error'], array_column($this->logger->calls, 'method'));
        }
    }
}
