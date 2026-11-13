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

namespace App\Upgrade\Infrastructure\Migration;

use App\Upgrade\Infrastructure\Doctrine\SqlStatement;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Query\Query;
use Webmozart\Assert\Assert;

/**
 * Base class of data migrations, run in one transaction; released migrations extend it: never move or rename it.
 */
abstract class DmlMigration extends AbstractMigration
{
    private const string IMPLICIT_COMMIT_PATTERN = '/^\s*(?:(ALTER|CREATE(?!\s+(?:OR\s+REPLACE\s+)?TEMPORARY\b)|DROP(?!\s+TEMPORARY\b)|RENAME|TRUNCATE|LOCK|UNLOCK|ANALYZE|OPTIMIZE|REPAIR|FLUSH|CACHE|GRANT|REVOKE|INSTALL|UNINSTALL|BEGIN|START|COMMIT|ROLLBACK|SAVEPOINT|RELEASE|XA|RESET|PURGE|LOAD\s+INDEX|IMPORT\s+TABLE|BACKUP\s+STAGE|CHECK|STOP|CHANGE|CALL|PREPARE|EXECUTE|SET\s+PASSWORD|SET\s+STATEMENT)\b|SET\b.*?(?<!\w)(?<!(?<!@)@)AUTOCOMMIT\s*:?=)/is';

    final public function isTransactional(): bool
    {
        return true;
    }

    final public function down(Schema $schema): void
    {
        $this->revert($schema);
    }

    abstract public function revert(Schema $schema): void;

    /**
     * Called once up() or down() returns, before Doctrine runs the statements.
     *
     * @return Query[]
     */
    final public function getSql(): array
    {
        $this->assertTransactionIsOpen();

        return parent::getSql();
    }

    final public function postUp(Schema $schema): void
    {
        $this->assertTransactionIsOpen();
    }

    final public function postDown(Schema $schema): void
    {
        $this->assertTransactionIsOpen();
    }

    final protected function addSql(string $sql, array $params = [], array $types = []): void
    {
        Assert::true(SqlStatement::isSingle($sql), sprintf('The migration "%s" must pass one statement per addSql() call.', static::class));

        $code = SqlStatement::code($sql);
        Assert::false(SqlStatement::matches(self::IMPLICIT_COMMIT_PATTERN, $code), sprintf('The DML migration "%s" cannot run a statement committing its transaction (DDL, transaction control, CALL...): move DDL to a DdlMigration.', static::class));
        Assert::false(SqlStatement::changesSession($code), sprintf('The DML migration "%s" cannot change a session setting or the database, nor create a temporary table: the next migrations run on its connection. Assign user variables (@name) only.', static::class));

        parent::addSql($sql, $params, $types);
    }

    /**
     * Detects a commit made through $this->connection, which bypasses the addSql() checks.
     */
    private function assertTransactionIsOpen(): void
    {
        $native = $this->connection->getNativeConnection();
        Assert::isInstanceOf($native, \PDO::class, sprintf('The DML migration "%s" needs a PDO connection to check that its transaction stays open.', static::class));
        Assert::true($native->inTransaction(), sprintf('The DML migration "%s" committed its transaction: run its statements through addSql() only.', static::class));
    }
}
