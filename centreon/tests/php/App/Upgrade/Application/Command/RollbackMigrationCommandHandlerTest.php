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
use App\Upgrade\Application\Service\MigrationApplier;
use App\Upgrade\Application\Service\MigrationSetGuard;
use App\Upgrade\Application\Service\TargetVersionRecorder;
use App\Upgrade\Domain\Exception\LockNotReleasedException;
use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\ModuleNotInstalledException;
use App\Upgrade\Domain\Exception\RollbackPlanChangedException;
use App\Upgrade\Domain\Exception\RollbackTargetNotFoundException;
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

final class RollbackMigrationCommandHandlerTest extends TestCase
{
    private const array CONFIRMED = ['Centreon\\Migrations\\Configuration\\Version1'];

    public function testRollsTheSetBackUnderTheUpdateLock(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $migrationRunner->warnings['configuration'] = ['2 rows stay without host'];
        $locker = new FakeUpdateLocker();

        $reverted = ($this->handler($migrationRunner, new MigrationSetGuard($locker, new FakeMigrationSetLocker(), new FakeModuleRepository(['centreon-open-tickets'])), new FakeUpgradeLogger()))(new RollbackMigrationCommand(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev', self::CONFIRMED));

        self::assertEquals(new MigrationOutcome(['configuration\Version1'], ['2 rows stay without host']), $reverted);
        self::assertSame(['rollback:configuration:prev'], $migrationRunner->journal->getArrayCopy());
        self::assertSame([self::CONFIRMED], $migrationRunner->confirmedPlans, 'The runner refuses to revert other migrations than the confirmed ones');
        self::assertTrue($locker->unlockCalled);
    }

    public function testFailsWhenAnUpdateIsAlreadyInProgress(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $locker = new FakeUpdateLocker();
        $locker->lockAvailable = false;

        $logger = new FakeUpgradeLogger();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An update is already in progress');

        try {
            ($this->handler($migrationRunner, new MigrationSetGuard($locker, new FakeMigrationSetLocker(), new FakeModuleRepository(['centreon-open-tickets'])), $logger))(new RollbackMigrationCommand(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev', self::CONFIRMED));
        } finally {
            self::assertSame([], $migrationRunner->journal->getArrayCopy());
            self::assertSame(['warning'], array_column($logger->calls, 'method'));
        }
    }

    public function testRollsAModuleSetBack(): void
    {
        $migrationRunner = new FakeMigrationRunner();

        ($this->handler($migrationRunner, new MigrationSetGuard(new FakeUpdateLocker(), new FakeMigrationSetLocker(), new FakeModuleRepository(['centreon-open-tickets'])), new FakeUpgradeLogger()))(new RollbackMigrationCommand(MigrationSet::module('centreon-open-tickets'), 'prev', self::CONFIRMED));

        self::assertSame(['rollback:module:centreon-open-tickets:prev'], $migrationRunner->journal->getArrayCopy());
    }

    public function testAFailingUnlockAfterASuccessfulRollbackIsNotReportedAsAFailedRollback(): void
    {
        $migrationSetLocker = new FakeMigrationSetLocker();
        $migrationSetLocker->unlockFailure = new \RuntimeException('Semaphore removed');

        $logger = new FakeUpgradeLogger();
        $handler = $this->handler(new FakeMigrationRunner(), new MigrationSetGuard(new FakeUpdateLocker(), $migrationSetLocker, new FakeModuleRepository(['centreon-open-tickets'])), $logger);

        try {
            $handler(new RollbackMigrationCommand(MigrationSet::module('centreon-open-tickets'), 'prev', self::CONFIRMED));
            self::fail('Expected the unlock failure to propagate');
        } catch (LockNotReleasedException $exception) {
            self::assertEquals(new MigrationOutcome(['module:centreon-open-tickets\Version1']), $exception->result, 'The reverted migrations are still told');
            self::assertSame(
                'Rollback of the "centreon-open-tickets" module migration set: Completed, but releasing the lock failed: Semaphore removed',
                $logger->calls[array_key_last($logger->calls)]['message'] ?? null,
            );
        }
    }

    public function testLogsTheTraceOfAFailureRaisedBeforeTheRollbackStep(): void
    {
        $lockFailure = new \RuntimeException('Semaphore unavailable');
        $migrationSetLocker = new FakeMigrationSetLocker();
        $migrationSetLocker->lockFailure = $lockFailure;

        $logger = new FakeUpgradeLogger();

        try {
            $this->handler(new FakeMigrationRunner(), new MigrationSetGuard(new FakeUpdateLocker(), $migrationSetLocker, new FakeModuleRepository(['centreon-open-tickets'])), $logger)(
                new RollbackMigrationCommand(MigrationSet::module('centreon-open-tickets'), 'prev', self::CONFIRMED),
            );
            self::fail('Expected the lock failure to propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame($lockFailure, $exception);
        }

        self::assertSame(['error'], array_column($logger->calls, 'method'));
        self::assertSame($lockFailure, $logger->calls[0]['exception'] ?? null, 'No rollback step logged its trace');
    }

    public function testRefusesToRollAModuleNotInstalledBack(): void
    {
        // The rollback would create the metadata table of a module that is not there.
        $migrationRunner = new FakeMigrationRunner();
        $migrationSetLocker = new FakeMigrationSetLocker();
        $logger = new FakeUpgradeLogger();

        try {
            ($this->handler($migrationRunner, new MigrationSetGuard(new FakeUpdateLocker(), $migrationSetLocker, new FakeModuleRepository()), $logger))(new RollbackMigrationCommand(MigrationSet::module('centreon-open-tickets'), 'first', self::CONFIRMED));
            self::fail('Expected the rollback to be refused');
        } catch (ModuleNotInstalledException $exception) {
            self::assertSame('The module "centreon-open-tickets" is not installed, or does not exist: its migrations can only be run, rolled back or listed once it is installed.', $exception->getMessage());
        }

        self::assertSame([], $migrationRunner->journal->getArrayCopy());
        self::assertSame([], $migrationSetLocker->acquired, 'Refused before locking');
        self::assertSame([], $migrationSetLocker->released);
        self::assertSame(['warning'], array_column($logger->calls, 'method'));
    }

    public function testLogsTheRollbackStep(): void
    {
        $logger = new FakeUpgradeLogger();
        $this->rollbackHandler($logger)(new RollbackMigrationCommand(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev', self::CONFIRMED));
        self::assertSame(['step', 'stepCompleted'], array_column($logger->calls, 'method'));
        self::assertSame(['migrations_rollback:configuration'], array_unique(array_column($logger->calls, 'stepName')));
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

    /**
     * @return iterable<string, array{\RuntimeException}>
     */
    public static function provideChangesSinceThePlan(): iterable
    {
        yield 'a target gone' => [new RollbackTargetNotFoundException('Cannot roll the "configuration" migration set back to "Version1": it has not been executed.')];

        yield 'other migrations to revert' => [new RollbackPlanChangedException(MigrationSet::web(WebMigrationSetEnum::Configuration), ['Version2'])];
    }

    #[DataProvider('provideChangesSinceThePlan')]
    public function testAChangeSinceThePlanIsARefusal(\RuntimeException $change): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $migrationRunner->failure = $change;

        $logger = new FakeUpgradeLogger();
        $caught = null;

        try {
            $this->handler($migrationRunner, new MigrationSetGuard(new FakeUpdateLocker(), new FakeMigrationSetLocker(), new FakeModuleRepository()), $logger)(
                new RollbackMigrationCommand(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev', self::CONFIRMED),
            );
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertSame($change, $caught);
        self::assertSame(['step', 'stepFailure', 'warning'], array_column($logger->calls, 'method'));
        self::assertSame('Rollback of the "configuration" migration set refused: ' . $change->getMessage(), $logger->calls[2]['message'] ?? null);
    }

    #[DataProvider('provideStepFailures')]
    public function testLogsTheTraceOfAStepFailureOnce(\Throwable $failure): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $migrationRunner->failure = $failure;

        $logger = new FakeUpgradeLogger();

        try {
            $this->handler($migrationRunner, new MigrationSetGuard(new FakeUpdateLocker(), new FakeMigrationSetLocker(), new FakeModuleRepository()), $logger)(
                new RollbackMigrationCommand(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev', self::CONFIRMED),
            );
            self::fail('Expected the step failure to propagate');
        } catch (\Throwable $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame(['step', 'stepFailure', 'error'], array_column($logger->calls, 'method'));
        self::assertSame($failure, $logger->calls[1]['exception'] ?? null);
        self::assertNull($logger->calls[2]['exception'] ?? null);
    }

    private function rollbackHandler(FakeUpgradeLogger $logger): RollbackMigrationCommandHandler
    {
        return $this->handler(new FakeMigrationRunner(), new MigrationSetGuard(new FakeUpdateLocker(), new FakeMigrationSetLocker(), new FakeModuleRepository()), $logger);
    }

    private function handler(FakeMigrationRunner $migrationRunner, MigrationSetGuard $guard, FakeUpgradeLogger $logger): RollbackMigrationCommandHandler
    {
        $recorder = new TargetVersionRecorder(new FakeUpdateRepository(), new FakeUpdateScriptFinder(), '26.11.0', new FakeUpgradeLogger());

        return new RollbackMigrationCommandHandler($guard, new MigrationApplier($migrationRunner, $recorder, $logger, '26.11.0'), $logger, '26.11.0');
    }
}
