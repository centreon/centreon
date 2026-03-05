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
use App\Upgrade\Application\ModuleUpdater;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;

final class UpdateCommandHandlerTest extends TestCase
{
    private FakeUpdateRepository $updateRepository;

    private FakeUpdateScriptFinder $scriptFinder;

    private FakeUpdateLocker $locker;

    private DbmsVersionValidator&MockObject $dbmsValidator;

    private ModuleUpdater&MockObject $moduleUpdater;

    private EngineContextWriter&MockObject $engineContextWriter;

    private CacheClearer&MockObject $cacheClearer;

    private UpdateCommandHandler $handler;

    protected function setUp(): void
    {
        $this->updateRepository = new FakeUpdateRepository();
        $this->scriptFinder = new FakeUpdateScriptFinder();
        $this->locker = new FakeUpdateLocker();
        $this->dbmsValidator = $this->createMock(DbmsVersionValidator::class);
        $this->moduleUpdater = $this->createMock(ModuleUpdater::class);
        $this->engineContextWriter = $this->createMock(EngineContextWriter::class);
        $this->cacheClearer = $this->createMock(CacheClearer::class);

        $this->handler = new UpdateCommandHandler(
            $this->updateRepository,
            $this->scriptFinder,
            $this->locker,
            $this->dbmsValidator,
            $this->moduleUpdater,
            $this->engineContextWriter,
            $this->cacheClearer,
            new NullLogger(),
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

        self::assertSame(['24.10.1', '24.10.2'], $this->updateRepository->updatesRun);
        self::assertTrue($this->updateRepository->postUpdateCalled);
        self::assertTrue($this->locker->unlockCalled);
    }

    public function testThrowsWhenInstallsDirNotWritable(): void
    {
        $this->scriptFinder->installsDirWritable = false;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/installs backup directory/');

        ($this->handler)(new UpdateCommand());
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
            $this->moduleUpdater,
            $this->engineContextWriter,
            $this->cacheClearer,
            new NullLogger(),
        );

        $this->expectException(\RuntimeException::class);

        try {
            $handler(new UpdateCommand());
        } finally {
            self::assertTrue($this->locker->unlockCalled, 'Lock must be released even after a failure');
        }
    }

    public function testEngineContextAndCacheAreCalledOnSuccess(): void
    {
        $this->engineContextWriter->expects(self::once())->method('writeIfMissing');
        $this->cacheClearer->expects(self::once())->method('clear');

        ($this->handler)(new UpdateCommand());
    }
}
