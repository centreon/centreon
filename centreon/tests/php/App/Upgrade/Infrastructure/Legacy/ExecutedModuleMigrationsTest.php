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

namespace Tests\App\Upgrade\Infrastructure\Legacy;

use Adaptation\Database\Connection\Model\ConnectionConfig;
use CentreonModule\Domain\Repository\ModulesInformationsRepository;
use PHPUnit\Framework\TestCase;

/**
 * The Extension Manager reads the migrations a module executed to tell whether it is outdated.
 */
final class ExecutedModuleMigrationsTest extends TestCase
{
    private const string TABLE = 'doctrine_migration_versions_centreon_history_fixture';

    private \CentreonDB $db;

    protected function setUp(): void
    {
        $this->db = new \CentreonDB(connectionConfig: new ConnectionConfig(
            host: $this->env('hostCentreon'),
            user: $this->env('user'),
            password: $this->env('password'),
            databaseNameConfiguration: $this->env('db'),
            databaseNameRealTime: $this->env('dbcstg'),
            port: (int) $this->env('port'),
        ));
        $this->db->query('DROP TABLE IF EXISTS `' . self::TABLE . '`');
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `' . self::TABLE . '`');
    }

    public function testAModuleWithoutMetadataTableExecutedNoMigration(): void
    {
        self::assertSame([], (new ModulesInformationsRepository($this->db))->findExecutedMigrations('centreon-history-fixture'));
    }

    public function testListsTheExecutedMigrationsByClassName(): void
    {
        $this->db->query('CREATE TABLE `' . self::TABLE . '` (version VARCHAR(191) PRIMARY KEY)');
        $this->db->query('INSERT INTO `' . self::TABLE . "` VALUES ('CentreonHistoryFixture\\\\Migrations\\\\Version20260101000001')");

        self::assertSame(['Version20260101000001'], (new ModulesInformationsRepository($this->db))->findExecutedMigrations('centreon-history-fixture'));
    }

    private function env(string $name): string
    {
        $value = $_ENV[$name] ?? null;
        self::assertIsString($value);

        return $value;
    }
}
