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

use App\Upgrade\Infrastructure\Migration\DdlMigration;
use App\Upgrade\Infrastructure\Migration\DmlMigration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\App\Upgrade\Infrastructure\Double\NonPdoConnection;

final class MigrationBaseClassesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        // As in Doctrine, which opens the transaction of a DML migration before calling it.
        $this->connection->beginTransaction();
    }

    public function testDownDelegatesToRevert(): void
    {
        $ddl = $this->ddlMigration(['DROP TABLE t']);
        $ddl->down(new Schema());
        self::assertSame(['DROP TABLE t'], array_map(static fn ($query): string => $query->getStatement(), $ddl->getSql()));

        $dml = $this->dmlMigration(['DELETE FROM t']);
        $dml->down(new Schema());
        self::assertSame(['DELETE FROM t'], array_map(static fn ($query): string => $query->getStatement(), $dml->getSql()));
    }

    public function testMigrationsCannotHandStatementsOverBypassingAddSql(): void
    {
        self::assertTrue((new \ReflectionMethod(DdlMigration::class, 'getSql'))->isFinal());
        self::assertTrue((new \ReflectionMethod(DmlMigration::class, 'getSql'))->isFinal());
    }

    public function testDdlMigrationsHoldASingleStatement(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must hold a single statement');

        $this->ddlMigration(['CREATE TABLE a (id INT)', 'CREATE TABLE b (id INT)'])->up(new Schema());
    }

    public function testDmlMigrationsRejectDdl(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot run a statement committing its transaction');

        $this->dmlMigration(['INSERT INTO t VALUES (1)', '  alter table t add column c INT'])->up(new Schema());
    }

    public function testDdlMigrationsRejectSeveralStatementsInOneCall(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must hold a single statement');

        $this->ddlMigration(['CREATE TABLE a (id INT); DROP TABLE b'])->up(new Schema());
    }

    public function testDmlMigrationsRejectDdlHiddenAfterAnotherStatement(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('one statement per addSql() call');

        $this->dmlMigration(['INSERT INTO t VALUES (1); ALTER TABLE t ADD COLUMN c INT'])->up(new Schema());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideStatementsHiddenBehindAQuote(): iterable
    {
        yield 'apostrophe in a double-quoted literal' => ["UPDATE t SET a = \"it's\"; ALTER TABLE t ADD COLUMN c INT; UPDATE t SET b = 'x'"];

        yield 'apostrophe in a comment' => ["-- don't\nUPDATE t SET a = '1'; DELETE FROM u"];

        yield 'apostrophe in a backticked identifier' => ["UPDATE `it's` SET a = 1; ALTER TABLE t ADD COLUMN c INT; UPDATE t SET b = 'x'"];

        yield 'double dash without a space, which MySQL does not take for a comment' => ['UPDATE t SET a = 1--1; DROP TABLE u'];

        yield 'executable comment, which MySQL runs' => ['/*!50000 UPDATE t SET a = 1; DROP TABLE u */'];

        yield 'executable comment, which MariaDB runs' => ['/*M!100000 UPDATE t SET a = 1; DROP TABLE u */'];

        yield 'escaped backslash closing the literal' => ["UPDATE t SET a = 'x\\\\'; DROP TABLE u; -- '"];
    }

    #[DataProvider('provideStatementsHiddenBehindAQuote')]
    public function testAQuoteOrACommentDoesNotHideTheNextStatements(string $sql): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('one statement per addSql() call');

        $this->dmlMigration([$sql])->up(new Schema());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideStatementsEndingTheTransaction(): iterable
    {
        foreach ([
            'ALTER TABLE t ADD COLUMN c INT', 'CREATE INDEX i ON t (id)', 'DROP TABLE t', 'RENAME TABLE t TO u',
            'TRUNCATE TABLE t', 'LOCK TABLES t WRITE', 'UNLOCK TABLES', 'ANALYZE TABLE t', 'OPTIMIZE TABLE t',
            'REPAIR TABLE t', 'FLUSH TABLES', 'CACHE INDEX t IN hot', 'GRANT SELECT ON t TO u', 'REVOKE SELECT ON t FROM u',
            "INSTALL PLUGIN p SONAME 'p.so'", 'UNINSTALL PLUGIN p', 'BEGIN', 'START TRANSACTION', 'COMMIT', 'ROLLBACK',
            'SAVEPOINT s', 'RELEASE SAVEPOINT s', 'SET autocommit = 1', 'SET SESSION autocommit = 1', 'SET @@autocommit = 1',
            'XA START x', 'LOAD INDEX INTO CACHE t', 'RESET MASTER', 'PURGE BINARY LOGS TO x', 'CALL p()', 'CHECK TABLE t', "SET PASSWORD = 'p'",
            'IMPORT TABLE FROM t.sdi', 'BACKUP STAGE START', 'STOP REPLICA', "CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'", 'SET @x = 1, autocommit = 1', 'SET /* c */ autocommit = 1',
            'SET STATEMENT max_statement_time = 60 FOR ALTER TABLE t ADD COLUMN c INT', 'PREPARE s FROM @ddl', 'EXECUTE s',
        ] as $statement) {
            yield $statement => [mb_strtolower($statement)];
        }

        yield 'behind whitespace and comments' => ["  -- why\n# how\n/* note */ DROP TABLE t"];

        yield 'in an executable comment' => ['/*!50000 ALTER TABLE t ADD COLUMN c INT */'];

        yield 'in a MariaDB executable comment' => ['/*M!100000 ALTER TABLE t ADD COLUMN c INT */'];

        yield 'behind an empty executable comment' => ['/*!50000 */ CREATE TABLE t (a INT)'];

        yield 'behind an empty executable comment without version' => ['/*!*/DROP TABLE t'];

        yield 'behind an optimizer hint, a plain comment there' => ['/*+ x */ DROP TABLE t'];
    }

    public function testDmlMigrationsAcceptSessionVariables(): void
    {
        $dml = $this->dmlMigration([
            'SET @id = 1', 'SET @autocommit = 1', 'SET @a := 1, @b = 2', 'SET @v = @@version', "SET @x = 'a, b = c'",
            "SET @x = 'autocommit = 1'", 'SET @a = 1, @b = (SELECT c FROM t WHERE d IN (1, (2), 3))', "SET @x = 1 -- note, a = 1\n",
            'INSERT INTO t (id) VALUES (@id)', 'UPDATE t SET autocommit = 1, foreign_key_checks = 0',
            'DROP TEMPORARY TABLE IF EXISTS tmp',
        ]);
        $dml->up(new Schema());

        $this->assertCount(11, $dml->getSql());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSessionSettings(): iterable
    {
        foreach ([
            'SET foreign_key_checks = 0', "SET SESSION sql_mode = ''", 'SET @@session.unique_checks = 0', 'SET LOCAL sql_safe_updates = 0',
            'SET @x = 1, foreign_key_checks = 0', "SET @x = 1, @@sql_mode = ''", "/* why */ SET time_zone = '+00:00'",
            "SET @x = 1, SESSION sql_mode = ''", 'SET @x = 1, GLOBAL max_connections = 10', 'SET NAMES latin1',
            'SET NAMES utf8mb4, foreign_key_checks = 0', "SET CHARACTER SET utf8, time_zone = '+00:00'",
            'SET/**/foreign_key_checks = 0', '/*!40101 SET foreign_key_checks = 0 */', "/*M!100000 SET sql_mode = '' */",
            'USE centreon_storage', '/*!50000 USE centreon_storage */', 'CREATE TEMPORARY TABLE tmp (id INT)',
            'CREATE OR REPLACE TEMPORARY TABLE tmp (id INT)',
        ] as $statement) {
            yield $statement => [mb_strtolower($statement)];
        }
    }

    #[DataProvider('provideSessionSettings')]
    public function testDmlMigrationsRejectSessionSettings(string $sql): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot change a session setting');

        $this->dmlMigration([$sql])->up(new Schema());
    }

    #[DataProvider('provideSessionSettings')]
    public function testDdlMigrationsRejectSessionSettings(string $sql): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot change a session setting');

        $this->ddlMigration([$sql])->up(new Schema());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTransactionControl(): iterable
    {
        foreach (['BEGIN', 'START TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT s', 'RELEASE SAVEPOINT s', 'XA START x', 'LOCK TABLES t WRITE', 'UNLOCK TABLES'] as $statement) {
            yield $statement => [$statement];

            yield mb_strtolower($statement) => [mb_strtolower($statement)];
        }

        yield 'in an executable comment' => ['/*!50000 COMMIT */'];

        yield 'in a MariaDB executable comment' => ['/*M!100000 UNLOCK TABLES */'];

        yield 'behind a comment' => ["-- why\nLOCK TABLES t WRITE"];
    }

    #[DataProvider('provideTransactionControl')]
    public function testDdlMigrationsRejectTransactionControl(string $sql): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot control transactions or lock tables');

        $this->ddlMigration([$sql])->up(new Schema());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSchemaChanges(): iterable
    {
        foreach ([
            'SET @n = 1',
            'SET STATEMENT max_statement_time = 60 FOR ALTER TABLE t ADD COLUMN c INT',
            "CREATE TABLE t (a VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'x;y') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'ALTER TABLE t ADD COLUMN c INT, ALGORITHM=INPLACE, LOCK=NONE',
            "ALTER TABLE t ADD COLUMN s SET('a', 'b') NOT NULL DEFAULT 'a'",
            'ALTER TABLE t ALTER COLUMN c SET DEFAULT 0',
            '/*!40101 ALTER TABLE t ENGINE=InnoDB */',
            'CREATE TABLE `begin` (`commit` INT)',
            "ALTER TABLE t MODIFY c ENUM('a;b', 'c') DEFAULT 'a;b'",
            'DROP TABLE IF EXISTS t',
            'RENAME TABLE a TO b',
            'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.c = 1',
        ] as $statement) {
            yield $statement => [$statement];
        }
    }

    #[DataProvider('provideSchemaChanges')]
    public function testDdlMigrationsAcceptSchemaChanges(string $sql): void
    {
        $ddl = $this->ddlMigration([$sql]);
        $ddl->up(new Schema());

        self::assertCount(1, $ddl->getSql());
    }

    public function testAPcreFailureRefusesTheStatement(): void
    {
        $jit = ini_get('pcre.jit');
        $backtrackLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '100');

        try {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('Cannot analyse the SQL statement');

            $this->dmlMigration(['SET @x = ' . str_repeat('1+', 5000) . '1'])->up(new Schema());
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
        }
    }

    public function testDmlMigrationsNeedAPdoConnectionToCheckTheirTransaction(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => NonPdoConnection::class]);
        $dml = $this->dmlMigration(['DELETE FROM t']);
        $dml->up(new Schema());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('needs a PDO connection');

        $dml->getSql();
    }

    /**
     * @return iterable<string, array{'postUp'|'postDown'}>
     */
    public static function provideChecksAfterTheStatements(): iterable
    {
        yield 'after up()' => ['postUp'];

        yield 'after down()' => ['postDown'];
    }

    #[DataProvider('provideChecksAfterTheStatements')]
    public function testDmlMigrationsDetectAStatementThatCommittedTheirTransaction(string $check): void
    {
        $dml = $this->dmlMigration(['DELETE FROM t']);
        $this->connection->commit();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('committed its transaction');

        $dml->{$check}(new Schema());
    }

    public function testALongLiteralDoesNotHideTheNextStatement(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('one statement per addSql() call');

        $this->dmlMigration(["INSERT INTO t VALUES ('" . str_repeat('a', 1_000_000) . "'); DROP TABLE u"])->up(new Schema());
    }

    #[DataProvider('provideStatementsEndingTheTransaction')]
    public function testDmlMigrationsRejectStatementsEndingTheirTransaction(string $sql): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot run a statement committing its transaction');

        $this->dmlMigration([$sql])->up(new Schema());
    }

    public function testSemicolonsInLiteralsAndCommentsAreNotStatementSeparators(): void
    {
        $dml = $this->dmlMigration(["INSERT INTO t (a, b) VALUES ('x;y', \"z; ALTER\") -- trailing; comment\n", 'UPDATE t SET a = 1;  ', "UPDATE t SET a = 'x\\'; y'"]);
        $dml->up(new Schema());

        $this->assertCount(3, $dml->getSql());
    }

    public function testDmlMigrationsRejectDdlBehindAComment(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot run a statement committing its transaction');

        $this->dmlMigration(["/* index */\n-- needed for the report\nCREATE INDEX i ON t (id)"])->up(new Schema());
    }

    /**
     * @param list<string> $statements
     */
    private function ddlMigration(array $statements): DdlMigration
    {
        return new class ($this->connection, new NullLogger(), $statements) extends DdlMigration {
            /**
             * @param list<string> $statements
             */
            public function __construct(Connection $connection, NullLogger $logger, private readonly array $statements)
            {
                parent::__construct($connection, $logger);
            }

            public function up(Schema $schema): void
            {
                array_map($this->addSql(...), $this->statements);
            }

            public function revert(Schema $schema): void
            {
                array_map($this->addSql(...), $this->statements);
            }
        };
    }

    /**
     * @param list<string> $statements
     */
    private function dmlMigration(array $statements): DmlMigration
    {
        return new class ($this->connection, new NullLogger(), $statements) extends DmlMigration {
            /**
             * @param list<string> $statements
             */
            public function __construct(Connection $connection, NullLogger $logger, private readonly array $statements)
            {
                parent::__construct($connection, $logger);
            }

            public function up(Schema $schema): void
            {
                array_map($this->addSql(...), $this->statements);
            }

            public function revert(Schema $schema): void
            {
                array_map($this->addSql(...), $this->statements);
            }
        };
    }
}
