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

use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

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
            $this->modulesDir . '/4centreon/migrations',
        ]);

        $this->registry = new MigrationSetRegistry('/usr/share/centreon', $this->modulesDir, new NullLogger());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->modulesDir);
    }

    public function testWebSets(): void
    {
        $realtime = $this->registry->configuration(MigrationSet::web(WebMigrationSetEnum::Realtime));
        self::assertSame('realtime', $realtime->connection);
        self::assertSame('Centreon\Migrations\Realtime', $realtime->namespace);
        self::assertSame('/usr/share/centreon/migrations/realtime', $realtime->directory);
        self::assertSame('doctrine_migration_versions', $realtime->table);

        $configuration = $this->registry->configuration(MigrationSet::web(WebMigrationSetEnum::Configuration));
        self::assertSame('default', $configuration->connection);
        self::assertSame('Centreon\Migrations\Configuration', $configuration->namespace);
        self::assertSame('/usr/share/centreon/migrations/configuration', $configuration->directory);
        self::assertSame('doctrine_migration_versions', $configuration->table);
    }

    public function testTheWebMigrationDirectoriesAreShipped(): void
    {
        $registry = new MigrationSetRegistry(dirname(__DIR__, 6), '/nonexistent', new NullLogger());

        foreach (MigrationSet::webSets() as $set) {
            self::assertDirectoryExists($registry->configuration($set)->directory);
        }
    }

    public function testModuleSetsHaveTheirOwnNamespaceAndTable(): void
    {
        $configuration = $this->registry->configuration(MigrationSet::module('centreon-open-tickets'));

        self::assertSame('default', $configuration->connection);
        self::assertSame('CentreonOpenTickets\Migrations', $configuration->namespace);
        self::assertSame($this->modulesDir . '/centreon-open-tickets/migrations', $configuration->directory);
        self::assertSame('doctrine_migration_versions_centreon_open_tickets', $configuration->table);
    }

    public function testAllListsTheWebSetsFirstThenTheModulesShippingMigrations(): void
    {
        self::assertSame(
            ['realtime', 'configuration', 'module:centreon-open-tickets'],
            array_map(static fn (MigrationSet $set): string => $set->name, $this->registry->all()),
        );
    }

    public function testTheMetadataTableOfTheLongestModuleNameFitsAnIdentifier(): void
    {
        self::assertSame(63, mb_strlen($this->registry->configuration(MigrationSet::module(str_repeat('a', 35)))->table), 'PostgreSQL identifiers are limited to 63 characters');
    }

    public function testIgnoresTheModulesSharingANamespace(): void
    {
        (new Filesystem())->mkdir([
            $this->modulesDir . '/centreon-foo/migrations',
            $this->modulesDir . '/centreonfoo/migrations',
            $this->modulesDir . '/centreon-a1/migrations',
            $this->modulesDir . '/centreon-a-1/migrations',
        ]);

        self::assertSame(
            ['realtime', 'configuration', 'module:centreon-open-tickets'],
            array_map(static fn (MigrationSet $set): string => $set->name, $this->registry->all()),
        );
    }

    public function testLogsTheModuleDirectoriesItIgnores(): void
    {
        $logger = new RecordingLogger();

        (new MigrationSetRegistry('/usr/share/centreon', $this->modulesDir, $logger))->all();

        $warnings = array_column(array_filter($logger->records(), static fn (array $record): bool => $record['level'] === LogLevel::WARNING), 'message');
        self::assertCount(2, $warnings);
        self::assertStringContainsString('4centreon/migrations', implode("\n", $warnings));
    }

    public function testReportsTheModuleDirectoriesItCannotRead(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('root reads any directory');
        }

        $filesystem = new Filesystem();
        $filesystem->chmod($this->modulesDir . '/centreon-open-tickets', 0o600);

        try {
            self::assertContains(
                sprintf('Cannot read the module directory "%s/centreon-open-tickets": check its permissions.', $this->modulesDir),
                $this->registry->ignored(),
            );

            $filesystem->chmod($this->modulesDir, 0o300);
            self::assertSame([sprintf('Cannot list the modules of "%s": check its permissions.', $this->modulesDir)], $this->registry->ignored());
        } finally {
            $filesystem->chmod([$this->modulesDir, $this->modulesDir . '/centreon-open-tickets'], 0o755);
        }
    }

    public function testReportsMigrationsThatAreNotADirectory(): void
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->modulesDir . '/centreon-dsm/migrations', '');
        $filesystem->symlink($this->modulesDir . '/missing', $this->modulesDir . '/centreon-awie/migrations');

        $ignored = $this->registry->ignored();

        self::assertContains(sprintf('Ignored the migrations of "%s/centreon-dsm/migrations": it is not a directory.', $this->modulesDir), $ignored);
        self::assertContains(sprintf('Ignored the migrations of "%s/centreon-awie/migrations": it is not a directory.', $this->modulesDir), $ignored);
        self::assertSame(['centreon-open-tickets'], array_values(array_filter(array_map(static fn (MigrationSet $set): ?string => $set->module, $this->registry->all()))));
    }

    public function testAMissingModulesDirectoryHasNoModule(): void
    {
        $registry = new MigrationSetRegistry('/usr/share/centreon', $this->modulesDir . '/missing', new NullLogger());

        self::assertSame([], $registry->ignored());
        self::assertEquals(MigrationSet::webSets(), $registry->all());
    }
}
