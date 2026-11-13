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
use App\Upgrade\Application\Service\MigrationApplier;
use App\Upgrade\Application\Service\MigrationSetGuard;
use App\Upgrade\Application\Service\TargetVersionRecorder;
use App\Upgrade\Domain\Exception\LegacyUpdatesPendingException;
use App\Upgrade\Domain\Exception\LockNotReleasedException;
use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Exception\MigrationInterruptedException;
use App\Upgrade\Domain\Exception\MigrationSetBusyException;
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\PlatformVersionNotRecordedException;
use App\Upgrade\Domain\Exception\PlatformVersionUnknownException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\Attributes\DataProvider;
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
            new MigrationSetGuard($this->locker, $this->migrationSetLocker, new FakeModuleRepository(['centreon-open-tickets'])),
            new MigrationApplier(
                $this->migrationRunner,
                new TargetVersionRecorder($this->updateRepository, $this->scriptFinder, self::TARGET_VERSION, new FakeUpgradeLogger()),
                $this->logger,
                self::TARGET_VERSION,
            ),
            $this->logger,
            self::TARGET_VERSION,
        );
    }

    public function testMigratesTheWebSetsInOrderThenRecordsTheTargetVersion(): void
    {
        $executed = ($this->handler)(new MigrateCommand());

        self::assertSame(['migrate:realtime', 'migrate:configuration'], $this->migrationRunner->journal->getArrayCopy());
        self::assertEquals(new MigrationOutcome(['realtime\Version1', 'configuration\Version1']), $executed);
        self::assertSame([self::TARGET_VERSION], $this->updateRepository->updatesRun);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testMigratesAModuleSetWithoutRecordingThePlatformVersion(): void
    {
        ($this->handler)(new MigrateCommand(MigrationSet::module('centreon-open-tickets')));

        self::assertSame(['migrate:module:centreon-open-tickets'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun);
    }

    public function testFailsWhenTheSameModuleSetIsAlreadyBeingMigrated(): void
    {
        $this->migrationSetLocker->lockedSets = ['module:centreon-open-tickets'];

        $this->expectException(MigrationSetBusyException::class);
        $this->expectExceptionMessage('The "centreon-open-tickets" module migration set is already being migrated or rolled back');

        try {
            ($this->handler)(new MigrateCommand(MigrationSet::module('centreon-open-tickets')));
        } finally {
            self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
            self::assertSame(['warning'], array_column($this->logger->calls, 'method'));
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
            self::assertSame(['warning'], array_column($this->logger->calls, 'method'));
        }
    }

    public function testStopsAtTheFirstFailureAndReleasesTheLock(): void
    {
        $this->migrationRunner->failingSets = [WebMigrationSetEnum::Realtime->value];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the migration failure to propagate');
        } catch (MigrationFailedException $exception) {
            self::assertSame('realtime\Version1', $exception->version);
            self::assertSame('Migration of "realtime" failed', $exception->getPrevious()?->getMessage());
        }

        self::assertSame(['migrate:realtime'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testAFailingUnlockAfterASuccessfulMigrationIsNotReportedAsAFailedMigration(): void
    {
        $this->locker->unlockFailure = new \RuntimeException('Semaphore removed');

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the unlock failure to propagate');
        } catch (LockNotReleasedException $exception) {
            self::assertSame('Completed, but releasing the lock failed: Semaphore removed', $exception->getMessage());
            self::assertEquals(new MigrationOutcome(['realtime\Version1', 'configuration\Version1']), $exception->result, 'The executed migrations are still told');
        }

        self::assertSame([self::TARGET_VERSION], $this->updateRepository->updatesRun);
        self::assertSame('Migration of the Centreon Web migration sets: Completed, but releasing the lock failed: Semaphore removed', $this->logger->calls[array_key_last($this->logger->calls)]['message'] ?? null);
    }

    public function testAFailureInTheSecondWebSetKeepsTheFirstOneAndRecordsNoVersion(): void
    {
        $this->migrationRunner->failingSets = [WebMigrationSetEnum::Configuration->value];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the migration failure to propagate');
        } catch (MigrationInterruptedException $interrupted) {
            $exception = $interrupted->getPrevious();
            self::assertInstanceOf(MigrationFailedException::class, $exception);
            self::assertSame('configuration\Version1', $exception->version);
            self::assertSame('Migration of "configuration" failed', $exception->getPrevious()?->getMessage());
        }

        self::assertSame(['migrate:realtime', 'migrate:configuration'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun, 'The platform version waits for every web set');
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testTellsAFailedVersionRecordingFromAFailedMigration(): void
    {
        $this->updateRepository->versionUpdateFailure = new \RuntimeException('informations is locked');

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the version recording failure to propagate');
        } catch (PlatformVersionNotRecordedException $exception) {
            self::assertSame('error', $this->logger->calls[array_key_last($this->logger->calls)]['method'] ?? null);
            self::assertSame($exception, $this->logger->calls[array_key_last($this->logger->calls)]['exception'] ?? null, 'Raised after the steps: its trace is logged');
        }

        self::assertSame(['migrate:realtime', 'migrate:configuration'], $this->migrationRunner->journal->getArrayCopy());
    }

    public function testRefusesTheWebSetsWhileLegacyUpdateScriptsArePending(): void
    {
        $this->scriptFinder->availableUpdates = ['26.04.0', '26.10.0'];

        foreach ([new MigrateCommand(), new MigrateCommand(MigrationSet::web(WebMigrationSetEnum::Configuration))] as $command) {
            try {
                ($this->handler)($command);
                self::fail('The Centreon Web sets must wait for the legacy update scripts');
            } catch (LegacyUpdatesPendingException) {
            }
        }

        self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun, 'Recording the version would skip the legacy update scripts for good');
        self::assertSame(['warning', 'warning'], array_column($this->logger->calls, 'method'));
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
        } catch (PlatformVersionUnknownException $exception) {
            self::assertSame('Cannot read the platform version: the "version" row of the "informations" table is missing or empty. Restore it, or finish the installation, before migrating.', $exception->getMessage());
            self::assertSame(['warning'], array_column($this->logger->calls, 'method'), 'A refusal the operator can act on');
        }

        self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testTheWebSetsFailWhenAnUpdateIsAlreadyInProgress(): void
    {
        $this->locker->lockAvailable = false;

        foreach ([new MigrateCommand(), new MigrateCommand(MigrationSet::web(WebMigrationSetEnum::Configuration))] as $command) {
            try {
                ($this->handler)($command);
                self::fail('A Centreon Web set requires the update lock');
            } catch (MigrationSetBusyException $exception) {
                self::assertSame('An update is already in progress', $exception->getMessage());
            }
        }

        self::assertSame([], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame(['warning', 'warning'], array_column($this->logger->calls, 'method'));
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

    public function testTellsTheWebSetsAlreadyMigratedWhenALaterOneFails(): void
    {
        $this->migrationRunner->failingSets = [WebMigrationSetEnum::Configuration->value];

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the migration failure to propagate');
        } catch (MigrationInterruptedException $exception) {
            self::assertSame(
                'The "configuration\Version1" migration of the "configuration" migration set failed: Migration of "configuration" failed. Already migrated: "realtime" migration set. A new run resumes with the "configuration" migration set.',
                $exception->getMessage(),
            );
            self::assertSame('Migration of the Centreon Web migration sets failed: ' . $exception->getMessage(), $this->logger->calls[4]['message'] ?? null);
            self::assertSame(['step', 'stepCompleted', 'step', 'stepFailure', 'error'], array_column($this->logger->calls, 'method'));
            self::assertNull($this->logger->calls[4]['exception'] ?? null, 'The failed step logged the trace');
        }
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function provideStepFailures(): iterable
    {
        $set = MigrationSet::web(WebMigrationSetEnum::Configuration);

        yield 'a failed migration' => [MigrationFailedException::failed($set, 'Version1', new \RuntimeException('Duplicate key'))];

        yield 'a refused set' => [new MigrationSetRefusedException('Refused')];

        yield 'a lost connection' => [new \RuntimeException('Connection lost')];

        yield 'an open transaction' => [new \LogicException('Transaction open')];
    }

    #[DataProvider('provideStepFailures')]
    public function testLogsTheTraceOfAStepFailureOnce(\Throwable $failure): void
    {
        $this->migrationRunner->failure = $failure;

        try {
            ($this->handler)(new MigrateCommand());
            self::fail('Expected the step failure to propagate');
        } catch (\Throwable $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame(['step', 'stepFailure', 'error'], array_column($this->logger->calls, 'method'));
        self::assertSame($failure, $this->logger->calls[1]['exception'] ?? null);
        self::assertNull($this->logger->calls[2]['exception'] ?? null);
    }
}
