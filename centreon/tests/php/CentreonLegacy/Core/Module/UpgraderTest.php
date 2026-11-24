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
use CentreonLegacy\Core\Utils\ExecutedQueries;
use CentreonLegacy\Core\Utils\ExecutedQueriesChangedException;
use CentreonLegacy\ServiceProvider;
use Core\Common\Domain\Exception\RepositoryException;
use Pimple\Psr11\Container;
use Tests\CentreonLegacy\Core\Module\Double\InMemoryOptionsConnection;

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

        $installedInformation = ['name' => 'my-module', 'rname' => 'my-module', 'mod_release' => '1.0.0', 'is_removeable' => 1, 'infos' => 'my module for unit test', 'author' => 'unit test', 'svc_tools' => null, 'host_tools' => null];
        $this->information->expects($this->any())
            ->method('getInstalledInformation')
            ->willReturn($installedInformation);

        $configuration = ['name' => 'my-module', 'rname' => 'my-module', 'mod_release' => '1.0.1', 'is_removeable' => 1, 'infos' => 'my module for unit test', 'author' => 'unit test', 'svc_tools' => null, 'host_tools' => null];
        $this->information->expects($this->any())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $this->information->expects($this->any())
            ->method('getModulePath')
            ->willReturn('/my-module/');

        $this->utils = $this->getMockBuilder(\CentreonLegacy\Core\Utils\Utils::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireConfiguration', 'executeSqlFile', 'executePhpFile'])
            ->getMock();
        $upgradeConfiguration = ['my-module' => ['name' => 'my-module', 'rname' => 'my-module', 'release_from' => '1.0.0', 'release_to' => '1.0.1', 'infos' => 'my module for unit test', 'author' => 'unit test']];
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
            ->with('my-module');
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $migrationLauncher;

        // Not suspended: the upgrade clears the caches itself.
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('clear');
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $cacheClearer;

        $upgrader = new Upgrader(new Container($this->container), $this->information, 'my-module', $this->utils, 1);
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
        $migrationLauncher->expects($this->once())->method('migrate')->with('my-module');
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $migrationLauncher;
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);

        $upgrader = new Upgrader(new Container($this->container), $this->information, 'my-module', $this->utils, 1);

        $this->assertEquals(1, $upgrader->upgrade());
    }

    public function testRecordsAVersionOnceItsScriptsSucceededThenRunsTheMigrations(): void
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
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $this->migrationLauncher($journal);
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = $progress = new ModuleUpgradeProgressRepository(new InMemoryOptionsConnection());

        (new Upgrader(new Container($this->container), $this->information, 'my-module', $this->utils, 1))->upgrade();

        $this->assertSame(
            ['script:upgrade.pre.php', 'script:upgrade.sql', 'script:upgrade.php', 'version:1.0.1', 'migrations'],
            $journal->getArrayCopy(),
            'Already at the version of the code: not recorded twice',
        );
        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $progress->get('my-module', '1.0.1'), 'The progress is forgotten once the version is recorded');
    }

    public function testIgnoresTheUpgradesBeyondTheVersionOfTheCode(): void
    {
        // 1.0.2 is a pre-release next to a conf.php still at 1.0.1: it would run again once released.
        $this->assertSame(
            ['script:upgrade.pre.php', 'script:upgrade.sql', 'script:upgrade.php', 'version:1.0.1'],
            $this->upgradeJournal(['1.0.1', '1.0.2'], '1.0.0'),
        );
    }

    public function testRecordsTheVersionOfTheCodeWithoutAnUpgradeDirectoryForIt(): void
    {
        $this->assertSame(['version:1.0.1'], $this->upgradeJournal([], '1.0.0'));
    }

    public function testNeverRecordsAVersionOlderThanTheInstalledOne(): void
    {
        // A package rollback: recording 1.0.1 would replay the upgrades up to 2.1.0 on the next upgrade.
        $this->assertSame([], $this->upgradeJournal([], '2.1.0'));
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
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = $progress = new ModuleUpgradeProgressRepository(new InMemoryOptionsConnection());

        // First attempt: the pre-upgrade script passes, then the second SQL query fails.
        $phpScripts = [];
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use (&$phpScripts): void {
            $phpScripts[] = basename($file);
        });
        $sqlAttempts = [];
        $this->utils->method('executeSqlFile')->willReturnCallback(
            static function (string $file, array $macros, bool $monitoring, ?ExecutedQueries $alreadyExecuted, callable $onQueryExecuted) use (&$sqlAttempts): void {
                $sqlAttempts[] = $alreadyExecuted;
                if (count($sqlAttempts) === 1) {
                    $onQueryExecuted(new ExecutedQueries(1, 'checksum of query 1'));

                    throw new \Exception('Duplicate column name');
                }
                $onQueryExecuted(new ExecutedQueries(2, 'checksum of queries 1 and 2'));
            }
        );
        $upgrader = new Upgrader(new Container($this->container), $this->information, 'my-module', $this->utils, 1);

        try {
            $upgrader->upgrade();
            $this->fail('The SQL failure must propagate');
        } catch (\Exception $exception) {
            $this->assertSame('Duplicate column name', $exception->getMessage());
        }
        $this->assertSame([], $versions, 'A failed version is not recorded');
        $this->assertSame(['pre' => true, 'sql' => 1, 'checksum' => 'checksum of query 1'], $progress->get('my-module', '1.0.1'));

        // Second attempt: resumes after the first query, without replaying the pre-upgrade script.
        $upgrader->upgrade();

        $this->assertSame(['upgrade.pre.php', 'upgrade.php'], $phpScripts);
        $this->assertEquals([new ExecutedQueries(0, ''), new ExecutedQueries(1, 'checksum of query 1')], $sqlAttempts);
        $this->assertSame(['1.0.1'], $versions);
        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $progress->get('my-module', '1.0.1'));
    }

    public function testResumesTheFailedVersionWithoutReplayingThePreviousOne(): void
    {
        $journal = new \ArrayObject();
        $upgrader = $this->multiVersionUpgrader(['1.0.1', '1.0.2'], $journal, new InMemoryOptionsConnection());
        $sqlAttempts = [];
        // 1.0.1 passes; the first attempt of 1.0.2 fails at its second query.
        $this->utils->method('executeSqlFile')->willReturnCallback(
            static function (string $file, array $macros, bool $monitoring, ?ExecutedQueries $alreadyExecuted, callable $onQueryExecuted) use (&$sqlAttempts, $journal): void {
                $version = basename(dirname($file, 2));
                $skipped = $alreadyExecuted->count ?? 0;
                $sqlAttempts[] = [$version, $skipped];
                for ($query = $skipped + 1; $query <= 3; ++$query) {
                    if ($version === '1.0.2' && $query === 2 && count($sqlAttempts) === 2) {
                        throw new \Exception('Duplicate column name');
                    }
                    $journal[] = "query:{$version}:{$query}";
                    $onQueryExecuted(new ExecutedQueries($query, "checksum of {$version} up to {$query}"));
                }
            }
        );
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use ($journal): void {
            $journal[] = 'script:' . basename(dirname($file, 2)) . ':' . basename($file);
        });

        try {
            $upgrader->upgrade();
            $this->fail('The SQL failure must propagate');
        } catch (\Exception $exception) {
            $this->assertSame('Duplicate column name', $exception->getMessage());
        }
        $this->assertContains('version:1.0.1', $journal->getArrayCopy(), 'The version that passed is recorded');
        $this->assertNotContains('version:1.0.2', $journal->getArrayCopy());

        $journal->exchangeArray([]);
        $upgrader->upgrade();

        $this->assertSame([['1.0.1', 0], ['1.0.2', 0], ['1.0.2', 1]], $sqlAttempts, '1.0.2 resumes after its first query');
        $this->assertSame(
            ['query:1.0.2:2', 'query:1.0.2:3', 'script:1.0.2:upgrade.php', 'version:1.0.2', 'migrations'],
            $journal->getArrayCopy(),
            '1.0.1 is not replayed',
        );
    }

    public function testAProgressThatCannotBeSavedStopsTheUpgrade(): void
    {
        $journal = new \ArrayObject();
        $options = new InMemoryOptionsConnection();
        $options->failOn('UPDATE', new \RuntimeException('Lock wait timeout exceeded'));
        $upgrader = $this->multiVersionUpgrader(['1.0.1'], $journal, $options);
        $this->utils->method('executeSqlFile')->willReturnCallback(static function (string $file) use ($journal): void {
            $journal[] = 'script:' . basename($file);
        });
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use ($journal): void {
            $journal[] = 'script:' . basename($file);
        });

        try {
            $upgrader->upgrade();
            $this->fail('The progress failure must propagate');
        } catch (RepositoryException) {
        }

        $this->assertSame(['script:upgrade.pre.php'], $journal->getArrayCopy(), 'Nothing runs once the progress cannot be kept');
    }

    public function testAProgressThatCannotBeClearedDoesNotStopTheUpgrade(): void
    {
        $journal = new \ArrayObject();
        $options = new InMemoryOptionsConnection();
        $options->failOn('DELETE', new \RuntimeException('Lock wait timeout exceeded'));
        $upgrader = $this->multiVersionUpgrader(['1.0.1', '1.0.2'], $journal, $options);
        $this->utils->method('executeSqlFile')->willReturnCallback(static function (string $file) use ($journal): void {
            $journal[] = 'script:' . basename(dirname($file, 2)) . ':' . basename($file);
        });

        $upgrader->upgrade();

        $this->assertSame(
            ['script:1.0.1:upgrade.sql', 'version:1.0.1', 'script:1.0.2:upgrade.sql', 'version:1.0.2', 'migrations'],
            $journal->getArrayCopy(),
        );
    }

    public function testRefusesToResumeAnUpgradeSqlWhoseExecutedQueriesChanged(): void
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
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = $progress = new ModuleUpgradeProgressRepository(new InMemoryOptionsConnection());

        // A previous attempt stopped after the first query of an upgrade.sql whose first query was changed since.
        $progress->save('my-module', '1.0.1', ['pre' => true, 'sql' => 1, 'checksum' => 'checksum of query 1']);
        $phpScripts = [];
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use (&$phpScripts): void {
            $phpScripts[] = basename($file);
        });
        $this->utils->method('executeSqlFile')->willReturnCallback(
            static function (string $file): void {
                throw ExecutedQueriesChangedException::inFile($file, 1);
            }
        );

        try {
            (new Upgrader(new Container($this->container), $this->information, 'my-module', $this->utils, 1))->upgrade();
            $this->fail('A changed upgrade.sql must not be resumed');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('The first 1 queries of', $exception->getMessage());
            $this->assertStringContainsString('delete the "module_upgrade_progress:my-module" option', $exception->getMessage());
        }

        $this->assertSame([], $phpScripts, 'Neither the pre-upgrade script that passed nor upgrade.php runs');
        $this->assertSame(['pre' => true, 'sql' => 1, 'checksum' => 'checksum of query 1'], $progress->get('my-module', '1.0.1'), 'The progress is kept');
    }

    public function testReplaysOnlyTheFinalScriptWhenItFailed(): void
    {
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
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = new ModuleUpgradeProgressRepository(new InMemoryOptionsConnection());

        // First attempt: the pre-upgrade script and both queries pass, then upgrade.php fails.
        $phpScripts = [];
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use (&$phpScripts): void {
            $phpScripts[] = basename($file);
            if ($phpScripts === ['upgrade.pre.php', 'upgrade.php']) {
                throw new \Exception('upgrade.php failed');
            }
        });
        $skippedQueries = [];
        $this->utils->method('executeSqlFile')->willReturnCallback(
            static function (string $file, array $macros, bool $monitoring, ?ExecutedQueries $alreadyExecuted, callable $onQueryExecuted) use (&$skippedQueries): void {
                $skipped = $alreadyExecuted->count ?? 0;
                $skippedQueries[] = $skipped;
                for ($query = $skipped + 1; $query <= 2; ++$query) {
                    $onQueryExecuted(new ExecutedQueries($query, "checksum up to {$query}"));
                }
            }
        );
        $upgrader = new Upgrader(new Container($this->container), $this->information, 'my-module', $this->utils, 1);

        try {
            $upgrader->upgrade();
            $this->fail('The upgrade.php failure must propagate');
        } catch (\Exception $exception) {
            $this->assertSame('upgrade.php failed', $exception->getMessage());
        }
        $upgrader->upgrade();

        $this->assertSame(['upgrade.pre.php', 'upgrade.php', 'upgrade.php'], $phpScripts, 'upgrade.php is the only step replayed whole');
        $this->assertSame([0, 2], $skippedQueries, 'The queries that passed are skipped');
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
            (new Upgrader(new Container($this->container), $this->information, 'my-module', $this->utils, 1))->upgrade();
            $this->fail('The migration failure must be reported');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Version20260101000001 failed', $exception->getMessage());
        }

        $this->assertSame([], $versions, 'The next update resumes the pending migrations of this version');
    }

    public function testRefusesAModuleShippingMigrationsUnderAnUnsupportedNameBeforeAnyScript(): void
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        // No query registered: updating the module would throw.
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The module "centreon_foo" ships migrations');

        (new Upgrader(new Container($this->container), $this->information, 'centreon_foo', $this->utils, 1))->upgrade();
    }

    public function mockExists($file)
    {
        return ! (preg_match('/install\.pre\.php/', $file));
    }

    /**
     * @param list<string> $upgradeDirectories
     *
     * @return list<string> the scripts run and the versions recorded
     */
    private function upgradeJournal(array $upgradeDirectories, string $installedVersion): array
    {
        $journal = new \ArrayObject();

        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));

        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->method('directories')->willReturn($finder);
        $finder->method('depth')->willReturn($finder);
        $finder->method('in')->willReturn($finder);
        $finder->method('getIterator')->willReturn(new \ArrayIterator(array_map(static fn (string $directory): \SplFileInfo => new \SplFileInfo($directory), $upgradeDirectories)));
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
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = new ModuleUpgradeProgressRepository(new InMemoryOptionsConnection());

        $information = $this->createMock(Information::class);
        $information->method('getInstalledInformation')->willReturn(['name' => 'my-module', 'mod_release' => $installedVersion]);
        $information->method('getConfiguration')->willReturn(['name' => 'my-module', 'rname' => 'my-module', 'mod_release' => '1.0.1', 'is_removeable' => 1, 'infos' => '', 'author' => '', 'svc_tools' => null, 'host_tools' => null]);
        $information->method('getModulePath')->willReturn('/my-module/');

        (new Upgrader(new Container($this->container), $information, 'my-module', $this->utils, 1))->upgrade();

        return $journal->getArrayCopy();
    }

    /**
     * @param list<string> $upgradeDirectories up to the version of the code, 1.0.0 installed
     * @param \ArrayObject<int, string> $journal the versions recorded and the migrations run
     */
    private function multiVersionUpgrader(array $upgradeDirectories, \ArrayObject $journal, InMemoryOptionsConnection $options): Upgrader
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->method('directories')->willReturn($finder);
        $finder->method('depth')->willReturn($finder);
        $finder->method('in')->willReturn($finder);
        $finder->method('getIterator')->willReturnCallback(static fn (): \ArrayIterator => new \ArrayIterator(array_map(static fn (string $directory): \SplFileInfo => new \SplFileInfo($directory), $upgradeDirectories)));
        $this->container->registerProvider(new FinderProvider($finder));

        $installedVersion = '1.0.0';
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
            static function (array $params) use ($journal, &$installedVersion): void {
                $installedVersion = $params[':mod_release'];
                $journal[] = 'version:' . $params[':mod_release'];
            }
        );
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $this->migrationLauncher($journal);
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = new ModuleUpgradeProgressRepository($options);

        $information = $this->createMock(Information::class);
        $information->method('getInstalledInformation')->willReturnCallback(
            static function () use (&$installedVersion): array {
                return ['name' => 'my-module', 'mod_release' => $installedVersion];
            }
        );
        $information->method('getConfiguration')->willReturn(['name' => 'my-module', 'rname' => 'my-module', 'mod_release' => end($upgradeDirectories), 'is_removeable' => 1, 'infos' => '', 'author' => '', 'svc_tools' => null, 'host_tools' => null]);
        $information->method('getModulePath')->willReturn('/my-module/');

        return new Upgrader(new Container($this->container), $information, 'my-module', $this->utils, 1);
    }

    /**
     * @param \ArrayObject<int, string> $journal
     */
    private function migrationLauncher(\ArrayObject $journal): MigrationLauncher
    {
        return new class ($journal) extends MigrationLauncher {
            /**
             * @param \ArrayObject<int, string> $journal
             */
            public function __construct(private readonly \ArrayObject $journal)
            {
            }

            public function migrate(?string $module = null): void
            {
                $this->journal[] = 'migrations';
            }
        };
    }
}
