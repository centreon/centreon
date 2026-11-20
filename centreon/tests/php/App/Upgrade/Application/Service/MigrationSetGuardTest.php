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

namespace Tests\App\Upgrade\Application\Service;

use App\Upgrade\Application\Service\MigrationSetGuard;
use App\Upgrade\Domain\Exception\LockNotReleasedException;
use App\Upgrade\Domain\Exception\MigrationSetBusyException;
use App\Upgrade\Domain\Exception\ModuleNotInstalledException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationSetLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;

final class MigrationSetGuardTest extends TestCase
{
    private FakeUpdateLocker $updateLocker;

    private FakeMigrationSetLocker $migrationSetLocker;

    private FakeModuleRepository $moduleRepository;

    private MigrationSetGuard $guard;

    protected function setUp(): void
    {
        $this->updateLocker = new FakeUpdateLocker();
        $this->migrationSetLocker = new FakeMigrationSetLocker();
        $this->moduleRepository = new FakeModuleRepository(['centreon-open-tickets']);
        $this->guard = new MigrationSetGuard($this->updateLocker, $this->migrationSetLocker, $this->moduleRepository);
    }

    public function testRunsTheCentreonWebSetsUnderTheUpdateLock(): void
    {
        $result = new \stdClass();

        foreach ([null, MigrationSet::web(WebMigrationSetEnum::Configuration)] as $set) {
            $this->updateLocker->unlockCalled = false;

            self::assertSame($result, $this->guard->run($set, static fn (): \stdClass => $result));
            self::assertTrue($this->updateLocker->unlockCalled);
        }

        self::assertTrue($this->updateLocker->lockCalled);
        self::assertSame([], $this->migrationSetLocker->acquired);
    }

    public function testRunsAnInstalledModuleUnderItsOwnLock(): void
    {
        $set = MigrationSet::module('centreon-open-tickets');
        $result = new \stdClass();

        self::assertSame($result, $this->guard->run($set, static fn (): \stdClass => $result));

        self::assertSame(['module:centreon-open-tickets'], $this->migrationSetLocker->acquired);
        self::assertSame(['module:centreon-open-tickets'], $this->migrationSetLocker->released);
        self::assertFalse($this->updateLocker->lockCalled);
    }

    public function testRefusesAModuleThatIsNotInstalledBeforeLocking(): void
    {
        try {
            $this->guard->run(MigrationSet::module('centreon-bam-server'), static fn (): string => 'done');
            self::fail('Expected the module to be refused');
        } catch (ModuleNotInstalledException $exception) {
            self::assertStringContainsString('"centreon-bam-server" is not installed', $exception->getMessage());
        }

        self::assertSame([], $this->migrationSetLocker->acquired);
        self::assertFalse($this->updateLocker->lockCalled);
    }

    public function testRefusesWhenTheUpdateLockIsHeld(): void
    {
        $this->updateLocker->lockAvailable = false;
        $runs = new \ArrayObject();

        try {
            $this->guard->run(null, static function () use ($runs): void {
                $runs->append(true);
            });
            self::fail('Expected the update lock to be busy');
        } catch (MigrationSetBusyException $exception) {
            self::assertSame('An update is already in progress', $exception->getMessage());
        }

        self::assertCount(0, $runs);
        self::assertFalse($this->updateLocker->unlockCalled);
    }

    public function testRefusesWhenTheModuleLockIsHeld(): void
    {
        $this->migrationSetLocker->lockedSets = ['module:centreon-open-tickets'];

        $this->expectException(MigrationSetBusyException::class);
        $this->expectExceptionMessage('is already being migrated or rolled back');

        $this->guard->run(MigrationSet::module('centreon-open-tickets'), static fn (): string => 'done');
    }

    public function testReportsTheFailureOfTheRunNotTheOneOfTheUnlock(): void
    {
        $this->updateLocker->unlockFailure = new \RuntimeException('Semaphore removed');
        $this->migrationSetLocker->unlockFailure = new \RuntimeException('Semaphore removed');
        $failure = new \RuntimeException('Migration failed');

        foreach ([null, MigrationSet::module('centreon-open-tickets')] as $set) {
            $caught = null;

            try {
                $this->guard->run($set, static function () use ($failure): string {
                    throw $failure;
                });
            } catch (\RuntimeException $exception) {
                $caught = $exception;
            }

            self::assertSame($failure, $caught);
        }

        self::assertTrue($this->updateLocker->unlockCalled);
        self::assertSame(['module:centreon-open-tickets'], $this->migrationSetLocker->released);
    }

    public function testKeepsTheResultWhenTheLockCannotBeReleasedAfterASuccess(): void
    {
        $this->migrationSetLocker->unlockFailure = new \RuntimeException('Semaphore removed');

        try {
            $this->guard->run(MigrationSet::module('centreon-open-tickets'), static fn (): string => 'done');
            self::fail('Expected the lock release to fail');
        } catch (LockNotReleasedException $exception) {
            self::assertSame('done', $exception->result);
            self::assertSame('Completed, but releasing the lock failed: Semaphore removed', $exception->getMessage());
        }
    }
}
