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

use App\Upgrade\Infrastructure\Doctrine\DdlMigration;
use App\Upgrade\Infrastructure\Doctrine\DmlMigration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class MigrationBaseClassesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public function testDdlMigrationsAreNotTransactional(): void
    {
        self::assertFalse($this->ddlMigration(['CREATE TABLE t (id INT)'])->isTransactional());
    }

    public function testDmlMigrationsAreTransactional(): void
    {
        self::assertTrue($this->dmlMigration(['INSERT INTO t VALUES (1)'])->isTransactional());
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

    public function testDdlMigrationsHoldASingleStatement(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must hold a single statement');

        $this->ddlMigration(['CREATE TABLE a (id INT)', 'CREATE TABLE b (id INT)'])->up(new Schema());
    }

    public function testDmlMigrationsRejectDdl(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot run DDL statements');

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
    }

    #[DataProvider('provideStatementsHiddenBehindAQuote')]
    public function testAQuoteInsideALiteralOrACommentDoesNotHideTheNextStatements(string $sql): void
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
        yield 'transaction start' => ['START TRANSACTION'];

        yield 'commit' => ['commit'];

        yield 'table flush' => ['FLUSH TABLES'];
    }

    #[DataProvider('provideStatementsEndingTheTransaction')]
    public function testDmlMigrationsRejectStatementsEndingTheirTransaction(string $sql): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot run DDL statements');

        $this->dmlMigration([$sql])->up(new Schema());
    }

    public function testSemicolonsInLiteralsAndCommentsAreNotStatementSeparators(): void
    {
        $dml = $this->dmlMigration(["INSERT INTO t (a, b) VALUES ('x;y', \"z; ALTER\") -- trailing; comment\n", 'UPDATE t SET a = 1;  ']);
        $dml->up(new Schema());

        $this->assertCount(2, $dml->getSql());
    }

    public function testDmlMigrationsRejectDdlBehindAComment(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot run DDL statements');

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
