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

namespace CentreonModule\Tests\Infrastructure\Service;

use Centreon\Test\Mock;
use Centreon\Test\Traits\TestCaseExtensionTrait;
use CentreonLegacy\Core\Configuration\Configuration;
use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\Infrastructure\Source;
use CentreonModule\Tests\Infrastructure\Source\ModuleSourceTest;
use CentreonModule\Tests\Infrastructure\Source\WidgetSourceTest;
use CentreonModule\Tests\Resources\Traits\SourceDependencyTrait;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\Psr11\Container as ContainerWrap;

class CentreonModuleServiceTest extends TestCase
{
    use TestCaseExtensionTrait;
    use SourceDependencyTrait;

    /** @var CentreonModuleService|\PHPUnit\Framework\MockObject\MockObject */
    private $service;

    protected function setUp(): void
    {
        $this->service = $this->getMockBuilder(CentreonModuleService::class)
            ->onlyMethods([
                'initSources',
            ])
            ->setConstructorArgs([new ContainerWrap(new Container())])
            ->getMock();

        $sources = [];
        $sourcesTypes = [
            Source\ModuleSource::TYPE => Source\ModuleSource::class,
            Source\WidgetSource::TYPE => Source\WidgetSource::class,
        ];

        foreach ($sourcesTypes as $type => $class) {
            $sources[$type] = $this
                ->getMockBuilder($class)
                ->disableOriginalConstructor()
                ->onlyMethods([
                    'getList',
                    'getDetail',
                    'install',
                    'update',
                    'remove',
                ])
                ->getMock();

            $sources[$type]
                ->method('getList')
                ->will($this->returnCallback(fn() => [$type]));
            $sources[$type]
                ->method('getDetail')
                ->will($this->returnCallback(function () use ($type) {
                    $entity = new Module();
                    $entity->setType($type);
                    $entity->setName($type);
                    $entity->setKeywords('test,module,lorem');
                    $entity->setInstalled(true);
                    $entity->setUpdated(false);

                    return $entity;
                }));
            $sources[$type]
                ->method('install')
                ->will($this->returnCallback(function ($id) use ($type) {
                    $entity = new Module();
                    $entity->setId($id);
                    $entity->setType($type);
                    $entity->setName($type);
                    $entity->setKeywords('test,module,lorem');
                    $entity->setInstalled(true);
                    $entity->setUpdated(false);

                    return $entity;
                }));
            $sources[$type]
                ->method('update')
                ->will($this->returnCallback(function ($id) use ($type) {
                    $entity = new Module();
                    $entity->setId($id);
                    $entity->setType($type);
                    $entity->setName($type);
                    $entity->setKeywords('test,module,lorem');
                    $entity->setInstalled(true);
                    $entity->setUpdated(false);

                    return $entity;
                }));
            $sources[$type]
                ->method('remove')
                ->will($this->returnCallback(function ($id): void {
                    if ($id === ModuleSourceTest::$moduleName) {
                        throw new \Exception('Removed');
                    }
                }));
        }

        // load sources
        $this->setProtectedProperty($this->service, 'sources', $sources);
    }

    public function testGetList(): void
    {
        (function (): void {
            $result = $this->service->getList();

            $this->assertArrayHasKey(Source\ModuleSource::TYPE, $result);
            $this->assertArrayHasKey(Source\WidgetSource::TYPE, $result);
        })();

        (function (): void {
            $result = $this->service->getList(null, null, null, [Source\ModuleSource::TYPE]);

            $this->assertArrayHasKey(Source\ModuleSource::TYPE, $result);
            $this->assertArrayNotHasKey(Source\WidgetSource::TYPE, $result);
        })();

        (function (): void {
            $result = $this->service->getList(null, null, null, ['missing-type']);

            $this->assertArrayNotHasKey(Source\ModuleSource::TYPE, $result);
            $this->assertArrayNotHasKey(Source\WidgetSource::TYPE, $result);
        })();
    }

    public function testGetDetails(): void
    {
        (function (): void {
            $result = $this->service->getDetail('test-module', Source\ModuleSource::TYPE);

            $this->assertInstanceOf(Module::class, $result);
            $this->assertEquals(Source\ModuleSource::TYPE, $result->getType());
        })();

        (function (): void {
            $result = $this->service->getDetail('test-module', 'missing-type');

            $this->assertEquals(null, $result);
        })();
    }

    public function testInstall(): void
    {
        $result = $this->service->install(ModuleSourceTest::$moduleName, Source\ModuleSource::TYPE);

        $this->assertInstanceOf(Module::class, $result);
    }

    /**
     * @covers \CentreonModule\Infrastructure\Service\CentreonModuleService::install
     */
    public function testInstallMissingType(): void
    {
        $result = $this->service->install(ModuleSourceTest::$moduleName, 'missing-type');

        $this->assertNull($result);
    }

    public function testUpdate(): void
    {
        $result = $this->service->update(ModuleSourceTest::$moduleName, Source\ModuleSource::TYPE);

        $this->assertInstanceOf(Module::class, $result);
    }

    /**
     * @covers \CentreonModule\Infrastructure\Service\CentreonModuleService::update
     */
    public function testUpdateMissingType(): void
    {
        $result = $this->service->update(ModuleSourceTest::$moduleName, 'missing-type');

        $this->assertNull($result);
    }

    /**
     * @throws \Exception
     */
    public function testRemove(): void
    {
        (function (): void {
            $result = null;

            try {
                $result = $this->service->remove(ModuleSourceTest::$moduleName, Source\ModuleSource::TYPE);
            } catch (\Exception $ex) {
                $result = $ex->getMessage();
            }

            $this->assertEquals('Removed', $result);
        })();

        $result = $this->service->remove(ModuleSourceTest::$moduleNameMissing, Source\ModuleSource::TYPE);
        $this->assertTrue($result);
    }

    /**
     * @covers \CentreonModule\Infrastructure\Service\CentreonModuleService::remove
     */
    public function testRemoveMissingType(): void
    {
        $result = $this->service->remove(ModuleSourceTest::$moduleName, 'missing-type');

        $this->assertNull($result);
    }

    /**
     * @covers \CentreonModule\Infrastructure\Service\CentreonModuleService::initSources
     */
    public function testInitSources(): void
    {
        $container = new Container();
        $container['finder'] = null;
        $container['configuration'] = $this->createMock(Configuration::class);
        $container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER] = new Mock\CentreonDBManagerService();

        // Data sets
        $queries = array_merge(ModuleSourceTest::$sqlQueryVsData, WidgetSourceTest::$sqlQueryVsData);
        foreach ($queries as $key => $result) {
            $container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]->addResultSet($key, $result);
        }

        $this->setUpSourceDependency($container);

        $service = new CentreonModuleService(new ContainerWrap($container));

        $sources = $this->getProtectedProperty($service, 'sources');

        $this->assertArrayHasKey(Source\ModuleSource::TYPE, $sources);
        $this->assertArrayHasKey(Source\WidgetSource::TYPE, $sources);

        $this->assertInstanceOf(Source\ModuleSource::class, $sources[Source\ModuleSource::TYPE]);
        $this->assertInstanceOf(Source\WidgetSource::class, $sources[Source\WidgetSource::TYPE]);
    }

    public function testSortList(): void
    {
        $service = $this->createMock(CentreonModuleService::class);

        $value = [
            'B-1-0',
            'C-1-0',
            'D-0-0',
            'F-0-0',
            'A-1-1',
            'B-1-1',
        ];
        $list = [
            (function () {
                $entity = new Module();
                $entity->setName('B');
                $entity->setInstalled(true);
                $entity->setUpdated(true);

                return $entity;
            })(),
            (function () {
                $entity = new Module();
                $entity->setName('A');
                $entity->setInstalled(true);
                $entity->setUpdated(true);

                return $entity;
            })(),
            (function () {
                $entity = new Module();
                $entity->setName('B');
                $entity->setInstalled(true);
                $entity->setUpdated(false);

                return $entity;
            })(),
            (function () {
                $entity = new Module();
                $entity->setName('C');
                $entity->setInstalled(true);
                $entity->setUpdated(false);

                return $entity;
            })(),
            (function () {
                $entity = new Module();
                $entity->setName('D');
                $entity->setInstalled(false);
                $entity->setUpdated(false);

                return $entity;
            })(),
            (function () {
                $entity = new Module();
                $entity->setName('F');
                $entity->setInstalled(false);
                $entity->setUpdated(false);

                return $entity;
            })(),
        ];
        $list = $this->invokeMethod($service, 'sortList', [$list]);

        $result = [];
        foreach ($list as $entity) {
            $result[] = $entity->getName() . '-' . (int) $entity->isInstalled() . '-' . (int) $entity->isUpdated();
        }

        $this->assertEquals($value, $result);
    }
}
