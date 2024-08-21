<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonModule\Tests\Infrastructure\Source;

use Centreon\Test\Mock;
use Centreon\Test\Traits\TestCaseExtensionTrait;
use CentreonLegacy\Core\Configuration\Configuration;
use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Source\ModuleException;
use CentreonModule\Infrastructure\Source\ModuleSource;
use CentreonModule\Tests\Resources\Traits\SourceDependencyTrait;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\Psr11\Container as ContainerWrap;
use Symfony\Component\Finder\Finder;
use VirtualFileSystem\FileSystem;

class ModuleSourceTest extends TestCase
{
    use TestCaseExtensionTrait;
    use SourceDependencyTrait;

    /** @var string */
    public static $moduleName = 'test-module';

    /** @var string */
    public static $moduleNameMissing = 'missing-module';

    /** @var string[] */
    public static $moduleInfo = [
        'rname' => 'Curabitur congue porta neque',
        'name' => 'test-module',
        'mod_release' => 'x.y.q',
        'infos' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent id ante neque.',
        'is_removeable' => '1',
        'author' => 'Centreon',
        'stability' => 'alpha',
        'last_update' => '2001-01-01',
        'release_note' => 'http://localhost',
        'images' => 'images/image1.png',
    ];

    /** @var string[][][] */
    public static $sqlQueryVsData = [
        'SELECT `name` AS `id`, `mod_release` AS `version` FROM `modules_informations`' => [
            [
                'id' => 'test-module',
                'version' => 'x.y.z',
            ],
        ],
        'SELECT `id` FROM `modules_informations` WHERE `name` = :name LIMIT 0, 1' => [
            [
                'id' => '1',
            ],
        ],
    ];

    /** @var ModuleSource|\PHPUnit\Framework\MockObject\MockObject */
    private $source;

    /** @var ContainerWrap */
    private $containerWrap;

    /** @var FileSystem */
    private $fs;

    protected function setUp(): void
    {
        // mount VFS
        $this->fs = new FileSystem();
        $this->fs->createDirectory('/modules');
        $this->fs->createDirectory('/modules/' . static::$moduleName);
        $this->fs->createFile(
            '/modules/' . static::$moduleName . '/' . ModuleSource::CONFIG_FILE,
            static::buildConfContent(),
        );

        // provide services
        $container = new Container();
        $container['finder'] = new Finder();
        $container['configuration'] = $this->createMock(Configuration::class);

        // DB service
        $container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER] = new Mock\CentreonDBManagerService();
        foreach (static::$sqlQueryVsData as $query => $data) {
            $container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]->addResultSet($query, $data);
        }

        $this->setUpSourceDependency($container);

        $this->containerWrap = new ContainerWrap($container);

        $this->source = $this->getMockBuilder(ModuleSource::class)
            ->onlyMethods([
                'getPath',
                'getModuleConf',
            ])
            ->setConstructorArgs([
                $this->containerWrap,
            ])
            ->getMock();
        $this->source
            ->method('getPath')
            ->will($this->returnCallback(fn() => $this->fs->path('/modules/')));
        $this->source
            ->method('getModuleConf')
            ->will($this->returnCallback(fn() => [
                ModuleSourceTest::$moduleName => ModuleSourceTest::$moduleInfo,
            ]));
    }

    public function tearDown(): void
    {
    }

    public function testGetList(): void
    {
        $result = $this->source->getList();

        $this->assertTrue(is_array($result));

        $result2 = $this->source->getList(static::$moduleNameMissing);
        $this->assertEquals([], $result2);
    }

    public function testGetDetail(): void
    {
        (function (): void {
            $result = $this->source->getDetail(static::$moduleNameMissing);

            $this->assertNull($result);
        })();

        (function (): void {
            $result = $this->source->getDetail(static::$moduleName);

            $this->assertInstanceOf(Module::class, $result);
        })();
    }

    /**
     * @throws \Exception
     */
    public function testRemove(): void
    {
        try {
            $this->source->remove(static::$moduleNameMissing);
        } catch (\Exception $ex) {
            $this->assertEquals(static::$moduleNameMissing, $ex->getMessage());
            $this->assertEquals(1, $ex->getCode()); // check moduleId
        }

        $this->source->remove(static::$moduleName);
    }

    /**
     * @throws \Exception
     */
    public function testUpdate(): void
    {
        try {
            $this->assertNull($this->source->update(static::$moduleNameMissing));
        } catch (\Exception $ex) {
            $this->assertEquals(
                ModuleException::moduleIsMissing(static::$moduleNameMissing)->getMessage(),
                $ex->getMessage()
            );
        }

        $this->source->update(static::$moduleName);
    }

    public function testCreateEntityFromConfig(): void
    {
        $configFile = $this->getConfFilePath();
        $result = $this->source->createEntityFromConfig($configFile);
        $images = [
            ModuleSource::PATH_WEB . $result->getId() . '/' . static::$moduleInfo['images'],
        ];

        $this->assertInstanceOf(Module::class, $result);
        $this->assertEquals(static::$moduleName, $result->getId());
        $this->assertEquals(ModuleSource::TYPE, $result->getType());
        $this->assertEquals(static::$moduleInfo['rname'], $result->getName());
        $this->assertEquals(static::$moduleInfo['author'], $result->getAuthor());
        $this->assertEquals(static::$moduleInfo['mod_release'], $result->getVersion());
        $this->assertEquals($images, $result->getImages());
        $this->assertEquals(static::$moduleInfo['stability'], $result->getStability());
        $this->assertEquals(static::$moduleInfo['last_update'], $result->getLastUpdate());
        $this->assertEquals(static::$moduleInfo['release_note'], $result->getReleaseNote());
        $this->assertTrue($result->isInstalled());
        $this->assertFalse($result->isUpdated());
    }

    /**
     * @return string
     */
    public static function buildConfContent(): string
    {
        $result = '<?php';
        $moduleName = static::$moduleName;

        foreach (static::$moduleInfo as $key => $data) {
            $result .= "\n\$module_conf['{$moduleName}']['{$key}'] = '{$data}'";
        }

        return $result;
    }

    /**
     * @return string
     */
    private function getConfFilePath(): string
    {
        return $this->fs->path('/modules/' . static::$moduleName . '/' . ModuleSource::CONFIG_FILE);
    }
}
