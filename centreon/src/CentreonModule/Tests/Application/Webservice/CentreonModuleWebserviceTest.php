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

namespace CentreonModule\Tests\Application\Webservice;

use Centreon\Tests\Resources\Traits;
use CentreonModule\Application\Webservice\CentreonModuleWebservice;
use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\Infrastructure\Source\ModuleSource;
use CentreonModule\ServiceProvider;
use CentreonModule\Tests\Infrastructure\Source\ModuleSourceTest;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class CentreonModuleWebserviceTest extends TestCase
{
    use Traits\WebServiceAuthorizeRestApiTrait;
    use Traits\WebServiceExecuteTestTrait;
    public const METHOD_GET_LIST = 'getList';
    public const METHOD_GET_DETAILS = 'getDetails';
    public const METHOD_POST_INSTALL = 'postInstall';
    public const METHOD_POST_UPDATE = 'postUpdate';
    public const METHOD_DELETE_REMOVE = 'deleteRemove';

    /** @var CentreonModuleWebservice|\PHPUnit\Framework\MockObject\MockObject */
    private $webservice;

    protected function setUp(): void
    {
        // dependencies
        $container = new Container();
        $container[ServiceProvider::CENTREON_MODULE] = $this->createMock(CentreonModuleService::class);
        $container[ServiceProvider::CENTREON_MODULE]
            ->method('getList')
            ->will($this->returnCallback(function () {
                    $funcArgs = func_get_args();

                    // prepare filters
                    $funcArgs[0] ??= '-';
                    $funcArgs[1] = $funcArgs[1] === true ? '1' : ($funcArgs[1] !== false ? '-' : '0');
                    $funcArgs[2] = $funcArgs[2] === true ? '1' : ($funcArgs[2] !== false ? '-' : '0');
                    $funcArgs[3] = $funcArgs[3] ? implode('|', $funcArgs[3]) : '-';
                    $name = implode(',', $funcArgs);

                    $module = new Module();
                    $module->setId(ModuleSourceTest::$moduleName);
                    $module->setName($name);
                    $module->setAuthor('');
                    $module->setVersion('');
                    $module->setType(ModuleSource::TYPE);

                    return [
                        ModuleSource::TYPE => [
                            $module,
                        ],
                    ];
            }));
        $container[ServiceProvider::CENTREON_MODULE]
            ->method('getDetail')
            ->will($this->returnCallback(function () {
                    $funcArgs = func_get_args();

                    // prepare filters
                    $funcArgs[0] ??= '-';
                    $funcArgs[1] ??= '-';

                if ($funcArgs[0] === ModuleSourceTest::$moduleNameMissing) {
                    return;
                }

                    $name = implode(',', $funcArgs);

                    $module = new Module;
                    $module->setId(ModuleSourceTest::$moduleName);
                    $module->setName($name);
                    $module->setAuthor('');
                    $module->setVersion('');
                    $module->setType(ModuleSource::TYPE);

                    return $module;
            }));
        $container[ServiceProvider::CENTREON_MODULE]
            ->method('install')
            ->will($this->returnCallback(function () {
                    $funcArgs = func_get_args();

                if ($funcArgs[0] === '' && $funcArgs[1] === '') {
                    throw new \Exception('');
                }

                    // prepare filters
                    $funcArgs[0] = $funcArgs[0] === '' ? '-' : $funcArgs[0];
                    $funcArgs[1] = $funcArgs[1] === '' ? '-' : $funcArgs[1];

                if ($funcArgs[0] === ModuleSourceTest::$moduleNameMissing) {
                    return;
                }

                    $name = implode(',', $funcArgs);

                    $module = new Module;
                    $module->setId(ModuleSourceTest::$moduleName);
                    $module->setName($name);
                    $module->setAuthor('');
                    $module->setVersion('');
                    $module->setType(ModuleSource::TYPE);

                    return $module;
            }));
        $container[ServiceProvider::CENTREON_MODULE]
            ->method('update')
            ->will($this->returnCallback(function () {
                    $funcArgs = func_get_args();

                if ($funcArgs[0] === '' && $funcArgs[1] === '') {
                    throw new \Exception('');
                }

                    // prepare filters
                    $funcArgs[0] = $funcArgs[0] === '' ? '-' : $funcArgs[0];
                    $funcArgs[1] = $funcArgs[1] === '' ? '-' : $funcArgs[1];

                if ($funcArgs[0] === ModuleSourceTest::$moduleNameMissing) {
                    return;
                }

                    $name = implode(',', $funcArgs);

                    $module = new Module;
                    $module->setId(ModuleSourceTest::$moduleName);
                    $module->setName($name);
                    $module->setAuthor('');
                    $module->setVersion('');
                    $module->setType(ModuleSource::TYPE);

                    return $module;
            }));
        $container[ServiceProvider::CENTREON_MODULE]
            ->method('remove')
            ->will($this->returnCallback(function (): void {
                    $funcArgs = func_get_args();

                if ($funcArgs[0] === '' && $funcArgs[1] === '') {
                    throw new \Exception('');
                }
            }));

        $this->webservice = $this->createPartialMock(CentreonModuleWebservice::class, [
            'loadDb',
            'loadArguments',
            'loadToken',
            'query',
        ]);

        // load dependencies
        $this->webservice->setDi($container);
        $this->fixturePath = __DIR__ . '/../../Resources/Fixture/';
    }

    public function testGetList(): void
    {
        // without applied filters
        $this->mockQuery();
        $this->executeTest(static::METHOD_GET_LIST, 'response-list-1.json');
    }

    public function testGetList2(): void
    {
        // with search, installed, updated, and selected type filter
        $this->mockQuery([
            'search' => 'test',
            'installed' => 'true',
            'updated' => 'true',
            'types' => ModuleSource::TYPE,
        ]);
        $this->executeTest(static::METHOD_GET_LIST, 'response-list-2.json');
    }

    public function testGetList3(): void
    {
        // with not installed, not updated and not selected type filter
        $this->mockQuery([
            'installed' => 'false',
            'updated' => 'false',
            'types' => [],
        ]);
        $this->executeTest(static::METHOD_GET_LIST, 'response-list-3.json');
    }

    public function testGetList4(): void
    {
        // with wrong values of installed and updated filters
        $this->mockQuery([
            'installed' => 'ture',
            'updated' => 'folse',
        ]);
        $this->executeTest(static::METHOD_GET_LIST, 'response-list-4.json');
    }

    public function testGetDetails(): void
    {
        // find module by id and type
        $this->mockQuery([
            'id' => ModuleSourceTest::$moduleName,
            'type' => ModuleSource::TYPE,
        ]);
        $this->executeTest(static::METHOD_GET_DETAILS, 'response-details-1.json');
    }

    public function testGetDetails2(): void
    {
        // try to find missing module applied filters
        $this->mockQuery([
            'id' => ModuleSourceTest::$moduleNameMissing,
            'type' => ModuleSource::TYPE,
        ]);
        $this->executeTest(static::METHOD_GET_DETAILS, 'response-details-2.json');
    }

    public function testPostInstall(): void
    {
        $this->mockQuery();
        $this->executeTest(static::METHOD_POST_INSTALL, 'response-install-1.json');
    }

    public function testPostInstall2(): void
    {
        // find module by id and type
        $this->mockQuery([
            'id' => ModuleSourceTest::$moduleName,
            'type' => ModuleSource::TYPE,
        ]);
        $this->executeTest(static::METHOD_POST_INSTALL, 'response-install-2.json');
    }

    public function testPostUpdate(): void
    {
        $this->mockQuery();
        $this->executeTest(static::METHOD_POST_UPDATE, 'response-update-1.json');
    }

    public function testPostUpdate2(): void
    {
        // find module by id and type
        $this->mockQuery([
            'id' => ModuleSourceTest::$moduleName,
            'type' => ModuleSource::TYPE,
        ]);
        $this->executeTest(static::METHOD_POST_UPDATE, 'response-update-2.json');
    }

    public function testPostRemove(): void
    {
        $this->mockQuery();
        $this->executeTest(static::METHOD_DELETE_REMOVE, 'response-remove-1.json');
    }

    public function testPostRemove2(): void
    {
        // find module by id and type
        $this->mockQuery([
            'id' => ModuleSourceTest::$moduleName,
            'type' => ModuleSource::TYPE,
        ]);
        $this->executeTest(static::METHOD_DELETE_REMOVE, 'response-remove-2.json');
    }

    public function testGetName(): void
    {
        $this->assertEquals('centreon_module', CentreonModuleWebservice::getName());
    }
}
