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

use App\Upgrade\Application\Command\UpdateCommand;
use App\Upgrade\Application\Command\UpdateCommandHandler;
use App\Upgrade\Application\Service\MigrationApplier;
use App\Upgrade\Application\Service\TargetVersionRecorder;
use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeCacheClearer;
use Tests\App\Upgrade\Infrastructure\Double\FakeDbmsVersionValidator;
use Tests\App\Upgrade\Infrastructure\Double\FakeEngineContextWriter;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpgradeLogger;
use Tests\App\Upgrade\Infrastructure\Double\FakeWidgetRepository;

final class UpdateCommandHandlerTest extends TestCase
{
    private const string TARGET_VERSION = '26.11.0';

    private FakeUpdateRepository $updateRepository;

    private FakeUpdateScriptFinder $scriptFinder;

    private FakeUpdateLocker $locker;

    private FakeDbmsVersionValidator $dbmsValidator;

    private FakeModuleRepository $moduleRepository;

    private FakeWidgetRepository $widgetRepository;

    private FakeEngineContextWriter $engineContextWriter;

    private FakeCacheClearer $cacheClearer;

    private FakeMigrationRunner $migrationRunner;

    private UpdateCommandHandler $handler;

    private FakeUpgradeLogger $logger;

    protected function setUp(): void
    {
        $this->updateRepository = new FakeUpdateRepository();
        $this->scriptFinder = new FakeUpdateScriptFinder();
        $this->locker = new FakeUpdateLocker();
        $this->dbmsValidator = new FakeDbmsVersionValidator();
        $this->moduleRepository = new FakeModuleRepository();
        $this->widgetRepository = new FakeWidgetRepository();
        $this->engineContextWriter = new FakeEngineContextWriter();
        $this->cacheClearer = new FakeCacheClearer();
        $this->logger = new FakeUpgradeLogger();
        $this->migrationRunner = new FakeMigrationRunner();

        $this->handler = $this->createHandler($this->updateRepository);
    }

    public function testHappyPathNoUpdatesAvailable(): void
    {
        $this->scriptFinder->availableUpdates = [];

        ($this->handler)(new UpdateCommand());

        self::assertSame([self::TARGET_VERSION], $this->updateRepository->updatesRun);
        self::assertTrue($this->updateRepository->postUpdateCalled);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testHappyPathUpdatesApplied(): void
    {
        $this->scriptFinder->availableUpdates = ['24.10.1', '24.10.2'];

        ($this->handler)(new UpdateCommand());

        // Each available update is run in order, then the platform version is recorded.
        self::assertSame(['24.10.1', '24.10.2', self::TARGET_VERSION], $this->updateRepository->updatesRun);
        self::assertTrue($this->updateRepository->postUpdateCalled);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testThrowsWhenLockAlreadyAcquired(): void
    {
        $this->locker->lockAvailable = false;

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the lock failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertMatchesRegularExpression('/already in progress/', $exception->getMessage());
        }

        // A lock failure happens before start(): it is surfaced as a standalone error(),
        // never a dangling failure().
        $methods = $this->loggedMethods();
        self::assertNotContains('start', $methods);
        self::assertNotContains('failure', $methods);
        self::assertContains('error', $methods);
    }

    public function testThrowsWhenCurrentVersionCannotBeRetrieved(): void
    {
        $this->updateRepository->currentVersion = null;

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the unreadable version to propagate');
        } catch (\RuntimeException $exception) {
            self::assertMatchesRegularExpression('/current platform version/', $exception->getMessage());
        }

        // The version is read before start(): a failure here is surfaced as a standalone error().
        $methods = $this->loggedMethods();
        self::assertNotContains('start', $methods);
        self::assertNotContains('failure', $methods);
        self::assertContains('error', $methods);
    }

    public function testThrowsWhenCurrentVersionIsBlank(): void
    {
        // A blank version from the DB must be treated like a missing one, so the upgrade
        // fails with a clear message instead of running steps against an unknown version.
        $this->updateRepository->currentVersion = '   ';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/current platform version/');

        ($this->handler)(new UpdateCommand());
    }

    public function testDbmsValidationFailurePropagates(): void
    {
        $this->dbmsValidator->failure = new \RuntimeException('MariaDB version 10.5 required');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/MariaDB/');

        ($this->handler)(new UpdateCommand());
    }

    public function testLockIsReleasedEvenWhenUpdateFails(): void
    {
        $this->scriptFinder->availableUpdates = ['24.10.1'];
        $this->updateRepository->currentVersion = '24.10.0';

        // Simulate a failure during runUpdate
        $failingRepository = new FakeUpdateRepository();
        $failingRepository->monitoringSqlFailure = new \RuntimeException('SQL error during update');

        $handler = $this->createHandler($failingRepository);

        try {
            $handler(new UpdateCommand());
            self::fail('Expected the update failure to propagate as a RuntimeException');
        } catch (\RuntimeException $exception) {
            self::assertSame('SQL error during update', $exception->getMessage());
        }

        // The lock must be released even though the update failed.
        self::assertTrue($this->locker->unlockCalled, 'Lock must be released even after a failure');

        // A failed upgrade must never run the post-update.
        self::assertFalse($failingRepository->postUpdateCalled);
    }

    public function testLockIsReleasedWhenAWrappedStepFails(): void
    {
        // A failure inside one of the handler-owned steps (here cache_clear) must still
        // propagate and release the lock.
        $this->cacheClearer->failure = new \RuntimeException('cache clear failed');

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the cache clear failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame('cache clear failed', $exception->getMessage());
        }

        self::assertTrue($this->locker->unlockCalled, 'Lock must be released even when a wrapped step fails');
    }

    public function testPostUpdateBacksUpBeforeRemovingTheInstallDir(): void
    {
        $this->scriptFinder->availableUpdates = [];

        ($this->handler)(new UpdateCommand());

        // The handler must back up the install directory before removing it.
        self::assertSame(['backup', 'remove'], $this->updateRepository->calls);
    }

    public function testRunsEveryPostUpgradeStepOnSuccess(): void
    {
        ($this->handler)(new UpdateCommand());

        self::assertSame(1, $this->moduleRepository->updates);
        self::assertSame(1, $this->widgetRepository->updates);
        self::assertSame(1, $this->engineContextWriter->writes);
        self::assertSame(1, $this->cacheClearer->clears);
    }

    public function testEmitsAnErrorButNoFailureWhenItFailsBeforeStart(): void
    {
        // A failure raised before start() (here DBMS validation) must not emit a dangling
        // failure() with no matching start(); instead a standalone error() keeps
        // the aborted attempt visible in the upgrade channel (and it is still re-thrown).
        $this->dbmsValidator->failure = new \RuntimeException('MariaDB version 10.5 required');

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the validation failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame('MariaDB version 10.5 required', $exception->getMessage());
        }

        $methods = $this->loggedMethods();
        self::assertNotContains('start', $methods);
        self::assertNotContains('failure', $methods);
        self::assertContains('error', $methods);

        // The error carries the current version (unknown here, as it failed before the version was read)
        // and the original message, pinning the handler call-site argument order.
        $error = $this->callFor('error');
        self::assertSame('unknown', $error['version']);
        self::assertSame('MariaDB version 10.5 required', $error['message']);
    }

    public function testEmitsFailureAfterStartWhenAStepFails(): void
    {
        // A failure raised after start() must emit a balanced failure(), preceded by start().
        $this->cacheClearer->failure = new \RuntimeException('cache clear failed');

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the cache clear failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame('cache clear failed', $exception->getMessage());
        }

        $methods = $this->loggedMethods();
        self::assertContains('start', $methods);
        self::assertContains('failure', $methods);
        // The post-start branch is exclusive: it must not also emit the pre-start error().
        self::assertNotContains('error', $methods);
        self::assertLessThan(
            array_search('failure', $methods, true),
            array_search('start', $methods, true),
            'start must be called before failure'
        );

        // The failure routes the versions into from/to and the original message, pinning the handler
        // call-site argument order (the target version is the platform version, not the last legacy script).
        $failure = $this->callFor('failure');
        self::assertSame('24.10.0', $failure['fromVersion']);
        self::assertSame(self::TARGET_VERSION, $failure['toVersion']);
        self::assertSame('cache clear failed', $failure['message']);
    }

    public function testRunsTheLegacyScriptsThenTheMigrationsThenTheClosingSteps(): void
    {
        $this->scriptFinder->availableUpdates = ['26.09.1'];

        ($this->handler)(new UpdateCommand());

        $steps = array_column(array_filter($this->logger->calls, static fn (array $call): bool => $call['method'] === 'step'), 'stepName');
        self::assertSame([
            'monitoring_sql',
            'php_script',
            'configuration_sql',
            'php_post_script',
            'update_version_information',
            ...array_map(static fn (MigrationSet $set): string => $set->migrationStep(), MigrationSet::webSets()),
            'backup_install_directory',
            'remove_install_directory',
            'modules_update',
            'widgets_update',
            'engine_context',
            'cache_clear',
        ], $steps);
        self::assertSame(['26.09.1', self::TARGET_VERSION], $this->updateRepository->updatesRun);
    }

    public function testNeverMovesTheVersionBackwards(): void
    {
        // A legacy script above the platform version, such as a hotfix shipped before the version bump.
        $this->scriptFinder->availableUpdates = ['26.12.0'];

        ($this->handler)(new UpdateCommand());

        self::assertSame(['26.12.0'], $this->updateRepository->updatesRun);
        self::assertSame('26.12.0', $this->callFor('start')['toVersion']);
    }

    public function testTheTargetVersionIsThePlatformVersion(): void
    {
        $this->scriptFinder->availableUpdates = ['24.10.1'];

        ($this->handler)(new UpdateCommand());

        $start = $this->callFor('start');
        self::assertSame('24.10.0', $start['fromVersion']);
        self::assertSame(self::TARGET_VERSION, $start['toVersion']);
        self::assertSame(['24.10.1', self::TARGET_VERSION], $this->updateRepository->updatesRun);
    }

    public function testTheTargetVersionIsTheLegacyOneWhileUpdateNextIsPending(): void
    {
        $this->scriptFinder->availableUpdates = ['24.10.1'];
        $this->scriptFinder->nextUpdatePending = true;

        ($this->handler)(new UpdateCommand());

        self::assertSame('24.10.1', $this->callFor('start')['toVersion']);
        self::assertSame(['24.10.1'], $this->updateRepository->updatesRun, 'The platform version is not recorded');
    }

    public function testKeepsTheLegacyVersionWithoutTheInstallDirectory(): void
    {
        $this->scriptFinder->availableUpdates = ['24.10.1'];
        $this->updateRepository->installDirectoryPresent = false;

        ($this->handler)(new UpdateCommand());

        self::assertSame('24.10.1', $this->callFor('start')['toVersion']);
        self::assertSame(['24.10.1'], $this->updateRepository->updatesRun, 'The platform version is not recorded');
        self::assertSame('Kept the platform version 24.10.1: without the install directory, a pending Update-next.php cannot be detected.', $this->callFor('warning')['message']);
        self::assertNotContains('backup_install_directory', array_column($this->logger->calls, 'stepName'));
        self::assertContains('success', $this->loggedMethods());
    }

    public function testStopsAtTheFirstFailingMigrationSetAndReleasesTheLock(): void
    {
        $this->migrationRunner->failingSets = [WebMigrationSetEnum::Realtime->value];

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the migration failure to propagate');
        } catch (MigrationFailedException $exception) {
            self::assertStringContainsString('Migration of "realtime" failed', $exception->getMessage());
        }

        self::assertSame(['migrate:' . WebMigrationSetEnum::Realtime->value], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame([], $this->updateRepository->updatesRun, 'The platform version is not recorded when a migration fails');
        self::assertSame(0, $this->moduleRepository->updates);
        self::assertSame(0, $this->cacheClearer->clears);
        self::assertTrue($this->locker->unlockCalled);
        self::assertContains('failure', $this->loggedMethods());
    }

    public function testAModuleFailureStillEndsTheUpgradeThenIsReported(): void
    {
        $this->moduleRepository->updateFailure = new \RuntimeException('Some updates failed. bam: upgrade.sql failed');

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the module failure to be reported');
        } catch (\RuntimeException $exception) {
            self::assertSame('Some updates failed. bam: upgrade.sql failed', $exception->getMessage());
        }

        self::assertSame(1, $this->widgetRepository->updates);
        self::assertSame(1, $this->engineContextWriter->writes);
        self::assertSame(1, $this->cacheClearer->clears);
        self::assertTrue($this->locker->unlockCalled);
        self::assertContains('failure', $this->loggedMethods());
    }

    public function testTheFirstClosingStepFailureIsReportedWhateverFailsAfterIt(): void
    {
        $this->moduleRepository->updateFailure = new \RuntimeException('Some updates failed. bam: upgrade.sql failed');
        $this->widgetRepository->failure = new \RuntimeException('engine-status failed');
        $this->cacheClearer->failure = new \RuntimeException('cache:clear failed');

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the module failure to be reported');
        } catch (\RuntimeException $exception) {
            self::assertSame('Some updates failed. bam: upgrade.sql failed', $exception->getMessage());
        }

        self::assertSame(1, $this->engineContextWriter->writes);
        self::assertSame(1, $this->cacheClearer->clears);
        self::assertSame(['modules_update', 'widgets_update', 'cache_clear'], array_column(array_filter($this->logger->calls, static fn (array $call): bool => $call['method'] === 'stepFailure'), 'stepName'));
    }

    /**
     * @return list<string> the ordered list of logger methods called
     */
    private function loggedMethods(): array
    {
        return array_column($this->logger->calls, 'method');
    }

    /**
     * @return array<string, mixed> the arguments of the first call to the given method
     */
    private function callFor(string $method): array
    {
        foreach ($this->logger->calls as $call) {
            if ($call['method'] === $method) {
                return $call;
            }
        }

        self::fail(sprintf('%s was never called', $method));
    }

    private function createHandler(FakeUpdateRepository $updateRepository): UpdateCommandHandler
    {
        $targetVersionRecorder = new TargetVersionRecorder($updateRepository, $this->scriptFinder, self::TARGET_VERSION, $this->logger);

        return new UpdateCommandHandler(
            $updateRepository,
            $this->scriptFinder,
            $this->locker,
            $this->dbmsValidator,
            $this->moduleRepository,
            $this->widgetRepository,
            $this->engineContextWriter,
            $this->cacheClearer,
            $this->logger,
            new MigrationApplier($this->migrationRunner, $targetVersionRecorder, $this->logger, self::TARGET_VERSION),
            $targetVersionRecorder,
        );
    }
}
