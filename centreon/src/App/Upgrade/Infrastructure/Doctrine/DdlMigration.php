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
 * MySQL commits implicitly around DDL: no transaction, so a single idempotent statement that a replay cannot harm.
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

    protected function addSql(string $sql, array $params = [], array $types = []): void
    {
        if ($this->getSql() !== [] || ! SqlStatement::isSingle($sql)) {
            throw new \LogicException(\sprintf('The DDL migration "%s" must hold a single statement per direction.', static::class));
        }

        parent::addSql($sql, $params, $types);
    }
}
