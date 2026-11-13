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

namespace Tests\App\Upgrade\Infrastructure\Doctrine;

use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\RollbackPlanChangedException;
use App\Upgrade\Domain\Exception\RollbackTargetNotFoundException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use App\Upgrade\Infrastructure\Doctrine\MigrationFactoryProvider;
use App\Upgrade\Infrastructure\Doctrine\MigrationRunner;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetConfiguration;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Tests\App\Upgrade\Infrastructure\Double\NonDbalConnectionRegistry;
use Tests\App\Upgrade\Infrastructure\Double\SingleConnectionRegistry;
use Tests\App\Upgrade\Infrastructure\Double\TrackingConnection;

/**
 * Uses a dedicated database: the DAMA transaction would hide the transactional behaviour under test.
 */
final class MigrationRunnerTest extends TestCase
{
    private const string DATABASE = 'centreon_migrations_test';
    private const string ATOMICITY = 'atomicity';
    private const string ROLLBACK = 'rollback';

    private Connection $connection;

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        $root = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->env('hostCentreon'),
            'port' => (int) $this->env('port'),
            'user' => $this->env('rootUser', 'root'),
            'password' => $this->env('rootPassword', $this->env('password')),
        ]);
        $root->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', self::DATABASE));
        $root->executeStatement(sprintf('CREATE DATABASE `%s`', self::DATABASE));
        $root->executeStatement(sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'", self::DATABASE, $this->env('user')));
        $root->close();

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->env('hostCentreon'),
            'port' => (int) $this->env('port'),
            'user' => $this->env('user'),
            'password' => $this->env('password'),
            'dbname' => self::DATABASE,
        ]);

        $this->runner = new MigrationRunner(
            new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/modules', new NullLogger()),
            new MigrationFactoryProvider(new SingleConnectionRegistry($this->connection), new NullLogger(), '26.12.0'),
        );
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', self::DATABASE));
        $this->connection->close();
    }

    public function testMigrateCreatesTheMetadataTableAndAppliesEveryMigration(): void
    {
        self::assertSame([\Rollback\Migrations\Version20260101000001::class, \Rollback\Migrations\Version20260101000002::class], $this->runner->migrate(MigrationSet::module(self::ROLLBACK))->executed);

        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_rollback']));
        self::assertSame([1], $this->rows('fx_rollback'));
        self::assertSame([], $this->runner->migrate(MigrationSet::module(self::ROLLBACK))->executed, 'A second run has nothing to execute');
    }

    public function testAFailingDmlMigrationIsRolledBackAndStopsTheRunAfterADdlMigration(): void
    {
        try {
            $this->runner->migrate(MigrationSet::module(self::ATOMICITY));
            self::fail('Expected the duplicate key to fail the run');
        } catch (\RuntimeException $exception) {
            self::assertStringStartsWith('The "Atomicity\Migrations\Version20260101000003" migration of the "atomicity" module migration set failed: ', $exception->getMessage());
        }

        // The DDL migration committed implicitly: the DML ones must still run in real transactions.
        self::assertSame([1], $this->rows('fx_atomicity'), 'The failing migration must leave no row behind');
        self::assertSame(
            [\Atomicity\Migrations\Version20260101000001::class, \Atomicity\Migrations\Version20260101000002::class],
            $this->runner->status(MigrationSet::module(self::ATOMICITY))->executed,
        );
        self::assertSame([\Atomicity\Migrations\Version20260101000003::class], $this->runner->status(MigrationSet::module(self::ATOMICITY))->pending);
    }

    public function testAFailingDdlMigrationStopsTheRunAndStaysPending(): void
    {
        try {
            $this->runner->migrate(MigrationSet::module('ddl-failure'));
            self::fail('Expected the missing table to fail the run');
        } catch (MigrationFailedException $exception) {
            self::assertSame(\DdlFailure\Migrations\Version20260101000002::class, $exception->version);
            self::assertStringStartsWith('The "DdlFailure\Migrations\Version20260101000002" migration of the "ddl-failure" module migration set failed: ', $exception->getMessage());
            self::assertStringEndsWith('. Completed before it in this set: DdlFailure\Migrations\Version20260101000001.', $exception->getMessage(), 'The operator knows what the run left applied');
        }

        $status = $this->runner->status(MigrationSet::module('ddl-failure'));
        self::assertSame([\DdlFailure\Migrations\Version20260101000001::class], $status->executed, 'The DDL migration before it stays applied');
        self::assertSame([\DdlFailure\Migrations\Version20260101000002::class], $status->pending, 'The next run replays it');
    }

    public function testAFailingRevertStopsTheRollbackAndNamesItsMigration(): void
    {
        $this->runner->migrate(MigrationSet::module('revert-failure'));

        try {
            $this->revert(MigrationSet::module('revert-failure'), 'first');
            self::fail('Expected the missing table to fail the rollback');
        } catch (\RuntimeException $exception) {
            self::assertStringStartsWith('The "RevertFailure\Migrations\Version20260101000001" migration of the "revert-failure" module migration set failed: ', $exception->getMessage());
            self::assertStringEndsWith('. Completed before it in this set: RevertFailure\Migrations\Version20260101000002.', $exception->getMessage(), 'The operator knows what the rollback already reverted');
        }

        self::assertSame([\RevertFailure\Migrations\Version20260101000001::class], $this->runner->status(MigrationSet::module('revert-failure'))->executed, 'The migration reverted before it stays reverted');
        self::assertSame([], $this->rows('fx_revert_failure'));
    }

    public function testASkippedMigrationFailsTheRunAndStaysPending(): void
    {
        try {
            $this->runner->migrate(MigrationSet::module('skipping'));
            self::fail('Expected the skipped migration to fail the run');
        } catch (\RuntimeException $exception) {
            self::assertSame('The "skipping" module migration set skipped Skipping\Migrations\Version20260101000002 ("Nothing to change"): a migration must run or fail, never skip. Completed before it in this set: Skipping\Migrations\Version20260101000001.', $exception->getMessage());
        }

        self::assertSame(
            [\Skipping\Migrations\Version20260101000002::class, \Skipping\Migrations\Version20260101000003::class],
            $this->runner->status(MigrationSet::module('skipping'))->pending,
            'Nothing runs past a skipped migration',
        );
    }

    public function testASkippedRevertFailsTheRollbackWithItsReason(): void
    {
        $set = MigrationSet::module('skipping-revert');
        $this->runner->migrate($set);

        try {
            $this->runner->rollback($set, 'first', $this->runner->planRollback($set, 'first'));
            self::fail('Expected the skipped revert to fail the rollback');
        } catch (MigrationFailedException $exception) {
            self::assertStringContainsString('skipped SkippingRevert\Migrations\Version20260101000002 ("Nothing to revert")', $exception->getMessage());
        }

        self::assertSame(
            [\SkippingRevert\Migrations\Version20260101000001::class, \SkippingRevert\Migrations\Version20260101000002::class],
            $this->runner->status($set)->executed,
            'A skipped revert stays executed, and nothing is reverted past it',
        );
    }

    public function testAStatementCommittingTheTransactionOfADmlMigrationFailsIt(): void
    {
        try {
            $this->runner->migrate(MigrationSet::module('implicit-commit'));
            self::fail('Expected the committed transaction to fail the migration');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('committed its transaction', $exception->getMessage());
        }

        self::assertSame([\ImplicitCommit\Migrations\Version20260101000001::class], $this->runner->status(MigrationSet::module('implicit-commit'))->pending);
        self::assertSame([], $this->rows('fx_implicit_commit'), 'No statement of the migration runs once its transaction is committed');
    }

    public function testAStatementCommittingTheTransactionOfADmlRevertFailsIt(): void
    {
        $set = MigrationSet::module('implicit-commit-revert');
        $this->runner->migrate($set);

        try {
            $this->revert($set, 'prev');
            self::fail('Expected the committed transaction to fail the rollback');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('committed its transaction', $exception->getMessage());
        }

        self::assertSame([\ImplicitCommitRevert\Migrations\Version20260101000001::class, \ImplicitCommitRevert\Migrations\Version20260101000002::class], $this->runner->status($set)->executed);
        self::assertSame([1], $this->rows('fx_implicit_commit_revert'), 'No statement of the revert runs once its transaction is committed');
    }

    public function testRunsOnTheActualServerWhateverServerVersionIsConfigured(): void
    {
        // As in production, where the DoctrineBundle connections declare a MariaDB server.
        $connection = DriverManager::getConnection([...$this->connection->getParams(), 'serverVersion' => 'mariadb-11.8.0']);
        $runner = new MigrationRunner(
            new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/modules', new NullLogger()),
            new MigrationFactoryProvider(new SingleConnectionRegistry($connection), new NullLogger(), '26.12.0'),
        );

        $runner->migrate(MigrationSet::module(self::ROLLBACK));

        // The metadata table now exists: Doctrine introspects it, with the platform of the actual server.
        self::assertSame([], $runner->migrate(MigrationSet::module(self::ROLLBACK))->executed);
        self::assertCount(2, $runner->status(MigrationSet::module(self::ROLLBACK))->executed);
    }

    public function testRefusesToRunInsideAnOpenTransaction(): void
    {
        // As from a transactional command: a DDL migration would wait forever for the locks of that transaction.
        $this->connection->beginTransaction();

        try {
            $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
            self::fail('Expected the open transaction to be refused');
        } catch (\LogicException $exception) {
            self::assertSame('Migrations cannot run inside a transaction: the "default" connection has one open.', $exception->getMessage());
        } finally {
            $this->connection->rollBack();
        }

        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_rollback']), 'Nothing ran');
    }

    public function testRefusesToRollBackInsideAnOpenTransaction(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
        $this->connection->beginTransaction();

        try {
            $this->runner->rollback(MigrationSet::module(self::ROLLBACK), 'prev', [\Rollback\Migrations\Version20260101000002::class]);
            self::fail('Expected the open transaction to be refused');
        } catch (\LogicException $exception) {
            self::assertSame('Migrations cannot run inside a transaction: the "default" connection has one open.', $exception->getMessage());
        } finally {
            $this->connection->rollBack();
        }

        self::assertCount(2, $this->runner->status(MigrationSet::module(self::ROLLBACK))->executed, 'Nothing was reverted');
    }

    public function testReadsInsideAnOpenTransaction(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
        $this->connection->beginTransaction();

        try {
            self::assertCount(2, $this->runner->status(MigrationSet::module(self::ROLLBACK))->executed);
            self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->runner->planRollback(MigrationSet::module(self::ROLLBACK), 'prev'));
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testClosesTheConnectionOfEveryOperation(): void
    {
        $shared = DriverManager::getConnection([...$this->connection->getParams(), 'wrapperClass' => TrackingConnection::class]);
        $factories = new MigrationFactoryProvider(new SingleConnectionRegistry($shared), new NullLogger(), '26.12.0');
        $runner = new MigrationRunner(new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/modules', new NullLogger()), $factories);
        TrackingConnection::reset();

        $runner->migrate(MigrationSet::module(self::ROLLBACK));
        $runner->status(MigrationSet::module(self::ROLLBACK));
        $runner->rollback(MigrationSet::module(self::ROLLBACK), 'prev', $runner->planRollback(MigrationSet::module(self::ROLLBACK), 'prev'));

        try {
            (new MigrationRunner(new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/invalid-modules', new NullLogger()), $factories))->migrate(MigrationSet::module('no-description'));
            self::fail('Expected the set to be refused');
        } catch (MigrationSetRefusedException) {
        }

        // Without its directory, a module set only reads its metadata table.
        $runner->migrate(MigrationSet::module('without-migrations'));

        self::assertCount(6, TrackingConnection::connected(), 'One connection per operation');
        foreach (TrackingConnection::connected() as $connection) {
            self::assertFalse($connection->isConnected());
        }
    }

    public function testRollbackPrevRevertsTheLastMigrationAndMigrateReappliesIt(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->revert(MigrationSet::module(self::ROLLBACK), 'prev'));
        self::assertSame([], $this->rows('fx_rollback'));
        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->runner->status(MigrationSet::module(self::ROLLBACK))->pending);

        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->runner->migrate(MigrationSet::module(self::ROLLBACK))->executed);
        self::assertSame([1], $this->rows('fx_rollback'));
    }

    public function testRollbackAcceptsAShortVersionAndFirst(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->revert(MigrationSet::module(self::ROLLBACK), 'Version20260101000001'));
        self::assertSame([\Rollback\Migrations\Version20260101000001::class], $this->revert(MigrationSet::module(self::ROLLBACK), 'first'));
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['fx_rollback']));
    }

    public function testRollbackAcceptsATimestampAsTheGeneratedFileNamesEnd(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->revert(MigrationSet::module(self::ROLLBACK), '20260101000001'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownTargets(): iterable
    {
        yield 'an unknown version' => ['Version2026'];

        yield 'next, with nothing pending' => ['next'];

        yield 'further back than the executed migrations' => ['current-9'];
    }

    #[DataProvider('provideUnknownTargets')]
    public function testRollbackNamesTheAcceptedFormsOfAnUnknownVersion(string $target): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        $this->expectException(RollbackTargetNotFoundException::class);
        $this->expectExceptionMessage(sprintf('The "rollback" module migration set has no "%s" migration: pass a version listed by centreon:migrations:status -v', $target));

        $this->runner->planRollback(MigrationSet::module(self::ROLLBACK), $target);
    }

    public function testRollbackRefusesToMigrateUp(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
        $this->revert(MigrationSet::module(self::ROLLBACK), 'first');

        $this->expectException(RollbackTargetNotFoundException::class);
        $this->expectExceptionMessage('Cannot roll the "rollback" module migration set back to "Rollback\Migrations\Version20260101000002": it has not been executed.');

        $this->runner->planRollback(MigrationSet::module(self::ROLLBACK), 'Version20260101000002');
    }

    public function testARollbackPlanTellsWhatWouldBeRevertedWithoutRevertingIt(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        self::assertSame([\Rollback\Migrations\Version20260101000002::class, \Rollback\Migrations\Version20260101000001::class], $this->runner->planRollback(MigrationSet::module(self::ROLLBACK), 'first'));
        self::assertSame([], $this->runner->status(MigrationSet::module(self::ROLLBACK))->pending);
    }

    public function testARollbackPlanWritesNothingNotEvenTheMetadataTable(): void
    {
        self::assertSame([], $this->runner->planRollback(MigrationSet::module(self::ROLLBACK), 'first'));
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_rollback']));
    }

    public function testARollbackRefusesToRevertOtherMigrationsThanTheConfirmedOnes(): void
    {
        // Another run changed the set while the rollback waited for its confirmation.
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        try {
            $this->runner->rollback(MigrationSet::module(self::ROLLBACK), 'prev', [\Rollback\Migrations\Version20260101000001::class]);
            self::fail('Expected the rollback to be refused');
        } catch (RollbackPlanChangedException $exception) {
            self::assertSame([\Rollback\Migrations\Version20260101000002::class], $exception->planned);
            self::assertStringContainsString('changed since they were confirmed', $exception->getMessage());
        }

        self::assertCount(2, $this->runner->status(MigrationSet::module(self::ROLLBACK))->executed, 'Nothing was reverted');
    }

    public function testRollbackRevertsOnlyTheExecutedMigrationsAfterTheTargetWhenAnOlderOneIsPending(): void
    {
        // A fix forward-ported with an older timestamp than the migrations already executed, still pending.
        $this->runner->migrate(MigrationSet::module('forward-port'));
        $this->connection->delete('doctrine_migration_versions_forward_port', ['version' => \ForwardPort\Migrations\Version20260101000001::class]);

        self::assertSame([\ForwardPort\Migrations\Version20260101000003::class], $this->revert(MigrationSet::module('forward-port'), 'prev'));
        self::assertSame([\ForwardPort\Migrations\Version20260101000001::class, \ForwardPort\Migrations\Version20260101000003::class], $this->runner->status(MigrationSet::module('forward-port'))->pending);
    }

    public function testMigrateRunsAPendingMigrationOlderThanTheExecutedOnes(): void
    {
        $this->runner->migrate(MigrationSet::module('forward-port'));
        $this->connection->delete('doctrine_migration_versions_forward_port', ['version' => \ForwardPort\Migrations\Version20260101000001::class]);

        self::assertSame([\ForwardPort\Migrations\Version20260101000001::class], $this->runner->migrate(MigrationSet::module('forward-port'))->executed);
        self::assertSame([], $this->runner->status(MigrationSet::module('forward-port'))->pending);
    }

    public function testSetsAreIsolated(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        $status = $this->runner->status(MigrationSet::module(self::ATOMICITY));
        self::assertSame([], $status->executed);
        self::assertCount(3, $status->pending);
        self::assertFalse(
            $this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_atomicity']),
            'Reading a status must not create the metadata table',
        );
    }

    public function testAnExecutedMigrationMissingFromTheCodeBaseFailsTheRunBeforeAnyMigration(): void
    {
        $set = MigrationSet::module(self::ROLLBACK);
        $this->runner->migrate($set);
        $plan = $this->runner->planRollback($set, 'first');
        $this->runner->rollback($set, 'first', $plan);
        $this->connection->insert('doctrine_migration_versions_rollback', ['version' => 'Rollback\Migrations\Version20250101000000']);

        try {
            $this->runner->migrate($set);
            self::fail('Expected the set to be refused');
        } catch (MigrationSetRefusedException $exception) {
            self::assertStringContainsString('Rollback\Migrations\Version20250101000000', $exception->getMessage());
            self::assertStringContainsString('upgrade to a version that has it', $exception->getMessage());
            self::assertStringContainsString('"doctrine_migration_versions_rollback" table', $exception->getMessage());
        }

        self::assertNotContains($plan[0], $this->connection->fetchFirstColumn('SELECT version FROM doctrine_migration_versions_rollback'));
    }

    public function testTheStatusReportsAnExecutedMigrationMissingFromTheCodeBase(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
        $this->connection->insert('doctrine_migration_versions_rollback', ['version' => 'Rollback\Migrations\Version20250101000000']);

        $this->expectException(MigrationSetRefusedException::class);
        $this->expectExceptionMessage('Rollback\Migrations\Version20250101000000');

        $this->runner->status(MigrationSet::module(self::ROLLBACK));
    }

    public function testARollbackIsRefusedWhileAnExecutedMigrationIsMissingFromTheCodeBase(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
        $this->connection->insert('doctrine_migration_versions_rollback', ['version' => 'Rollback\Migrations\Version20250101000000']);

        $this->expectException(MigrationSetRefusedException::class);
        $this->expectExceptionMessage('Rollback\Migrations\Version20250101000000');

        $this->runner->planRollback(MigrationSet::module(self::ROLLBACK), 'prev');
    }

    public function testRollingBackASetWithoutMigrationDirectoryFailsClearly(): void
    {
        $this->expectException(RollbackTargetNotFoundException::class);
        $this->expectExceptionMessage('has no migration to roll back');

        $this->runner->planRollback(MigrationSet::module('without-migrations'), 'prev');
    }

    public function testASetWithoutMigrationDirectoryIsANoop(): void
    {
        self::assertSame([], $this->runner->migrate(MigrationSet::module('without-migrations'))->executed);
        self::assertEquals(
            MigrationSetStatus::read(MigrationSet::module('without-migrations'), [], []),
            $this->runner->status(MigrationSet::module('without-migrations')),
        );
        self::assertSame([], $this->connection->createSchemaManager()->listTableNames());
    }

    public function testAWebSetWithoutMigrationDirectoryFails(): void
    {
        $realtime = MigrationSet::web(WebMigrationSetEnum::Realtime);
        $message = 'The "realtime" migration set cannot run: "/nonexistent/migrations/realtime" does not exist or cannot be accessed.';

        foreach (['migrate', 'status'] as $method) {
            try {
                $this->runner->{$method}($realtime);
                self::fail(sprintf('%s() must fail without the migration directory', $method));
            } catch (\RuntimeException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
        self::assertSame([], $this->connection->createSchemaManager()->listTableNames());
    }

    public function testRefusesAMigrationDoctrineWouldIgnore(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must declare the "WrongNamespace\Migrations\Version20260101000001" migration');

        $this->invalidModulesRunner()->migrate(MigrationSet::module('wrong-namespace'));
    }

    public function testRefusesAMigrationRedeclaringALoadedClassWithoutLoadingIt(): void
    {
        require_once __DIR__ . '/Fixtures/invalid-modules/wrong-namespace/migrations/Version20260101000001.php';

        $this->expectException(MigrationSetRefusedException::class);
        $this->expectExceptionMessage('must declare the "CopiedClass\Migrations\Version20260101000001" migration');

        $this->invalidModulesRunner()->migrate(MigrationSet::module('copied-class'));
    }

    public function testRefusesAClassDeclaredInTheGlobalNamespaceOfAMigrationFile(): void
    {
        $this->expectException(MigrationSetRefusedException::class);
        $this->expectExceptionMessage('The migration "GlobalClassFixtureHelper" must be alone in a file named after it.');

        $this->invalidModulesRunner()->migrate(MigrationSet::module('global-class'));
    }

    public function testReturnsTheWarningsOfTheMigrations(): void
    {
        self::assertEquals(
            new MigrationOutcome([\Warning\Migrations\Version20260101000001::class], ['Warning\Migrations\Version20260101000001: 2 rows had no host and were left out']),
            $this->runner->migrate(MigrationSet::module('warning')),
        );
    }

    public function testReturnsTheWarningsOfARollback(): void
    {
        $set = MigrationSet::module('warning');
        $this->runner->migrate($set);

        self::assertEquals(
            new MigrationOutcome([\Warning\Migrations\Version20260101000001::class], ['Warning\Migrations\Version20260101000001: 2 rows stay without host']),
            $this->runner->rollback($set, 'first', $this->runner->planRollback($set, 'first')),
        );
    }

    public function testAFailureTellsTheWarningsOfTheMigrationsBeforeIt(): void
    {
        $this->expectException(MigrationFailedException::class);
        $this->expectExceptionMessage('. Completed before it in this set: WarningFailure\Migrations\Version20260101000001. Warnings: WarningFailure\Migrations\Version20260101000001: 2 rows had no host and were left out.');

        $this->runner->migrate(MigrationSet::module('warning-failure'));
    }

    public function testAClassConstantOrAnAnonymousClassIsNotAnotherClassOfTheFile(): void
    {
        self::assertSame([\AnonymousClass\Migrations\Version20260101000001::class], $this->runner->migrate(MigrationSet::module('anonymous-class'))->executed);
    }

    public function testRefusesAMigrationFileItCannotRead(): void
    {
        $runner = new MigrationRunner(
            new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/modules', new NullLogger()),
            new MigrationFactoryProvider(new SingleConnectionRegistry($this->connection), new NullLogger(), '26.12.0', readFile: static fn (string $file): false => false),
        );

        $this->expectException(MigrationSetRefusedException::class);
        $this->expectExceptionMessage(sprintf('Cannot read the migration file "%s": check its permissions.', __DIR__ . '/Fixtures/modules/rollback/migrations/Version20260101000001.php'));

        $runner->migrate(MigrationSet::module(self::ROLLBACK));
    }

    public function testRefusesAMigrationDirectoryItCannotListInsteadOfFindingNoMigration(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('root lists any directory');
        }

        $factories = new MigrationFactoryProvider(new SingleConnectionRegistry($this->connection), new NullLogger(), '26.12.0');
        $directory = sys_get_temp_dir() . '/' . uniqid('migrations_', true);
        mkdir($directory, 0o300);

        try {
            $factories->create(
                MigrationSet::module(self::ROLLBACK),
                new MigrationSetConfiguration('default', 'Rollback\\Migrations', $directory, 'doctrine_migration_versions_rollback'),
                MigrationSet::module(self::ROLLBACK)->migrationStep(),
                initialize: false,
            );
            self::fail('A directory that cannot be listed must not look empty');
        } catch (MigrationSetRefusedException $exception) {
            self::assertSame(sprintf('Cannot list the migrations of "%s": check its permissions.', $directory), $exception->getMessage());
        } finally {
            rmdir($directory);
        }
    }

    public function testRefusesAModuleSetWhoseDirectoryCannotBeTraversed(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('root traverses any directory');
        }

        $modulesDir = sys_get_temp_dir() . '/' . uniqid('modules_', true);
        mkdir($modulesDir . '/' . self::ROLLBACK . '/migrations', recursive: true);
        $runner = new MigrationRunner(
            new MigrationSetRegistry('/nonexistent', $modulesDir, new NullLogger()),
            new MigrationFactoryProvider(new SingleConnectionRegistry($this->connection), new NullLogger(), '26.12.0'),
        );

        try {
            foreach ([$modulesDir . '/' . self::ROLLBACK, $modulesDir] as $locked) {
                chmod($locked, 0o600);
                try {
                    $runner->migrate(MigrationSet::module(self::ROLLBACK));
                    self::fail('A directory that cannot be traversed must not look empty');
                } catch (MigrationSetRefusedException $exception) {
                    self::assertSame(sprintf('The "rollback" module migration set cannot run: cannot traverse "%s", check its permissions.', $locked), $exception->getMessage());
                } finally {
                    chmod($locked, 0o755);
                }
            }
        } finally {
            (new Filesystem())->remove($modulesDir);
        }
    }

    public function testRefusesMigrationsThatAreNotADirectory(): void
    {
        $modulesDir = sys_get_temp_dir() . '/' . uniqid('modules_', true);
        $migrations = $modulesDir . '/' . self::ROLLBACK . '/migrations';
        $filesystem = new Filesystem();
        $runner = new MigrationRunner(
            new MigrationSetRegistry('/nonexistent', $modulesDir, new NullLogger()),
            new MigrationFactoryProvider(new SingleConnectionRegistry($this->connection), new NullLogger(), '26.12.0'),
        );

        try {
            foreach (['a file' => static fn () => $filesystem->dumpFile($migrations, ''), 'a dangling link' => static fn () => $filesystem->symlink($modulesDir . '/missing', $migrations)] as $case => $create) {
                $filesystem->remove($migrations);
                $create();
                try {
                    $runner->migrate(MigrationSet::module(self::ROLLBACK));
                    self::fail(sprintf('Migrations as %s must not look like a module without migrations', $case));
                } catch (MigrationSetRefusedException $exception) {
                    self::assertSame(sprintf('The "rollback" module migration set cannot run: "%s" is not a directory.', $migrations), $exception->getMessage(), $case);
                }
            }
        } finally {
            $filesystem->remove($modulesDir);
        }
    }

    public function testRefusesAModuleSetWhoseDirectoryIsGoneWithExecutedMigrations(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        $modulesDir = sys_get_temp_dir() . '/' . uniqid('modules_', true);
        mkdir($modulesDir . '/' . self::ROLLBACK, recursive: true);
        $runner = new MigrationRunner(
            new MigrationSetRegistry('/nonexistent', $modulesDir, new NullLogger()),
            new MigrationFactoryProvider(new SingleConnectionRegistry($this->connection), new NullLogger(), '26.12.0'),
        );
        $message = sprintf(
            'The "rollback" module migration set has executed migrations, but "%s/rollback/migrations" does not exist or cannot be accessed: check the module files and their permissions.',
            $modulesDir,
        );

        try {
            foreach (['migrate', 'status', 'planRollback'] as $method) {
                try {
                    $method === 'planRollback' ? $runner->planRollback(MigrationSet::module(self::ROLLBACK), 'first') : $runner->{$method}(MigrationSet::module(self::ROLLBACK));
                    self::fail(sprintf('%s() must not find "no migration"', $method));
                } catch (MigrationSetRefusedException $exception) {
                    self::assertSame($message, $exception->getMessage());
                }
            }
        } finally {
            rmdir($modulesDir . '/' . self::ROLLBACK);
            rmdir($modulesDir);
        }
    }

    public function testRefusesAMigrationNotNamedAfterItsFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must declare the "MisnamedClass\Migrations\Version20260101000001" migration');

        $this->invalidModulesRunner()->migrate(MigrationSet::module('misnamed-class'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFilesDoctrineWouldNotFind(): iterable
    {
        yield 'a file not named Version<timestamp>.php' => ['stray-file', 'AddNotesColumn.php'];

        yield 'a subdirectory' => ['nested-file', '2026'];
    }

    #[DataProvider('provideFilesDoctrineWouldNotFind')]
    public function testRefusesAFileDoctrineWouldNeverRun(string $module, string $entry): void
    {
        $directory = __DIR__ . '/Fixtures/invalid-modules/' . $module . '/migrations';

        $this->expectException(MigrationSetRefusedException::class);
        $this->expectExceptionMessage(sprintf('"%s/%s" is not a migration file: name it Version<timestamp>.php, at the root of "%s".', $directory, $entry, $directory));

        $this->invalidModulesRunner()->migrate(MigrationSet::module($module));
    }

    public function testTheStatusReportsWhatMigrateWouldRefuse(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must declare the "MisnamedClass\Migrations\Version20260101000001" migration');

        $this->invalidModulesRunner()->status(MigrationSet::module('misnamed-class'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDeclarationsBesideTheMigration(): iterable
    {
        yield 'another migration' => ['two-classes', \TwoClasses\Migrations\Version20260101000002::class];

        yield 'an enum' => ['helper-enum', \HelperEnum\Migrations\Status::class];
    }

    #[DataProvider('provideDeclarationsBesideTheMigration')]
    public function testRefusesAMigrationSharingItsFile(string $module, string $declaration): void
    {
        $this->expectException(MigrationSetRefusedException::class);
        $this->expectExceptionMessage(sprintf('The migration "%s" must be alone in a file named after it.', $declaration));

        $this->invalidModulesRunner()->migrate(MigrationSet::module($module));
    }

    public function testAMigrationFailingToLoadNamesItsSetAndFile(): void
    {
        try {
            $this->invalidModulesRunner()->migrate(MigrationSet::module('broken-class'));
            self::fail('Expected the broken migration to refuse the set');
        } catch (MigrationSetRefusedException $exception) {
            self::assertSame(
                sprintf('Cannot load the migrations of the "broken-class" module migration set: Undefined array key "label" (%s:47).', __DIR__ . '/Fixtures/invalid-modules/broken-class/migrations/Version20260101000001.php'),
                $exception->getMessage(),
            );
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }
    }

    public function testRefusesAMigrationWithoutDescription(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The migration "NoDescription\Migrations\Version20260101000001" must describe what it changes in getDescription()');

        try {
            $this->invalidModulesRunner()->migrate(MigrationSet::module('no-description'));
        } finally {
            self::assertFalse($this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_no_description']), 'A refused set leaves no metadata table behind');
        }
    }

    public function testRefusesAMigrationEscapingTheBaseClassChecks(): void
    {
        try {
            $this->invalidModulesRunner()->migrate(MigrationSet::module('no-base-class'));
            self::fail('The migration would run without the base class checks');
        } catch (\RuntimeException $exception) {
            self::assertSame('The migration "NoBaseClass\Migrations\Version20260101000001" must extend DdlMigration or DmlMigration.', $exception->getMessage());
        }

        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_no_base_class']), 'Nothing ran, not even the metadata table creation');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideClassesDoctrineCannotInstantiate(): iterable
    {
        yield 'not a migration' => ['not-a-migration', 'The migration "NotAMigration\Migrations\Version20260101000001" must extend DdlMigration or DmlMigration.'];

        yield 'abstract' => ['abstract-class', 'The migration "AbstractClass\Migrations\Version20260101000001" must not be abstract: Doctrine instantiates every class of a migration file.'];
    }

    #[DataProvider('provideClassesDoctrineCannotInstantiate')]
    public function testRefusesAClassDoctrineCannotInstantiate(string $module, string $message): void
    {
        $runner = $this->invalidModulesRunner();
        $operations = [
            'migrate' => static fn (): array => $runner->migrate(MigrationSet::module($module))->executed,
            'status' => static fn (): MigrationSetStatus => $runner->status(MigrationSet::module($module)),
        ];
        foreach ($operations as $operation => $run) {
            try {
                $run();
                self::fail(sprintf('%s must refuse the set', $operation));
            } catch (MigrationSetRefusedException $exception) {
                self::assertSame($message, $exception->getMessage(), $operation);
            }
        }

        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_' . str_replace('-', '_', $module)]), 'Nothing ran, not even the metadata table creation');
    }

    public function testAMigrationChangingTheSchemaObjectFailsWithoutRunningAnything(): void
    {
        $this->connection->executeStatement('CREATE TABLE fx_schema_change (id INT PRIMARY KEY)');

        try {
            $this->runner->migrate(MigrationSet::module('schema-change'));
            self::fail('The schema changes would escape the DdlMigration and DmlMigration checks');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('The "SchemaChange\Migrations\Version20260101000001" migration of the "schema-change" module migration set failed: A migration must only read $schema', $exception->getMessage());
        }

        self::assertFalse($this->connection->createSchemaManager()->introspectTable('fx_schema_change')->hasColumn('label'));
        self::assertSame([], $this->runner->status(MigrationSet::module('schema-change'))->executed);
    }

    public function testColumnTypesUnknownToDbalDoNotBreakTheMigrations(): void
    {
        // Reading $schema introspects every table of the database, including ones whose column types DBAL cannot map.
        $this->connection->executeStatement('CREATE TABLE fx_unknown_types (flag BIT(1), location POINT, area GEOMETRY)');
        $this->connection->executeStatement('CREATE TABLE fx_schema_guard (id INT PRIMARY KEY)');

        self::assertCount(1, $this->runner->migrate(MigrationSet::module('schema-guard'))->executed);
        self::assertTrue($this->connection->createSchemaManager()->introspectTable('fx_schema_guard')->hasColumn('label'));
        self::assertSame(
            ['flag' => 'bit', 'location' => 'point', 'area' => 'geometry'],
            $this->connection->fetchAllKeyValue("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'fx_unknown_types' ORDER BY ordinal_position"),
            'The schema is only read: these columns are left as they are',
        );
    }

    public function testFailsOnAConnectionThatIsNotADbalOne(): void
    {
        $runner = new MigrationRunner(
            new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/modules', new NullLogger()),
            new MigrationFactoryProvider(new NonDbalConnectionRegistry(), new NullLogger(), '26.12.0'),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not a DBAL connection');

        $runner->migrate(MigrationSet::module(self::ROLLBACK));
    }

    /**
     * @return list<string>
     */
    private function revert(MigrationSet $set, string $versionOrAlias): array
    {
        return $this->runner->rollback($set, $versionOrAlias, $this->runner->planRollback($set, $versionOrAlias))->executed;
    }

    private function invalidModulesRunner(): MigrationRunner
    {
        return new MigrationRunner(
            new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/invalid-modules', new NullLogger()),
            new MigrationFactoryProvider(new SingleConnectionRegistry($this->connection), new NullLogger(), '26.12.0'),
        );
    }

    /**
     * @return list<int>
     */
    private function rows(string $table): array
    {
        return array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : -1,
            $this->connection->fetchFirstColumn(sprintf('SELECT id FROM %s ORDER BY id', $table)),
        );
    }

    private function env(string $name, string $default = ''): string
    {
        return is_string($_ENV[$name] ?? null) ? $_ENV[$name] : $default;
    }
}
