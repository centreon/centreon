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

namespace CentreonLegacy\Core\Module;

use Centreon\Test\Mock\CentreonDB;
use Centreon\Test\Mock\DependencyInjector\ConfigurationDBProvider;
use Centreon\Test\Mock\DependencyInjector\FilesystemProvider;
use Centreon\Test\Mock\DependencyInjector\FinderProvider;
use Centreon\Test\Mock\DependencyInjector\ServiceContainer;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\Core\Migration\MigrationLauncher;
use CentreonLegacy\ServiceProvider;
use Pimple\Psr11\Container;
use Tests\CentreonLegacy\Core\Module\InMemoryOptionsDB;

class UpgraderTest extends \PHPUnit\Framework\TestCase
{
    private $container;

    private $db;

    private $information;

    private $utils;

    public function setUp(): void
    {
        $this->container = new ServiceContainer();

        $this->db = new CentreonDB();

        $this->information = $this->getMockBuilder(Information::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getInstalledInformation', 'getModulePath', 'getConfiguration'])
            ->getMock();

        $installedInformation = ['name' => 'MyModule', 'rname' => 'MyModule', 'mod_release' => '1.0.0', 'is_removeable' => 1, 'infos' => 'my module for unit test', 'author' => 'unit test', 'svc_tools' => null, 'host_tools' => null];
        $this->information->expects($this->any())
            ->method('getInstalledInformation')
            ->willReturn($installedInformation);

        $configuration = ['name' => 'MyModule', 'rname' => 'MyModule', 'mod_release' => '1.0.1', 'is_removeable' => 1, 'infos' => 'my module for unit test', 'author' => 'unit test', 'svc_tools' => null, 'host_tools' => null];
        $this->information->expects($this->any())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $this->information->expects($this->any())
            ->method('getModulePath')
            ->willReturn('/MyModule/');

        $this->utils = $this->getMockBuilder(\CentreonLegacy\Core\Utils\Utils::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireConfiguration', 'executeSqlFile', 'executePhpFile'])
            ->getMock();
        $upgradeConfiguration = ['MyModule' => ['name' => 'MyModule', 'rname' => 'MyModule', 'release_from' => '1.0.0', 'release_to' => '1.0.1', 'infos' => 'my module for unit test', 'author' => 'unit test']];
        $this->utils->expects($this->any())
            ->method('requireConfiguration')
            ->willReturn($upgradeConfiguration);
        $this->utils->expects($this->any())
            ->method('executeSqlFile');
        $this->utils->expects($this->any())
            ->method('executePhpFile');
    }

    public function tearDown(): void
    {
        $this->container->terminate();
        $this->container = null;
    }

    public function testUpgrader(): void
    {
        $filesystem = $this->getMockBuilder(\Symfony\Component\Filesystem\Filesystem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['exists'])
            ->getMock();
        $filesystem->expects($this->any())
            ->method('exists')
            ->will($this->returnCallback([$this, 'mockExists']));
        // ->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));

        $finder = $this->getMockBuilder(\Symfony\Component\Finder\Finder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['directories', 'depth', 'in', 'getIterator'])
            ->getMock();
        $finder->expects($this->any())
            ->method('directories')
            ->willReturn($finder);
        $finder->expects($this->any())
            ->method('depth')
            ->willReturn($finder);
        $finder->expects($this->any())
            ->method('in')
            ->willReturn($finder);
        $finder->expects($this->any())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([new \SplFileInfo('MyModule-1.0.1')]));
        $this->container->registerProvider(new FinderProvider($finder));

        $query = 'UPDATE modules_informations '
            . 'SET `name` = :name , `rname` = :rname , `is_removeable` = :is_removeable , '
            . '`infos` = :infos , `author` = :author , '
            . '`svc_tools` = :svc_tools , `host_tools` = :host_tools WHERE id = :id';
        $this->db->addResultSet(
            $query,
            []
        );
        $this->db->addResultSet(
            'SELECT MAX(id) as id FROM modules_informations',
            [['id' => 1]]
        );
        $this->db->addResultSet(
            'UPDATE modules_informations SET `mod_release` = :mod_release WHERE id = :id',
            []
        );

        $this->container->registerProvider(new ConfigurationDBProvider($this->db));

        $migrationLauncher = $this->createMock(MigrationLauncher::class);
        $migrationLauncher->expects($this->once())
            ->method('migrate')
            ->with('MyModule');
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $migrationLauncher;

        // Not suspended: the upgrade clears the caches itself.
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('clear');
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $cacheClearer;

        $upgrader = new Upgrader(new Container($this->container), $this->information, 'MyModule', $this->utils, 1);
        $id = $upgrader->upgrade();

        $this->assertEquals($id, 1);
    }

    public function testUpgradesAModuleShippingDoctrineMigrationsOnly(): void
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturnCallback(static fn (string $file): bool => ! str_ends_with($file, '/upgrade/'));
        $this->container->registerProvider(new FilesystemProvider($filesystem));

        // Finder::in() throws on a missing directory.
        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->expects($this->never())->method('in');
        $this->container->registerProvider(new FinderProvider($finder));

        $this->db->addResultSet(
            'UPDATE modules_informations '
            . 'SET `name` = :name , `rname` = :rname , `is_removeable` = :is_removeable , '
            . '`infos` = :infos , `author` = :author , '
            . '`svc_tools` = :svc_tools , `host_tools` = :host_tools WHERE id = :id',
            []
        );
        $this->db->addResultSet('UPDATE modules_informations SET `mod_release` = :mod_release WHERE id = :id', []);
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));

        $migrationLauncher = $this->createMock(MigrationLauncher::class);
        $migrationLauncher->expects($this->once())->method('migrate')->with('MyModule');
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $migrationLauncher;
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);

        $upgrader = new Upgrader(new Container($this->container), $this->information, 'MyModule', $this->utils, 1);

        $this->assertEquals(1, $upgrader->upgrade());
    }

    public function testRecordsAVersionOnlyOnceItsScriptsSucceeded(): void
    {
        $journal = new \ArrayObject();

        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));

        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->method('directories')->willReturn($finder);
        $finder->method('depth')->willReturn($finder);
        $finder->method('in')->willReturn($finder);
        $finder->method('getIterator')->willReturn(new \ArrayIterator([new \SplFileInfo('1.0.1')]));
        $this->container->registerProvider(new FinderProvider($finder));

        $this->utils->method('executeSqlFile')->willReturnCallback(static function (string $file) use ($journal): void {
            $journal[] = 'script:' . basename($file);
        });
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use ($journal): void {
            $journal[] = 'script:' . basename($file);
        });

        $this->db->addResultSet(
            'UPDATE modules_informations '
            . 'SET `name` = :name , `rname` = :rname , `is_removeable` = :is_removeable , '
            . '`infos` = :infos , `author` = :author , '
            . '`svc_tools` = :svc_tools , `host_tools` = :host_tools WHERE id = :id',
            []
        );
        $this->db->addResultSet(
            'UPDATE modules_informations SET `mod_release` = :mod_release WHERE id = :id',
            [],
            null,
            static function (array $params) use ($journal): void {
                $journal[] = 'version:' . $params[':mod_release'];
            }
        );
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $this->createMock(MigrationLauncher::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = $progress = new UpgradeProgress(new InMemoryOptionsDB());

        (new Upgrader(new Container($this->container), $this->information, 'MyModule', $this->utils, 1))->upgrade();

        $this->assertSame(
            ['script:upgrade.pre.php', 'script:upgrade.sql', 'script:upgrade.php', 'version:1.0.1', 'version:1.0.1'],
            $journal->getArrayCopy()
        );
        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $progress->get('MyModule', '1.0.1'), 'The progress is forgotten once the version is recorded');
    }

    public function testResumesAFailedVersionWhereItStopped(): void
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));

        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->method('directories')->willReturn($finder);
        $finder->method('depth')->willReturn($finder);
        $finder->method('in')->willReturn($finder);
        $finder->method('getIterator')->willReturn(new \ArrayIterator([new \SplFileInfo('1.0.1')]));
        $this->container->registerProvider(new FinderProvider($finder));

        $this->db->addResultSet(
            'UPDATE modules_informations '
            . 'SET `name` = :name , `rname` = :rname , `is_removeable` = :is_removeable , '
            . '`infos` = :infos , `author` = :author , '
            . '`svc_tools` = :svc_tools , `host_tools` = :host_tools WHERE id = :id',
            []
        );
        $versions = [];
        $this->db->addResultSet(
            'UPDATE modules_informations SET `mod_release` = :mod_release WHERE id = :id',
            [],
            null,
            static function (array $params) use (&$versions): void {
                $versions[] = $params[':mod_release'];
            }
        );
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $this->createMock(MigrationLauncher::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = $progress = new UpgradeProgress(new InMemoryOptionsDB());

        // First attempt: the pre-upgrade script passes, then the second SQL query fails.
        $phpScripts = [];
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use (&$phpScripts): void {
            $phpScripts[] = basename($file);
        });
        $sqlAttempts = [];
        $this->utils->method('executeSqlFile')->willReturnCallback(
            static function (string $file, array $macros, bool $monitoring, int $skipped, callable $onQueryExecuted) use (&$sqlAttempts): void {
                $sqlAttempts[] = $skipped;
                if (count($sqlAttempts) === 1) {
                    $onQueryExecuted(1);

                    throw new \Exception('Duplicate column name');
                }
                $onQueryExecuted(2);
            }
        );
        $upgrader = new Upgrader(new Container($this->container), $this->information, 'MyModule', $this->utils, 1);

        try {
            $upgrader->upgrade();
            $this->fail('The SQL failure must propagate');
        } catch (\Exception $exception) {
            $this->assertSame('Duplicate column name', $exception->getMessage());
        }
        $this->assertSame([], $versions, 'A failed version is not recorded');
        $this->assertSame(['pre' => true, 'sql' => 1, 'checksum' => ''], $progress->get('MyModule', '1.0.1'));

        // Second attempt: resumes after the first query, without replaying the pre-upgrade script.
        $upgrader->upgrade();

        $this->assertSame(['upgrade.pre.php', 'upgrade.php'], $phpScripts);
        $this->assertSame([0, 1], $sqlAttempts);
        $this->assertSame(['1.0.1', '1.0.1'], $versions);
        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $progress->get('MyModule', '1.0.1'));
    }

    public function testRestartsTheSqlOfAVersionWhoseUpgradeSqlChanged(): void
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));

        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->method('directories')->willReturn($finder);
        $finder->method('depth')->willReturn($finder);
        $finder->method('in')->willReturn($finder);
        $finder->method('getIterator')->willReturn(new \ArrayIterator([new \SplFileInfo('1.0.1')]));
        $this->container->registerProvider(new FinderProvider($finder));

        $this->db->addResultSet(
            'UPDATE modules_informations '
            . 'SET `name` = :name , `rname` = :rname , `is_removeable` = :is_removeable , '
            . '`infos` = :infos , `author` = :author , '
            . '`svc_tools` = :svc_tools , `host_tools` = :host_tools WHERE id = :id',
            []
        );
        $this->db->addResultSet('UPDATE modules_informations SET `mod_release` = :mod_release WHERE id = :id', []);
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $this->createMock(MigrationLauncher::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = $progress = new UpgradeProgress(new InMemoryOptionsDB());

        // A previous attempt stopped after the first query of an upgrade.sql that was fixed since.
        $progress->save('MyModule', '1.0.1', ['pre' => true, 'sql' => 1, 'checksum' => 'checksum of the previous upgrade.sql']);
        $phpScripts = [];
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use (&$phpScripts): void {
            $phpScripts[] = basename($file);
        });
        $skippedQueries = [];
        $this->utils->method('executeSqlFile')->willReturnCallback(
            static function (string $file, array $macros, bool $monitoring, int $skipped) use (&$skippedQueries): void {
                $skippedQueries[] = $skipped;
            }
        );

        (new Upgrader(new Container($this->container), $this->information, 'MyModule', $this->utils, 1))->upgrade();

        $this->assertSame([0], $skippedQueries, 'The fixed upgrade.sql restarts from its first query');
        $this->assertSame(['upgrade.php'], $phpScripts, 'The pre-upgrade script that passed is not replayed');
    }

    public function testResumesOnlyTheUpgradeSqlItStartedFrom(): void
    {
        $modulePath = sys_get_temp_dir() . '/' . uniqid('module_', true);
        $sqlFile = $modulePath . '/upgrade/1.0.1/sql/upgrade.sql';
        (new \Symfony\Component\Filesystem\Filesystem())->dumpFile($sqlFile, "SELECT 1;\nSELECT 2;\n");

        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->method('directories')->willReturn($finder);
        $finder->method('depth')->willReturn($finder);
        $finder->method('in')->willReturn($finder);
        $finder->method('getIterator')->willReturnCallback(static fn (): \ArrayIterator => new \ArrayIterator([new \SplFileInfo('1.0.1')]));
        $this->container->registerProvider(new FinderProvider($finder));
        $this->db->addResultSet(
            'UPDATE modules_informations '
            . 'SET `name` = :name , `rname` = :rname , `is_removeable` = :is_removeable , '
            . '`infos` = :infos , `author` = :author , '
            . '`svc_tools` = :svc_tools , `host_tools` = :host_tools WHERE id = :id',
            []
        );
        $this->db->addResultSet('UPDATE modules_informations SET `mod_release` = :mod_release WHERE id = :id', []);
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $this->createMock(MigrationLauncher::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = $progress = new UpgradeProgress(new InMemoryOptionsDB());

        $utils = $this->getMockBuilder(\CentreonLegacy\Core\Utils\Utils::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireConfiguration', 'executeSqlFile', 'executePhpFile', 'buildPath'])
            ->getMock();
        $utils->method('buildPath')->willReturn($modulePath);
        $utils->method('requireConfiguration')->willReturn(['MyModule' => ['release_from' => '1.0.0', 'release_to' => '1.0.1']]);
        $skippedQueries = [];
        // Every attempt runs the first query it was not told to skip, then fails.
        $utils->method('executeSqlFile')->willReturnCallback(
            static function (string $file, array $macros, bool $monitoring, int $skipped, callable $onQueryExecuted) use (&$skippedQueries): void {
                $skippedQueries[] = $skipped;
                $onQueryExecuted($skipped + 1);

                throw new \Exception('upgrade.sql failed');
            }
        );
        $upgrade = fn () => (new Upgrader(new Container($this->container), $this->information, 'MyModule', $utils, 1))->upgrade();

        try {
            foreach (['first attempt', 'same upgrade.sql', 'fixed upgrade.sql'] as $attempt) {
                if ($attempt === 'fixed upgrade.sql') {
                    file_put_contents($sqlFile, "SELECT 1;\nSELECT 3;\n");
                }

                try {
                    $upgrade();
                    $this->fail('The upgrade.sql fails on every attempt');
                } catch (\Exception $exception) {
                    $this->assertSame('upgrade.sql failed', $exception->getMessage());
                }
                $this->assertSame(md5_file($sqlFile), $progress->get('MyModule', '1.0.1')['checksum'], $attempt);
            }
        } finally {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($modulePath);
        }

        $this->assertSame([0, 1, 0], $skippedQueries, 'The same upgrade.sql resumes, a fixed one restarts');
    }

    public function testAFailingModuleMigrationLeavesTheModuleVersionUnrecorded(): void
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturnCallback(static fn (string $file): bool => ! str_ends_with($file, '/upgrade/'));
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        $this->container->registerProvider(new FinderProvider($this->createMock(\Symfony\Component\Finder\Finder::class)));
        $this->db->addResultSet(
            'UPDATE modules_informations '
            . 'SET `name` = :name , `rname` = :rname , `is_removeable` = :is_removeable , '
            . '`infos` = :infos , `author` = :author , '
            . '`svc_tools` = :svc_tools , `host_tools` = :host_tools WHERE id = :id',
            []
        );
        $versions = [];
        $this->db->addResultSet(
            'UPDATE modules_informations SET `mod_release` = :mod_release WHERE id = :id',
            [],
            null,
            static function (array $params) use (&$versions): void {
                $versions[] = $params[':mod_release'];
            }
        );
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $migrationLauncher = $this->createMock(MigrationLauncher::class);
        $migrationLauncher->method('migrate')->willThrowException(new \RuntimeException('Version20260101000001 failed'));
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $migrationLauncher;
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->never())->method('clear');
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $cacheClearer;

        try {
            (new Upgrader(new Container($this->container), $this->information, 'MyModule', $this->utils, 1))->upgrade();
            $this->fail('The migration failure must be reported');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Version20260101000001 failed', $exception->getMessage());
        }

        $this->assertSame([], $versions, 'The next update resumes the pending migrations of this version');
    }

    public function mockExists($file)
    {
        return ! (preg_match('/install\.pre\.php/', $file));
    }
}
