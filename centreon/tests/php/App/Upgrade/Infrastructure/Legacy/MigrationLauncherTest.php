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

use CentreonLegacy\Core\Migration\MigrationLauncher;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The legacy launcher spawns the console of the new kernel: kept with the integration tests, which have
 * the kernel configuration and the database it needs.
 */
final class MigrationLauncherTest extends TestCase
{
    private const string MODULE = 'centreon-launcher-fixture';

    private string $moduleDirectory;

    private Connection $connection;

    protected function setUp(): void
    {
        // A module shipping a migration, where the console looks for modules.
        $this->moduleDirectory = \dirname(__DIR__, 6) . '/www/modules/' . self::MODULE;
        (new Filesystem())->mirror(__DIR__ . '/../Doctrine/Fixtures/modules/' . self::MODULE, $this->moduleDirectory);

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->env('hostCentreon'),
            'port' => (int) $this->env('port'),
            'user' => $this->env('user'),
            'password' => $this->env('password'),
            'dbname' => $this->env('db'),
        ]);
        $this->connection->executeStatement(
            'INSERT INTO modules_informations (name, rname, mod_release, is_removeable, infos, author, svc_tools, host_tools)'
            . " VALUES ('" . self::MODULE . "', 'Launcher fixture', '1.0.0', '1', '', 'Centreon', '0', '0')",
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->moduleDirectory);
        $this->connection->executeStatement('DELETE FROM modules_informations WHERE name = ?', [self::MODULE]);
        $this->connection->executeStatement('DROP TABLE IF EXISTS fx_launcher');
        $this->connection->executeStatement('DROP TABLE IF EXISTS doctrine_migration_versions_centreon_launcher_fixture');
        $this->connection->close();
    }

    public function testRunsTheMigrationsOfASetThroughTheConsole(): void
    {
        (new MigrationLauncher())->migrate(self::MODULE);

        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['fx_launcher']));
        self::assertSame(
            [\CentreonLauncherFixture\Migrations\Version20260101000001::class],
            $this->connection->fetchFirstColumn('SELECT version FROM doctrine_migration_versions_centreon_launcher_fixture'),
        );
    }

    public function testReportsTheReasonOfAFailure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported module name "not.a.module"');

        (new MigrationLauncher())->migrate('not.a.module');
    }

    public function testRefusesAModuleThatIsNotInstalled(): void
    {
        $this->connection->executeStatement('DELETE FROM modules_informations WHERE name = ?', [self::MODULE]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The module "' . self::MODULE . '" is not installed');

        (new MigrationLauncher())->migrate(self::MODULE);
    }

    private function env(string $name): string
    {
        return \is_string($_ENV[$name] ?? null) ? $_ENV[$name] : '';
    }
}
