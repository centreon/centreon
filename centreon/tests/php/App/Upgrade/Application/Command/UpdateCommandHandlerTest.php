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

use Adaptation\Log\LoggerUpgrade;
use App\Upgrade\Application\CacheClearer;
use App\Upgrade\Application\Command\UpdateCommand;
use App\Upgrade\Application\Command\UpdateCommandHandler;
use App\Upgrade\Application\DbmsVersionValidator;
use App\Upgrade\Application\EngineContextWriter;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Domain\Repository\WidgetRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;

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

    /** @var AbstractLogger&object{records: list<array{level: string, message: string, context: array<string, mixed>}>} */
    private AbstractLogger $loggerSpy;

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

        $this->handler = new UpdateCommandHandler(
            $this->updateRepository,
            $this->scriptFinder,
            $this->locker,
            $this->dbmsValidator,
            $this->moduleRepository,
            $this->widgetRepository,
            $this->engineContextWriter,
            $this->cacheClearer,
        );

        // Record the emitted upgrade events so the start/failure lifecycle can be asserted without
        // writing to the real upgrade channel. The facade is a singleton with a private constructor,
        // so the spy is wired through reflection.
        $this->loggerSpy = new class () extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             * @param mixed $level
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => is_scalar($level) ? (string) $level : '',
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $reflection = new \ReflectionClass(LoggerUpgrade::class);
        $facade = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('logger')->setValue($facade, $this->loggerSpy);
        $reflection->getProperty('instance')->setValue(null, $facade);
    }

    protected function tearDown(): void
    {
        // Drop the spy-backed singleton so it cannot leak into other test files sharing the process.
        (new \ReflectionClass(LoggerUpgrade::class))->getProperty('instance')->setValue(null, null);
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

        // A lock failure happens before start(): it is surfaced as a standalone upgrade.error,
        // never a dangling upgrade.failure.
        $events = $this->emittedEvents();
        self::assertNotContains('upgrade.start', $events);
        self::assertNotContains('upgrade.failure', $events);
        self::assertContains('upgrade.error', $events);
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

        // The version is read before start(): a failure here is surfaced as a standalone upgrade.error.
        $events = $this->emittedEvents();
        self::assertNotContains('upgrade.start', $events);
        self::assertNotContains('upgrade.failure', $events);
        self::assertContains('upgrade.error', $events);
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
        // upgrade.failure with no matching upgrade.start; instead a standalone upgrade.error keeps
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

        $events = $this->emittedEvents();
        self::assertNotContains('upgrade.start', $events);
        self::assertNotContains('upgrade.failure', $events);
        self::assertContains('upgrade.error', $events);

        // The error carries the current version (unknown here, as it failed before the version was read)
        // and the original message, pinning the handler call-site argument order.
        $error = $this->recordFor('upgrade.error');
        self::assertSame('unknown', $error['context']['version']);
        self::assertSame('MariaDB version 10.5 required', $error['message']);
    }

    public function testEmitsFailureAfterStartWhenAStepFails(): void
    {
        // A failure raised after start() must emit a balanced upgrade.failure, preceded by upgrade.start.
        $this->cacheClearer->method('clear')->willThrowException(new \RuntimeException('cache clear failed'));

        try {
            ($this->handler)(new UpdateCommand());
            self::fail('Expected the cache clear failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame('cache clear failed', $exception->getMessage());
        }

        $events = $this->emittedEvents();
        self::assertContains('upgrade.start', $events);
        self::assertContains('upgrade.failure', $events);
        // The post-start branch is exclusive: it must not also emit the pre-start upgrade.error.
        self::assertNotContains('upgrade.error', $events);
        self::assertLessThan(
            array_search('upgrade.failure', $events, true),
            array_search('upgrade.start', $events, true),
            'upgrade.start must be emitted before upgrade.failure'
        );

        // The failure routes the versions into from/to and the original message, pinning the handler
        // call-site argument order (currentVersion == targetVersion here, no updates available).
        $failure = $this->recordFor('upgrade.failure');
        self::assertSame('24.10.0', $failure['context']['from_version']);
        self::assertSame('24.10.0', $failure['context']['to_version']);
        self::assertSame('cache clear failed', $failure['message']);
    }

    /**
     * @return list<string> the ordered list of emitted upgrade event names
     */
    private function emittedEvents(): array
    {
        $events = [];
        foreach ($this->loggerSpy->records as $record) {
            $event = $record['context']['event'] ?? null;
            if (is_string($event)) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return array{level: string, message: string, context: array<string, mixed>} the first record for the event
     */
    private function recordFor(string $event): array
    {
        foreach ($this->loggerSpy->records as $record) {
            if (($record['context']['event'] ?? null) === $event) {
                return $record;
            }
        }

        self::fail(sprintf('No %s event was emitted', $event));
    }
}
