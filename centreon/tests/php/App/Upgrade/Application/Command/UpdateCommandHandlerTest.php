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

use App\Upgrade\Application\CacheClearer;
use App\Upgrade\Application\Command\UpdateCommand;
use App\Upgrade\Application\Command\UpdateCommandHandler;
use App\Upgrade\Application\DbmsVersionValidator;
use App\Upgrade\Application\EngineContextWriter;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Domain\Repository\WidgetRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpgradeLogger;

final class UpdateCommandHandlerTest extends TestCase
{
    private FakeUpdateRepository $updateRepository;

    private FakeUpdateScriptFinder $scriptFinder;

    private FakeUpdateLocker $locker;

    private DbmsVersionValidator&MockObject $dbmsValidator;

    private ModuleRepository&MockObject $moduleRepository;

    private WidgetRepository&MockObject $widgetRepository;

    private EngineContextWriter&MockObject $engineContextWriter;

    private CacheClearer&MockObject $cacheClearer;

    private UpdateCommandHandler $handler;

    private FakeUpgradeLogger $logger;

    protected function setUp(): void
    {
        $this->updateRepository = new FakeUpdateRepository();
        $this->scriptFinder = new FakeUpdateScriptFinder();
        $this->locker = new FakeUpdateLocker();
        $this->dbmsValidator = $this->createMock(DbmsVersionValidator::class);
        $this->moduleRepository = $this->createMock(ModuleRepository::class);
        $this->widgetRepository = $this->createMock(WidgetRepository::class);
        $this->engineContextWriter = $this->createMock(EngineContextWriter::class);
        $this->cacheClearer = $this->createMock(CacheClearer::class);
        $this->logger = new FakeUpgradeLogger();

        $this->handler = new UpdateCommandHandler(
            $this->updateRepository,
            $this->scriptFinder,
            $this->locker,
            $this->dbmsValidator,
            $this->moduleRepository,
            $this->widgetRepository,
            $this->engineContextWriter,
            $this->cacheClearer,
            $this->logger,
        );
    }

    public function testHappyPathNoUpdatesAvailable(): void
    {
        $this->scriptFinder->availableUpdates = [];

        ($this->handler)(new UpdateCommand());

        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertTrue($this->updateRepository->postUpdateCalled);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testHappyPathUpdatesApplied(): void
    {
        $this->scriptFinder->availableUpdates = ['24.10.1', '24.10.2'];

        ($this->handler)(new UpdateCommand());

        // Each available update is run in order, then the post-update runs.
        self::assertSame(['24.10.1', '24.10.2'], $this->updateRepository->updatesRun);
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
        $this->dbmsValidator
            ->method('validateOrFail')
            ->willThrowException(new \RuntimeException('MariaDB version 10.5 required'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/MariaDB/');

        ($this->handler)(new UpdateCommand());
    }

    public function testLockIsReleasedEvenWhenUpdateFails(): void
    {
        $this->scriptFinder->availableUpdates = ['24.10.1'];
        $this->updateRepository->currentVersion = '24.10.0';

        // Simulate a failure during runUpdate
        $failingRepository = new class ('24.10.0') extends FakeUpdateRepository {
            public function __construct(string $version)
            {
                $this->currentVersion = $version;
            }

            public function runMonitoringSql(string $version): void
            {
                throw new \RuntimeException('SQL error during update');
            }
        };

        $handler = new UpdateCommandHandler(
            $failingRepository,
            $this->scriptFinder,
            $this->locker,
            $this->dbmsValidator,
            $this->moduleRepository,
            $this->widgetRepository,
            $this->engineContextWriter,
            $this->cacheClearer,
            $this->logger,
        );

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
        $this->cacheClearer->method('clear')->willThrowException(new \RuntimeException('cache clear failed'));

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

    public function testEngineContextAndCacheAreCalledOnSuccess(): void
    {
        $this->engineContextWriter->expects(self::once())->method('writeIfMissing');
        $this->cacheClearer->expects(self::once())->method('clear');

        ($this->handler)(new UpdateCommand());
    }

    public function testEmitsAnErrorButNoFailureWhenItFailsBeforeStart(): void
    {
        // A failure raised before start() (here DBMS validation) must not emit a dangling
        // failure() with no matching start(); instead a standalone error() keeps
        // the aborted attempt visible in the upgrade channel (and it is still re-thrown).
        $this->dbmsValidator
            ->method('validateOrFail')
            ->willThrowException(new \RuntimeException('MariaDB version 10.5 required'));

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
        $this->cacheClearer->method('clear')->willThrowException(new \RuntimeException('cache clear failed'));

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
        // call-site argument order (currentVersion == targetVersion here, no updates available).
        $failure = $this->callFor('failure');
        self::assertSame('24.10.0', $failure['fromVersion']);
        self::assertSame('24.10.0', $failure['toVersion']);
        self::assertSame('cache clear failed', $failure['message']);
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
}
