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
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationSetLocker;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateLocker;

final class RollbackMigrationCommandHandlerTest extends TestCase
{
    public function testRollsTheSetBackUnderTheUpdateLock(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $locker = new FakeUpdateLocker();

        $reverted = (new RollbackMigrationCommandHandler($migrationRunner, $locker, new FakeMigrationSetLocker()))(new RollbackMigrationCommand('configuration', 'prev'));

        self::assertSame(['configuration\Version1'], $reverted);
        self::assertSame(['rollback:configuration:prev'], $migrationRunner->journal->getArrayCopy());
        self::assertTrue($locker->unlockCalled);
    }

    public function testFailsWhenAnUpdateIsAlreadyInProgress(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $locker = new FakeUpdateLocker();
        $locker->lockAvailable = false;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An update is already in progress');

        try {
            (new RollbackMigrationCommandHandler($migrationRunner, $locker, new FakeMigrationSetLocker()))(new RollbackMigrationCommand('configuration', 'prev'));
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

        (new RollbackMigrationCommandHandler($migrationRunner, $locker, $migrationSetLocker))(new RollbackMigrationCommand('module:centreon-open-tickets', 'prev'));

        self::assertSame(['rollback:module:centreon-open-tickets:prev'], $migrationRunner->journal->getArrayCopy());
        self::assertSame(['module:centreon-open-tickets'], $migrationSetLocker->released);

        $migrationSetLocker->lockedSets = ['module:centreon-open-tickets'];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is already being migrated');
        (new RollbackMigrationCommandHandler($migrationRunner, $locker, $migrationSetLocker))(new RollbackMigrationCommand('module:centreon-open-tickets', 'prev'));
    }
}
