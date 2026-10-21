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
use Centreon\Test\Mock\DependencyInjector\ServiceContainer;
use CentreonLegacy\Core\Migration\MigrationLauncher;
use CentreonLegacy\ServiceProvider;
use Pimple\Psr11\Container;

class InstallerTest extends \PHPUnit\Framework\TestCase
{
    private $container;

    private $db;

    private $information;

    private $utils;

    public function setUp(): void
    {
        $this->container = new ServiceContainer();

        $this->db = new CentreonDB();

        $configuration = ['name' => 'MyModule', 'rname' => 'MyModule', 'mod_release' => '1.0.0', 'is_removeable' => 1, 'infos' => 'my module for unit test', 'author' => 'unit test', 'svc_tools' => null, 'host_tools' => null];
        $this->information = $this->getMockBuilder(Information::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfiguration'])
            ->getMock();

        $this->information->expects($this->any())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $this->utils = $this->getMockBuilder(\CentreonLegacy\Core\Utils\Utils::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function tearDown(): void
    {
        $this->container->terminate();
        $this->container = null;
    }

    public function testInstall(): void
    {
        $filesystem = $this->getMockBuilder(\Symfony\Component\Filesystem\Filesystem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['exists'])
            ->getMock();
        $filesystem->expects($this->any())
            ->method('exists')
            ->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));

        $query = 'INSERT INTO modules_informations '
            . '(`name` , `rname` , `mod_release` , `is_removeable` , `infos` , `author` , '
            . '`svc_tools`, `host_tools`)'
            . 'VALUES ( :name , :rname , :mod_release , :is_removeable , :infos , :author , '
            . ':svc_tools , :host_tools )';
        $this->db->addResultSet(
            $query,
            []
        );
        $this->db->addResultSet(
            'SELECT MAX(id) as id FROM modules_informations',
            [['id' => 1]]
        );

        $this->container->registerProvider(new ConfigurationDBProvider($this->db));

        // Migrations last: a failure only leaves them pending.
        $journal = new \ArrayObject();
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file) use ($journal): void {
            $journal[] = basename($file);
        });
        $this->utils->method('executeSqlFile')->willReturnCallback(static function (string $file) use ($journal): void {
            $journal[] = basename($file);
        });
        $migrationLauncher = $this->createMock(MigrationLauncher::class);
        $migrationLauncher->expects($this->once())
            ->method('migrate')
            ->with('module:MyModule')
            ->willReturnCallback(static function () use ($journal): void {
                $journal[] = 'migrations';
            });
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $migrationLauncher;

        $installer = new Installer(new Container($this->container), $this->information, 'MyModule', $this->utils);
        $id = $installer->install();

        $this->assertEquals($id, 1);
        $this->assertSame(['install.pre.php', 'install.sql', 'install.php', 'migrations'], $journal->getArrayCopy());
    }

    public function testInstallWithoutMigrationsDirectoryDoesNotLaunchThem(): void
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturnCallback(static fn (string $file): bool => ! str_ends_with($file, '/migrations'));
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        $this->db->addResultSet(
            'INSERT INTO modules_informations '
            . '(`name` , `rname` , `mod_release` , `is_removeable` , `infos` , `author` , '
            . '`svc_tools`, `host_tools`)'
            . 'VALUES ( :name , :rname , :mod_release , :is_removeable , :infos , :author , '
            . ':svc_tools , :host_tools )',
            []
        );
        $this->db->addResultSet('SELECT MAX(id) as id FROM modules_informations', [['id' => 1]]);
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $migrationLauncher = $this->createMock(MigrationLauncher::class);
        $migrationLauncher->expects($this->never())->method('migrate');
        $this->container[ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER] = $migrationLauncher;

        $this->assertEquals(1, (new Installer(new Container($this->container), $this->information, 'MyModule', $this->utils))->install());
    }
}
