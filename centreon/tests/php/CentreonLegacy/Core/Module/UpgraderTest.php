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
            ->with('module:MyModule');
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
        $migrationLauncher->expects($this->once())->method('migrate')->with('module:MyModule');
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $migrationLauncher;
        $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->createMock(CacheClearer::class);

        $upgrader = new Upgrader(new Container($this->container), $this->information, 'MyModule', $this->utils, 1);

        $this->assertEquals(1, $upgrader->upgrade());
    }

    public function mockExists($file)
    {
        return ! (preg_match('/install\.pre\.php/', $file));
    }
}
