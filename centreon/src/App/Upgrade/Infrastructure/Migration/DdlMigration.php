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
 * Base class of schema migrations, run outside any transaction; released migrations extend it: never move or rename it.
 */
abstract class DdlMigration extends AbstractMigration
{
    final public function isTransactional(): bool
    {
        return false;
    }

    final public function down(Schema $schema): void
    {
        $this->revert($schema);
    }

    abstract public function revert(Schema $schema): void;

    /**
     * @return Query[]
     */
    final public function getSql(): array
    {
        return parent::getSql();
    }

    final protected function addSql(string $sql, array $params = [], array $types = []): void
    {
        Assert::true($this->getSql() === [] && SqlStatement::isSingle($sql), sprintf('The DDL migration "%s" must hold a single statement per direction: one addSql() call, no ";" outside literals and comments (a trigger, procedure, function or event body is not supported).', static::class));

        $code = SqlStatement::code($sql);
        Assert::false(SqlStatement::changesSession($code), sprintf('The DDL migration "%s" cannot change a session setting or the database, nor create a temporary table: the next migrations run on its connection. Assign user variables (@name) only.', static::class));
        Assert::false(SqlStatement::controlsTransaction($code), sprintf('The DDL migration "%s" cannot control transactions or lock tables: the next migrations run on its connection.', static::class));

        parent::addSql($sql, $params, $types);
    }
}
