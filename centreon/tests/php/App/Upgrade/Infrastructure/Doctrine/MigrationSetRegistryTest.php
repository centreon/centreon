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

use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class MigrationSetRegistryTest extends TestCase
{
    private string $modulesDir;

    private MigrationSetRegistry $registry;

    protected function setUp(): void
    {
        $this->modulesDir = sys_get_temp_dir() . '/' . uniqid('modules_', true);
        (new Filesystem())->mkdir([
            $this->modulesDir . '/centreon-open-tickets/migrations',
            $this->modulesDir . '/centreon-dsm',
            // A stray copy, ignored instead of breaking the listing.
            $this->modulesDir . '/centreon-open-tickets.bak/migrations',
        ]);

        $this->registry = new MigrationSetRegistry('/usr/share/centreon', $this->modulesDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->modulesDir);
    }

    public function testCoreSets(): void
    {
        $realtime = $this->registry->get(MigrationSet::REALTIME);
        self::assertSame('realtime', $realtime->connection);
        self::assertSame('Centreon\Migrations\Realtime', $realtime->namespace);
        self::assertSame('/usr/share/centreon/migrations/realtime', $realtime->directory);
        self::assertSame('doctrine_migration_versions', $realtime->table);

        $configuration = $this->registry->get(MigrationSet::CONFIGURATION);
        self::assertSame('default', $configuration->connection);
        self::assertSame('Centreon\Migrations\Configuration', $configuration->namespace);
        self::assertSame('/usr/share/centreon/migrations/configuration', $configuration->directory);
        self::assertSame('doctrine_migration_versions', $configuration->table);
    }

    public function testModuleSetsHaveTheirOwnNamespaceAndTable(): void
    {
        $set = $this->registry->get(MigrationSet::forModule('centreon-open-tickets'));

        self::assertSame('default', $set->connection);
        self::assertSame('CentreonOpenTickets\Migrations', $set->namespace);
        self::assertSame($this->modulesDir . '/centreon-open-tickets/migrations', $set->directory);
        self::assertSame('doctrine_migration_versions_centreon_open_tickets', $set->table);
    }

    public function testAllListsTheCoreSetsFirstThenTheModulesShippingMigrations(): void
    {
        self::assertSame(
            ['realtime', 'configuration', 'module:centreon-open-tickets'],
            array_map(static fn (MigrationSet $set): string => $set->name, $this->registry->all()),
        );
    }

    public function testRejectsUnknownSets(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown migration set "storage".');

        $this->registry->get('storage');
    }

    public function testRejectsModuleNamesEscapingTheModulesDirectory(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->registry->get(MigrationSet::forModule('../../etc'));
    }
}
