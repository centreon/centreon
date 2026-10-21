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

use App\Upgrade\Application\Command\RollbackMigrationCommand;
use App\Upgrade\Application\Command\RollbackMigrationCommandHandler;
use App\Upgrade\Application\MigrationSetGuard;
use App\Upgrade\Application\ModuleNotInstalledException;
use App\Upgrade\Domain\MigrationSet;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationSetLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;

final class RollbackMigrationCommandHandlerTest extends TestCase
{
    public function testRollsTheSetBackUnderTheUpdateLock(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $locker = new FakeUpdateLocker();

        $reverted = (new RollbackMigrationCommandHandler($migrationRunner, new MigrationSetGuard($locker, new FakeMigrationSetLocker(), new FakeModuleRepository(['centreon-open-tickets']))))(new RollbackMigrationCommand(MigrationSet::core('configuration'), 'prev'));

        self::assertSame(['configuration\Version1'], $reverted);
        self::assertSame(['rollback:configuration:prev'], $migrationRunner->journal->getArrayCopy());
        self::assertTrue($locker->unlockCalled);
    }

    public function testPassesADryRunToTheRunner(): void
    {
        $migrationRunner = new FakeMigrationRunner();

        (new RollbackMigrationCommandHandler($migrationRunner, new MigrationSetGuard(new FakeUpdateLocker(), new FakeMigrationSetLocker(), new FakeModuleRepository())))(new RollbackMigrationCommand(MigrationSet::core('configuration'), 'prev', dryRun: true));

        self::assertSame(['rollback-dry-run:configuration:prev'], $migrationRunner->journal->getArrayCopy());
    }

    public function testFailsWhenAnUpdateIsAlreadyInProgress(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $locker = new FakeUpdateLocker();
        $locker->lockAvailable = false;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An update is already in progress');

        try {
            (new RollbackMigrationCommandHandler($migrationRunner, new MigrationSetGuard($locker, new FakeMigrationSetLocker(), new FakeModuleRepository(['centreon-open-tickets']))))(new RollbackMigrationCommand(MigrationSet::core('configuration'), 'prev'));
        } finally {
            self::assertSame([], $migrationRunner->journal->getArrayCopy());
        }
    }

    public function testRollsAModuleSetBackUnderItsOwnLock(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $locker = new FakeUpdateLocker();
        // The update lock is not needed for a module set.
        $locker->lockAvailable = false;

        $migrationSetLocker = new FakeMigrationSetLocker();

        (new RollbackMigrationCommandHandler($migrationRunner, new MigrationSetGuard($locker, $migrationSetLocker, new FakeModuleRepository(['centreon-open-tickets']))))(new RollbackMigrationCommand(MigrationSet::module('centreon-open-tickets'), 'prev'));

        self::assertSame(['rollback:module:centreon-open-tickets:prev'], $migrationRunner->journal->getArrayCopy());
        self::assertSame(['module:centreon-open-tickets'], $migrationSetLocker->released);

        $migrationSetLocker->lockedSets = ['module:centreon-open-tickets'];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is already being migrated');
        (new RollbackMigrationCommandHandler($migrationRunner, new MigrationSetGuard($locker, $migrationSetLocker, new FakeModuleRepository(['centreon-open-tickets']))))(new RollbackMigrationCommand(MigrationSet::module('centreon-open-tickets'), 'prev'));
    }

    public function testReleasesTheLocksWhenTheRollbackFails(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $migrationRunner->failingSets = ['configuration', 'module:centreon-open-tickets'];

        $locker = new FakeUpdateLocker();
        $migrationSetLocker = new FakeMigrationSetLocker();
        $handler = new RollbackMigrationCommandHandler($migrationRunner, new MigrationSetGuard($locker, $migrationSetLocker, new FakeModuleRepository(['centreon-open-tickets'])));

        foreach ([MigrationSet::core('configuration'), MigrationSet::module('centreon-open-tickets')] as $set) {
            try {
                $handler(new RollbackMigrationCommand($set, 'prev'));
                self::fail('Expected the rollback failure to propagate');
            } catch (\RuntimeException $exception) {
                self::assertSame(\sprintf('Rollback of "%s" failed', $set->name), $exception->getMessage());
            }
        }

        self::assertTrue($locker->unlockCalled);
        self::assertSame(['module:centreon-open-tickets'], $migrationSetLocker->released);
    }

    public function testRefusesToRollAModuleNotInstalledBack(): void
    {
        // Its uninstallation dropped the metadata table, which the rollback would create again.
        $migrationRunner = new FakeMigrationRunner();
        $migrationSetLocker = new FakeMigrationSetLocker();

        try {
            (new RollbackMigrationCommandHandler($migrationRunner, new MigrationSetGuard(new FakeUpdateLocker(), $migrationSetLocker, new FakeModuleRepository())))(new RollbackMigrationCommand(MigrationSet::module('centreon-open-tickets'), 'first'));
            self::fail('Expected the rollback to be refused');
        } catch (ModuleNotInstalledException $exception) {
            self::assertSame('The module "centreon-open-tickets" is not installed, or does not exist: its migrations can only be run, rolled back or listed once it is installed.', $exception->getMessage());
        }

        self::assertSame([], $migrationRunner->journal->getArrayCopy());
        self::assertSame([], $migrationSetLocker->released, 'Refused before locking');
    }
}
