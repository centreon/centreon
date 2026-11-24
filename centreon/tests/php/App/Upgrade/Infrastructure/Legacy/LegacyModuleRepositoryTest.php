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

use App\Upgrade\Infrastructure\Legacy\LegacyModuleRepository;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\ServiceProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Symfony\Component\Filesystem\Filesystem;

final class LegacyModuleRepositoryTest extends TestCase
{
    private string $modulesDir;

    protected function setUp(): void
    {
        $this->modulesDir = sys_get_temp_dir() . '/legacy-module-repository-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->modulesDir);
    }

    public function testUpdatesTheOutdatedModulesWithoutClearingTheCaches(): void
    {
        $cacheClearer = new class () extends CacheClearer {
            /** @var list<string> */
            public array $commands = [];

            protected function run(array $command): void
            {
                $this->commands[] = implode(' ', $command);
            }
        };
        $moduleService = $this->createMock(CentreonModuleService::class);
        // Each module upgrader asks for a clear: the platform update does it once, at its end.
        $moduleService->expects(self::once())->method('updateAll')->with('module')->willReturnCallback(
            static function () use ($cacheClearer): array {
                $cacheClearer->clear();

                return [];
            },
        );

        $container = new Container();
        $container[ServiceProvider::CENTREON_MODULE] = $moduleService;
        $container[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $cacheClearer;

        (new LegacyModuleRepository($container, DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $this->modulesDir))->updateAll();

        self::assertSame([], $cacheClearer->commands);
    }

    public function testAModuleIsInstalledWhenItHasARowInModulesInformations(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE modules_informations (id INTEGER PRIMARY KEY, name VARCHAR(255))');
        $connection->insert('modules_informations', ['name' => 'centreon-open-tickets']);

        $repository = new LegacyModuleRepository(new Container(), $connection, $this->modulesDir);

        self::assertTrue($repository->isInstalled('centreon-open-tickets'));
        self::assertFalse($repository->isInstalled('centreon-bam-server'));
    }

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function provideUpgradeDirectories(): iterable
    {
        yield 'a version between the installed and the code ones' => ['24.10.0', ['24.10.1', '24.10.2'], true];

        yield 'the code version itself' => ['24.10.0', ['24.04.0', '24.10.1'], true];

        yield 'once the Upgrader loop ran, before it records the code version' => ['24.10.1', ['24.04.0', '24.10.0', '24.10.1'], false];

        yield 'a version beyond the code one only' => ['24.10.0', ['24.10.0', '24.10.3'], false];

        yield 'no upgrade directory' => ['24.10.0', [], false];

        yield 'a directory that is not a version' => ['24.10.0', ['notes', 'v24.10.1'], false];
    }

    /**
     * @param list<string> $upgradeDirectories
     */
    #[DataProvider('provideUpgradeDirectories')]
    public function testAnUpgradeIsPendingWhenAScriptDirectoryUpToTheCodeVersionIsNewerThanTheInstalledOne(
        string $installedRelease,
        array $upgradeDirectories,
        bool $expected,
    ): void {
        $this->writeModule('centreon-open-tickets', '24.10.1', $upgradeDirectories);

        self::assertSame($expected, $this->repository($this->connectionWith(['centreon-open-tickets' => $installedRelease]))->hasPendingLegacyUpgrade('centreon-open-tickets'));
    }

    public function testNoUpgradeIsPendingForAModuleNotInstalled(): void
    {
        $this->writeModule('centreon-open-tickets', '24.10.1', ['24.10.1']);

        self::assertFalse($this->repository($this->connectionWith([]))->hasPendingLegacyUpgrade('centreon-open-tickets'));
    }

    public function testFailsWhenTheCodeVersionOfAModuleWithUpgradeScriptsCannotBeRead(): void
    {
        $this->writeModule('centreon-open-tickets', null, ['24.10.1']);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot read the version of the "centreon-open-tickets" module');

        $this->repository($this->connectionWith(['centreon-open-tickets' => '24.10.0']))->hasPendingLegacyUpgrade('centreon-open-tickets');
    }

    public function testFailsWhenTheConfigurationOfAModuleWithAPendingUpgradeIsMissing(): void
    {
        $this->writeModule('centreon-open-tickets', '24.10.1', ['24.10.1']);
        unlink($this->modulesDir . '/centreon-open-tickets/conf.php');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('conf.php" is missing');

        $this->repository($this->connectionWith(['centreon-open-tickets' => '24.10.0']))->hasPendingLegacyUpgrade('centreon-open-tickets');
    }

    public function testFailsWhenTheUpgradeDirectoryOfAModuleCannotBeRead(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('root reads any directory');
        }
        $this->writeModule('centreon-open-tickets', '24.10.1', ['24.10.1']);
        $upgradeDirectory = $this->modulesDir . '/centreon-open-tickets/upgrade';
        chmod($upgradeDirectory, 0000);

        try {
            $this->expectException(\UnexpectedValueException::class);
            $this->expectExceptionMessage('Cannot list the upgrade scripts of the "centreon-open-tickets" module');

            $this->repository($this->connectionWith(['centreon-open-tickets' => '24.10.0']))->hasPendingLegacyUpgrade('centreon-open-tickets');
        } finally {
            chmod($upgradeDirectory, 0755);
        }
    }

    public function testListsTheInstalledModules(): void
    {
        $repository = $this->repository($this->connectionWith(['centreon-open-tickets' => '24.10.0', 'centreon-bam-server' => '24.10.0']));

        self::assertSame(['centreon-bam-server', 'centreon-open-tickets'], $repository->findInstalledModules());
    }

    public function testCountsTheExecutedMigrationsOfAModuleAndNoneBeforeItsFirstRun(): void
    {
        $connection = $this->connectionWith([]);
        $connection->executeStatement('CREATE TABLE doctrine_migration_versions_centreon_open_tickets (version VARCHAR(191) PRIMARY KEY)');
        $connection->insert('doctrine_migration_versions_centreon_open_tickets', ['version' => 'CentreonOpenTickets\Migrations\Version20260101000001']);
        $connection->insert('doctrine_migration_versions_centreon_open_tickets', ['version' => 'CentreonOpenTickets\Migrations\Version20260101000002']);

        $repository = $this->repository($connection);

        self::assertSame(2, $repository->countExecutedMigrations('centreon-open-tickets'));
        self::assertSame(0, $repository->countExecutedMigrations('centreon-bam-server'));
    }

    private function repository(Connection $connection): LegacyModuleRepository
    {
        return new LegacyModuleRepository(new Container(), $connection, $this->modulesDir);
    }

    /**
     * @param array<string, string> $installedReleases
     */
    private function connectionWith(array $installedReleases): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE modules_informations (id INTEGER PRIMARY KEY, name VARCHAR(255), mod_release VARCHAR(255))');
        foreach ($installedReleases as $name => $release) {
            $connection->insert('modules_informations', ['name' => $name, 'mod_release' => $release]);
        }

        return $connection;
    }

    /**
     * @param list<string> $upgradeDirectories
     */
    private function writeModule(string $module, ?string $codeRelease, array $upgradeDirectories): void
    {
        $filesystem = new Filesystem();
        $moduleDirectory = $this->modulesDir . '/' . $module;
        $configuration = $codeRelease === null ? [] : ['mod_release' => $codeRelease];
        $filesystem->dumpFile($moduleDirectory . '/conf.php', sprintf('<?php $module_conf[%s] = %s;', var_export($module, true), var_export($configuration, true)));
        foreach ($upgradeDirectories as $upgradeDirectory) {
            $filesystem->mkdir($moduleDirectory . '/upgrade/' . $upgradeDirectory);
        }
    }
}
