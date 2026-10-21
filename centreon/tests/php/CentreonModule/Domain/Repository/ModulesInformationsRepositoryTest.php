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

namespace CentreonModule\Domain\Repository;

use Centreon\Test\Mock\CentreonDB;
use PHPUnit\Framework\TestCase;

class ModulesInformationsRepositoryTest extends TestCase
{
    private const string TABLE_EXISTS = 'SELECT 1 FROM `information_schema`.`tables` WHERE `table_schema` = DATABASE() AND `table_name` = :table';

    public function testFindsTheExecutedMigrationsOfAModule(): void
    {
        $db = new CentreonDB();
        $db->addResultSet(self::TABLE_EXISTS, [[1]], [':table' => 'doctrine_migration_versions_centreon_open_tickets']);
        $db->addResultSet('SELECT `version` FROM `doctrine_migration_versions_centreon_open_tickets`', [
            ['version' => 'CentreonOpenTickets\\Migrations\\Version20260101000001'],
            ['version' => 'CentreonOpenTickets\\Migrations\\Version20260101000002'],
        ]);

        $this->assertSame(
            ['Version20260101000001', 'Version20260101000002'],
            (new ModulesInformationsRepository($db))->findExecutedMigrations('centreon-open-tickets'),
        );
    }

    public function testNoMigrationRanBeforeTheMetadataTableExists(): void
    {
        // Created by the first migration run. Not queried: the failure would be logged as an SQL error.
        $db = new CentreonDB();
        $db->addResultSet(self::TABLE_EXISTS, [], [':table' => 'doctrine_migration_versions_centreon_open_tickets']);

        $this->assertSame([], (new ModulesInformationsRepository($db))->findExecutedMigrations('centreon-open-tickets'));
    }

    public function testIgnoresUnsupportedModuleNames(): void
    {
        $this->assertNull((new ModulesInformationsRepository(new CentreonDB()))->findExecutedMigrations('../../etc'));
    }
}
