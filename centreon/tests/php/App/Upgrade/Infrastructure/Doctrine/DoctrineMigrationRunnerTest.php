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

use App\Upgrade\Application\MigrationSetStatus;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Infrastructure\Doctrine\DoctrineMigrationRunner;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\App\Upgrade\Infrastructure\Double\SingleConnectionRegistry;

/**
 * Runs real migrations against a throwaway database: DAMA wraps every test in a transaction,
 * which would hide the transactional behaviour under test.
 */
final class DoctrineMigrationRunnerTest extends TestCase
{
    private const string DATABASE = 'centreon_migrations_test';
    private const string ATOMICITY = 'atomicity';
    private const string ROLLBACK = 'rollback';

    private Connection $connection;

    private DoctrineMigrationRunner $runner;

    protected function setUp(): void
    {
        $root = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->env('hostCentreon'),
            'port' => (int) $this->env('port'),
            'user' => $this->env('rootUser', 'root'),
            'password' => $this->env('rootPassword', $this->env('password')),
        ]);
        $root->executeStatement(\sprintf('DROP DATABASE IF EXISTS `%s`', self::DATABASE));
        $root->executeStatement(\sprintf('CREATE DATABASE `%s`', self::DATABASE));
        $root->executeStatement(\sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'", self::DATABASE, $this->env('user')));
        $root->close();

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->env('hostCentreon'),
            'port' => (int) $this->env('port'),
            'user' => $this->env('user'),
            'password' => $this->env('password'),
            'dbname' => self::DATABASE,
        ]);

        $this->runner = new DoctrineMigrationRunner(
            new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/modules'),
            new SingleConnectionRegistry($this->connection),
            new NullLogger(),
        );
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(\sprintf('DROP DATABASE IF EXISTS `%s`', self::DATABASE));
        $this->connection->close();
    }

    public function testMigrateCreatesTheMetadataTableAndAppliesEveryMigration(): void
    {
        self::assertSame([\Rollback\Migrations\Version20260101000001::class, \Rollback\Migrations\Version20260101000002::class], $this->runner->migrate(MigrationSet::module(self::ROLLBACK)));

        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_rollback']));
        self::assertSame([1], $this->rows('fx_rollback'));
        self::assertSame([], $this->runner->migrate(MigrationSet::module(self::ROLLBACK)), 'A second run has nothing to execute');
    }

    public function testAFailingDmlMigrationIsRolledBackAndStopsTheRunAfterADdlMigration(): void
    {
        try {
            $this->runner->migrate(MigrationSet::module(self::ATOMICITY));
            self::fail('Expected the duplicate key to fail the run');
        } catch (\Throwable) {
        }

        // The DDL migration committed implicitly: the DML ones must still run in real transactions.
        self::assertSame([1], $this->rows('fx_atomicity'), 'The failing migration must leave no row behind');
        self::assertSame(
            [\Atomicity\Migrations\Version20260101000001::class, \Atomicity\Migrations\Version20260101000002::class],
            $this->runner->status(MigrationSet::module(self::ATOMICITY))[0]->executed,
        );
        self::assertSame([\Atomicity\Migrations\Version20260101000003::class], $this->runner->status(MigrationSet::module(self::ATOMICITY))[0]->pending);
        self::assertFalse($this->connection->isTransactionActive(), 'No transaction may leak after a failure');
    }

    public function testRollbackPrevRevertsTheLastMigrationAndMigrateReappliesIt(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->runner->rollback(MigrationSet::module(self::ROLLBACK), 'prev'));
        self::assertSame([], $this->rows('fx_rollback'));
        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->runner->status(MigrationSet::module(self::ROLLBACK))[0]->pending);

        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->runner->migrate(MigrationSet::module(self::ROLLBACK)));
        self::assertSame([1], $this->rows('fx_rollback'));
    }

    public function testRollbackAcceptsAShortVersionAndFirst(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        self::assertSame([\Rollback\Migrations\Version20260101000002::class], $this->runner->rollback(MigrationSet::module(self::ROLLBACK), 'Version20260101000001'));
        self::assertSame([\Rollback\Migrations\Version20260101000001::class], $this->runner->rollback(MigrationSet::module(self::ROLLBACK), 'first'));
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['fx_rollback']));
    }

    public function testRollbackRefusesToMigrateUp(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
        $this->runner->rollback(MigrationSet::module(self::ROLLBACK), 'first');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('it is ahead of the current version');

        $this->runner->rollback(MigrationSet::module(self::ROLLBACK), 'Version20260101000002');
    }

    public function testSetsAreIsolated(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));

        $status = $this->runner->status(MigrationSet::module(self::ATOMICITY))[0];
        self::assertSame([], $status->executed);
        self::assertCount(3, $status->pending);
        self::assertFalse(
            $this->connection->createSchemaManager()->tablesExist(['doctrine_migration_versions_atomicity']),
            'Reading a status must not create the metadata table',
        );
    }

    public function testAnExecutedMigrationMissingFromTheCodeBaseFailsTheRun(): void
    {
        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
        $this->connection->insert('doctrine_migration_versions_rollback', ['version' => 'Rollback\Migrations\Version20250101000000']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rollback\Migrations\Version20250101000000');

        $this->runner->migrate(MigrationSet::module(self::ROLLBACK));
    }

    public function testRollingBackASetWithoutMigrationDirectoryFailsClearly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no migration to roll back');

        $this->runner->rollback(MigrationSet::module('without-migrations'), 'prev');
    }

    public function testASetWithoutMigrationDirectoryIsANoop(): void
    {
        self::assertSame([], $this->runner->migrate(MigrationSet::module('without-migrations')));
        self::assertEquals(
            [new MigrationSetStatus(MigrationSet::module('without-migrations'), [], [])],
            $this->runner->status(MigrationSet::module('without-migrations')),
        );
        self::assertSame([], $this->connection->createSchemaManager()->listTableNames());
    }

    public function testRefusesAMigrationDoctrineWouldIgnore(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('declares no migration in the "WrongNamespace\Migrations" namespace');

        $this->invalidModulesRunner()->migrate(MigrationSet::module('wrong-namespace'));
    }

    public function testRefusesAMigrationEscapingTheBaseClassChecks(): void
    {
        try {
            $this->invalidModulesRunner()->migrate(MigrationSet::module('no-base-class'));
            self::fail('The migration would run without the base class checks');
        } catch (\RuntimeException $exception) {
            self::assertSame('The migration "NoBaseClass\Migrations\Version20260101000001" must extend DdlMigration or DmlMigration.', $exception->getMessage());
        }

        self::assertSame([], $this->connection->fetchFirstColumn('SELECT version FROM doctrine_migration_versions_no_base_class'), 'Nothing ran');
    }

    public function testAMigrationChangingTheSchemaObjectFailsWithoutRunningAnything(): void
    {
        $this->connection->executeStatement('CREATE TABLE fx_schema_change (id INT PRIMARY KEY)');

        try {
            $this->runner->migrate(MigrationSet::module('schema-change'));
            self::fail('The schema changes would escape the DdlMigration and DmlMigration checks');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('must only read $schema', $exception->getMessage());
        }

        self::assertFalse($this->connection->createSchemaManager()->introspectTable('fx_schema_change')->hasColumn('label'));
        self::assertSame([], $this->runner->status(MigrationSet::module('schema-change'))[0]->executed);
    }

    public function testColumnTypesUnknownToDbalDoNotBreakTheMigrations(): void
    {
        // A migration reading the schema reads every table of the database, a module one included.
        $this->connection->executeStatement('CREATE TABLE fx_unknown_types (flag BIT(1), location POINT, area GEOMETRY)');
        $this->connection->executeStatement('CREATE TABLE fx_schema_guard (id INT PRIMARY KEY)');

        self::assertCount(1, $this->runner->migrate(MigrationSet::module('schema-guard')));
        self::assertTrue($this->connection->createSchemaManager()->introspectTable('fx_schema_guard')->hasColumn('label'));
        self::assertSame(
            ['flag' => 'bit', 'location' => 'point', 'area' => 'geometry'],
            $this->connection->fetchAllKeyValue("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'fx_unknown_types' ORDER BY ordinal_position"),
            'The schema is only read: these columns are left as they are',
        );
    }

    public function testFailsOnAConnectionThatIsNotADbalOne(): void
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn(new \stdClass());
        $runner = new DoctrineMigrationRunner(
            new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/modules'),
            $registry,
            new NullLogger(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not a DBAL connection');

        $runner->migrate(MigrationSet::module(self::ROLLBACK));
    }

    private function invalidModulesRunner(): DoctrineMigrationRunner
    {
        return new DoctrineMigrationRunner(
            new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/invalid-modules'),
            new SingleConnectionRegistry($this->connection),
            new NullLogger(),
        );
    }

    /**
     * @return list<int>
     */
    private function rows(string $table): array
    {
        return array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : -1,
            $this->connection->fetchFirstColumn(\sprintf('SELECT id FROM %s ORDER BY id', $table)),
        );
    }

    private function env(string $name, string $default = ''): string
    {
        return \is_string($_ENV[$name] ?? null) ? $_ENV[$name] : $default;
    }
}
