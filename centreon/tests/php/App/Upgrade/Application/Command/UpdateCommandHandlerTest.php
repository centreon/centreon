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
use App\Upgrade\Domain\Event\UpgradeCompleted;
use App\Upgrade\Domain\Event\UpgradeFailed;
use App\Upgrade\Domain\Event\UpgradeStarted;
use App\Upgrade\Domain\Event\UpgradeStepFailed;
use App\Upgrade\Domain\Event\UpgradeStepStarted;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Domain\Repository\WidgetRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeEventDispatcher;
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

    private FakeEventDispatcher $events;

    private UpdateCommandHandler $handler;

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
        $this->events = new FakeEventDispatcher();

        $this->handler = new UpdateCommandHandler(
            $this->updateRepository,
            $this->scriptFinder,
            $this->locker,
            $this->dbmsValidator,
            $this->moduleRepository,
            $this->widgetRepository,
            $this->engineContextWriter,
            $this->cacheClearer,
            $this->events,
        );
    }

    public function testHappyPathNoUpdatesAvailable(): void
    {
        $this->scriptFinder->availableUpdates = [];

        ($this->handler)(new UpdateCommand());

        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertTrue($this->updateRepository->postUpdateCalled);
        self::assertTrue($this->locker->unlockCalled);

        // No update script to run, so the lifecycle is start -> 5 internal steps -> completed.
        $started = $this->firstEventOfType(UpgradeStarted::class);
        self::assertSame('24.10.0', $started->fromVersion);
        self::assertSame('24.10.0', $started->toVersion);

        self::assertSame(
            ['post_update', 'modules_update', 'widgets_update', 'engine_context', 'cache_clear'],
            $this->stepStartedNames(),
        );

        $completed = $this->firstEventOfType(UpgradeCompleted::class);
        self::assertSame('24.10.0', $completed->fromVersion);
        self::assertSame('24.10.0', $completed->toVersion);

        self::assertSame([], $this->eventsOfType(UpgradeStepFailed::class));
        self::assertSame([], $this->eventsOfType(UpgradeFailed::class));
    }

    public function testHappyPathUpdatesApplied(): void
    {
        $this->scriptFinder->availableUpdates = ['24.10.1', '24.10.2'];

        ($this->handler)(new UpdateCommand());

        self::assertSame(['24.10.1', '24.10.2'], $this->updateRepository->updatesRun);
        self::assertTrue($this->updateRepository->postUpdateCalled);
        self::assertTrue($this->locker->unlockCalled);

        // toVersion is the last available update.
        $started = $this->firstEventOfType(UpgradeStarted::class);
        self::assertSame('24.10.0', $started->fromVersion);
        self::assertSame('24.10.2', $started->toVersion);

        // One run_update step per available update, then the internal post-update steps.
        self::assertSame(
            ['run_update', 'run_update', 'post_update', 'modules_update', 'widgets_update', 'engine_context', 'cache_clear'],
            $this->stepStartedNames(),
        );
        $runUpdateSteps = array_values(array_filter(
            $this->eventsOfType(UpgradeStepStarted::class),
            static fn (UpgradeStepStarted $event): bool => $event->step === 'run_update',
        ));
        self::assertSame('24.10.1', $runUpdateSteps[0]->version);
        self::assertSame('24.10.2', $runUpdateSteps[1]->version);

        $completed = $this->firstEventOfType(UpgradeCompleted::class);
        self::assertSame('24.10.0', $completed->fromVersion);
        self::assertSame('24.10.2', $completed->toVersion);

        self::assertSame([], $this->eventsOfType(UpgradeFailed::class));
    }

    public function testThrowsWhenLockAlreadyAcquired(): void
    {
        $this->locker->lockAvailable = false;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already in progress/');

        ($this->handler)(new UpdateCommand());
    }

    public function testThrowsWhenCurrentVersionCannotBeRetrieved(): void
    {
        $this->updateRepository->currentVersion = null;

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

            public function runUpdate(string $version): void
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
            $this->events,
        );

        $this->expectException(\RuntimeException::class);

        try {
            $handler(new UpdateCommand());
        } finally {
            self::assertTrue($this->locker->unlockCalled, 'Lock must be released even after a failure');

            // The failing step must be reported, and the global failure must follow it.
            $stepFailed = $this->firstEventOfType(UpgradeStepFailed::class);
            self::assertSame('run_update', $stepFailed->step);
            self::assertSame('24.10.1', $stepFailed->version);
            self::assertSame('SQL error during update', $stepFailed->message);

            $failed = $this->firstEventOfType(UpgradeFailed::class);
            self::assertSame('24.10.0', $failed->fromVersion);
            self::assertSame('24.10.1', $failed->toVersion);
            self::assertSame('SQL error during update', $failed->message);
            self::assertInstanceOf(\RuntimeException::class, $failed->exception);

            // A failed upgrade must never emit a completion event.
            self::assertSame([], $this->eventsOfType(UpgradeCompleted::class));
        }
    }

    public function testEngineContextAndCacheAreCalledOnSuccess(): void
    {
        $this->engineContextWriter->expects(self::once())->method('writeIfMissing');
        $this->cacheClearer->expects(self::once())->method('clear');

        ($this->handler)(new UpdateCommand());
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
     * @return list<string>
     */
    private function stepStartedNames(): array
    {
        return array_map(
            static fn (UpgradeStepStarted $event): string => $event->step,
            $this->eventsOfType(UpgradeStepStarted::class),
        );
    }
}
