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

namespace App\Upgrade\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * DDL, transaction control and procedure calls (which may run either) are rejected: MySQL would implicitly commit the
 * transaction, breaking the atomicity of this migration and of the next ones.
 */
abstract class DmlMigration extends AbstractMigration
{
    private const string IMPLICIT_COMMIT_PATTERN = '/^(\s|--(?=\s)[^\n]*\n|#[^\n]*\n|\/\*(?![!+]).*?\*\/|\/\*[!+]\d*)*(?:(ALTER|CREATE|DROP|RENAME|TRUNCATE|LOCK|UNLOCK|ANALYZE|OPTIMIZE|REPAIR|FLUSH|CACHE|GRANT|REVOKE|INSTALL|UNINSTALL|BEGIN|START|COMMIT|ROLLBACK|SAVEPOINT|RELEASE|XA|RESET|PURGE|LOAD\s+INDEX|CHECK|STOP|CHANGE|CALL|SET\s+PASSWORD)\b|SET\b.*?(?<!\w)(?<!(?<!@)@)AUTOCOMMIT\s*:?=)/is';

    final public function isTransactional(): bool
    {
        return true;
    }

    final public function down(Schema $schema): void
    {
        $this->revert($schema);
    }

    abstract public function revert(Schema $schema): void;

    protected function addSql(string $sql, array $params = [], array $types = []): void
    {
        if (! SqlStatement::isSingle($sql)) {
            throw new \LogicException(\sprintf('The migration "%s" must pass one statement per addSql() call.', static::class));
        }

        if (preg_match(self::IMPLICIT_COMMIT_PATTERN, $sql) === 1) {
            throw new \LogicException(\sprintf('The DML migration "%s" cannot run a statement committing its transaction (DDL, transaction control, CALL...): use a DdlMigration instead.', static::class));
        }

        parent::addSql($sql, $params, $types);
    }
}
