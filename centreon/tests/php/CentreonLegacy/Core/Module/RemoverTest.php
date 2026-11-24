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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Centreon\Test\Mock\CentreonDB;
use Centreon\Test\Mock\DependencyInjector\ConfigurationDBProvider;
use Centreon\Test\Mock\DependencyInjector\FilesystemProvider;
use Centreon\Test\Mock\DependencyInjector\ServiceContainer;
use CentreonLegacy\ServiceProvider;
use Core\Common\Domain\Exception\RepositoryException;
use Pimple\Psr11\Container;
use Tests\CentreonLegacy\Core\Module\Double\InMemoryOptionsConnection;

class RemoverTest extends \PHPUnit\Framework\TestCase
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

    public function testRemove(): void
    {
        $filesystem = $this->getMockBuilder(\Symfony\Component\Filesystem\Filesystem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['exists'])
            ->getMock();
        $filesystem->expects($this->any())
            ->method('exists')
            ->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));

        $this->db = new class () extends CentreonDB {
            public array $executedStatements = [];

            public function executeStatement(string $query, ?QueryParameters $queryParameters = null): int
            {
                $this->executedStatements[] = $query;

                return 0;
            }
        };
        $this->db->addResultSet(
            'DELETE FROM modules_informations WHERE id = :id ',
            []
        );
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $options = $this->optionsWithAProgress();

        $remover = new Remover(new Container($this->container), $this->information, 'my-module', $this->utils);
        $removed = $remover->remove();

        $this->assertEquals($removed, true);
        $this->assertSame(['DROP TABLE IF EXISTS `doctrine_migration_versions_my_module`'], $this->db->executedStatements);
        $this->assertSame([], $options->rows, 'The upgrade progress is forgotten');
    }

    public function testAFailedUninstallationKeepsTheMigrationHistory(): void
    {
        // The module stays installed: dropping its metadata table would make every migration look pending.
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        // No DROP TABLE registered: running one would throw.
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $options = $this->optionsWithAProgress();
        $this->utils->method('executePhpFile')->willReturnCallback(static function (string $file): void {
            if (str_ends_with($file, '/php/uninstall.php')) {
                throw new \RuntimeException('uninstall.php failed');
            }
        });

        try {
            (new Remover(new Container($this->container), $this->information, 'my-module', $this->utils))->remove();
            $this->fail('The uninstall.php failure must propagate');
        } catch (\RuntimeException $exception) {
            $this->assertSame('uninstall.php failed', $exception->getMessage());
        }

        $this->assertCount(1, $options->rows, 'The upgrade progress is kept');
    }

    public function testAModuleWhoseProgressCannotBeClearedStaysInstalled(): void
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        // No DROP TABLE nor DELETE registered: running one would throw another error.
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $this->optionsWithAProgress()->failOn('DELETE', new \RuntimeException('Lock wait timeout exceeded'));

        $this->expectException(RepositoryException::class);

        (new Remover(new Container($this->container), $this->information, 'my-module', $this->utils))->remove();
    }

    public function testAModuleWhoseHistoryCannotBeForgottenStaysInstalled(): void
    {
        // Reinstalled with its history, it would skip its migrations.
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        $this->db = new class () extends CentreonDB {
            public array $executedQueries = [];

            public function executeStatement(string $query, ?QueryParameters $queryParameters = null): int
            {
                $this->executedQueries[] = $query;

                throw new \RuntimeException('Lock wait timeout exceeded');
            }
        };
        // No DELETE registered: removing the row would throw another error.
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));
        $this->optionsWithAProgress();

        $this->expectExceptionMessage('Lock wait timeout exceeded');

        (new Remover(new Container($this->container), $this->information, 'my-module', $this->utils))->remove();
    }

    public function testRemoveLeavesTheTablesOfUnsupportedModuleNamesAlone(): void
    {
        $filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $this->container->registerProvider(new FilesystemProvider($filesystem));
        // No DROP TABLE registered: running one would throw.
        $this->db->addResultSet('DELETE FROM modules_informations WHERE id = :id ', []);
        $this->container->registerProvider(new ConfigurationDBProvider($this->db));

        $this->optionsWithAProgress();

        $remover = new Remover(new Container($this->container), $this->information, 'my.module', $this->utils);

        $this->assertTrue($remover->remove());
    }

    private function optionsWithAProgress(): InMemoryOptionsConnection
    {
        $options = new InMemoryOptionsConnection();
        $progress = new ModuleUpgradeProgressRepository($options);
        $progress->save('my-module', '1.0.1', ['pre' => true, 'sql' => 1, 'checksum' => '']);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS] = $progress;

        return $options;
    }
}
