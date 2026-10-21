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

namespace CentreonModule\Infrastructure\Source;

use Centreon\Infrastructure\Service\CentreonDBManagerService;
use CentreonLegacy\Core\Configuration\Configuration;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Domain\Repository\ModulesInformationsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\Psr11\Container as Psr11Container;
use Symfony\Component\Filesystem\Filesystem;

class ModuleSourceTest extends TestCase
{
    private string $centreonPath;

    private string $installedVersion = '26.11.0';

    private ModulesInformationsRepository&MockObject $repository;

    private ModuleSource $source;

    protected function setUp(): void
    {
        $this->centreonPath = sys_get_temp_dir() . '/' . uniqid('centreon_', true) . '/';
        (new Filesystem())->dumpFile(
            $this->centreonPath . 'www/modules/my-module/conf.php',
            "<?php\n\$module_conf['my-module'] = ['rname' => 'My module', 'author' => 'Centreon', 'mod_release' => '26.11.0', 'infos' => ''];\n",
        );

        $this->repository = $this->createMock(ModulesInformationsRepository::class);
        $this->repository->method('getAllModuleVsVersion')->willReturnCallback(fn (): array => ['my-module' => $this->installedVersion]);

        $dbManager = $this->createMock(CentreonDBManagerService::class);
        $dbManager->method('getRepository')->willReturn($this->repository);
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('get')->willReturn($this->centreonPath);

        $container = new Container();
        $container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER] = $dbManager;
        $container['finder'] = $container->factory(static fn () => new \Symfony\Component\Finder\Finder());
        $container['configuration'] = $configuration;
        foreach ([
            LegacyServiceProvider::CENTREON_LEGACY_MODULE_INSTALLER,
            LegacyServiceProvider::CENTREON_LEGACY_MODULE_UPGRADER,
            LegacyServiceProvider::CENTREON_LEGACY_MODULE_REMOVER,
            LegacyServiceProvider::CENTREON_LEGACY_MODULE_LICENSE,
        ] as $service) {
            $container[$service] = static fn (): \stdClass => new \stdClass();
        }

        $this->source = new ModuleSource(new Psr11Container($container));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->centreonPath);
    }

    public function testNewerCodeMakesAModuleOutdated(): void
    {
        $this->installedVersion = '26.10.0';

        $module = $this->source->createEntityFromConfig($this->centreonPath . 'www/modules/my-module/conf.php');

        $this->assertFalse($module->isUpdated());
        $this->assertSame(
            ['my-module'],
            array_map(static fn (\CentreonModule\Infrastructure\Entity\Module $module): string => $module->getId(), $this->source->getList(null, true, false)),
            'The outdated modules are the ones the Extension Manager and the platform update upgrade',
        );
    }

    public function testAnUpToDateModuleIsNotListedAsOutdated(): void
    {
        $this->assertSame([], $this->source->getList(null, true, false));
    }

    public function testAnInstalledModuleWithoutMigrationsFollowsItsVersion(): void
    {
        $this->repository->expects($this->never())->method('findExecutedMigrations');

        $module = $this->source->createEntityFromConfig($this->centreonPath . 'www/modules/my-module/conf.php');

        $this->assertFalse($module->hasPendingMigrations());
        $this->assertTrue($module->isUpdated());
    }

    public function testAModuleWithPendingMigrationsNeedsAnUpdateAtItsInstalledVersion(): void
    {
        // An installation whose second migration failed.
        (new Filesystem())->dumpFile($this->centreonPath . 'www/modules/my-module/migrations/Version20260101000001.php', '<?php');
        (new Filesystem())->dumpFile($this->centreonPath . 'www/modules/my-module/migrations/Version20260101000002.php', '<?php');
        $this->repository->method('findExecutedMigrations')->with('my-module')->willReturn(['Version20260101000001']);

        $module = $this->source->createEntityFromConfig($this->centreonPath . 'www/modules/my-module/conf.php');

        $this->assertTrue($module->hasPendingMigrations());
        $this->assertFalse($module->isUpdated());
    }

    public function testAStaleExecutedMigrationDoesNotHideAPendingOne(): void
    {
        (new Filesystem())->dumpFile($this->centreonPath . 'www/modules/my-module/migrations/Version20260101000002.php', '<?php');
        // As many rows as files, but not the same migrations.
        $this->repository->method('findExecutedMigrations')->willReturn(['Version20250101000000']);

        $this->assertTrue($this->source->createEntityFromConfig($this->centreonPath . 'www/modules/my-module/conf.php')->hasPendingMigrations());
    }

    public function testCodeOlderThanTheInstalledVersionIsNotOutdated(): void
    {
        // A package rollback: updating would record an older version.
        $this->installedVersion = '27.04.0';

        $this->assertTrue($this->source->createEntityFromConfig($this->centreonPath . 'www/modules/my-module/conf.php')->isUpdated());
    }

    public function testAModuleWhoseMigrationsAllRanIsUpToDate(): void
    {
        (new Filesystem())->dumpFile($this->centreonPath . 'www/modules/my-module/migrations/Version20260101000001.php', '<?php');
        $this->repository->method('findExecutedMigrations')->willReturn(['Version20260101000001']);

        $module = $this->source->createEntityFromConfig($this->centreonPath . 'www/modules/my-module/conf.php');

        $this->assertFalse($module->hasPendingMigrations());
        $this->assertTrue($module->isUpdated());
    }

    public function testAModuleWhoseNameCannotHaveMigrationsIsNeverHeldOutdatedByThem(): void
    {
        (new Filesystem())->dumpFile($this->centreonPath . 'www/modules/my-module/migrations/Version20260101000001.php', '<?php');
        $this->repository->method('findExecutedMigrations')->willReturn(null);

        $module = $this->source->createEntityFromConfig($this->centreonPath . 'www/modules/my-module/conf.php');

        $this->assertFalse($module->hasPendingMigrations(), 'Its migrations cannot run: an update would never bring it up to date');
        $this->assertTrue($module->isUpdated());
    }
}
