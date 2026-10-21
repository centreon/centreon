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

namespace SchemaGuard\Migrations;

use App\Upgrade\Infrastructure\Doctrine\DdlMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260101000001 extends DdlMigration
{
    public function up(Schema $schema): void
    {
        // MySQL has no ADD COLUMN IF NOT EXISTS: the guard reads the schema of the whole database.
        if ($schema->getTable('fx_schema_guard')->hasColumn('label')) {
            return;
        }

        $this->addSql('ALTER TABLE fx_schema_guard ADD COLUMN label VARCHAR(255)');
    }

    public function revert(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fx_schema_guard DROP COLUMN label');
    }
}
