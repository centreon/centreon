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

use CentreonModule\Application\Webservice\CentreonModulesWebservice;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class CentreonModulesWebserviceTest extends TestCase
{
    /** @var array<string,mixed> */
    public static $sqlQueriesWitoutData = [
        'SELECT * FROM modules_informations ' => [],
    ];

    /** @var string[][][] */
    public static $sqlQueries = [
        'SELECT * FROM modules_informations ' => [
            [
                'id' => '1',
                'name' => 'centreon-bam-server',
                'rname' => 'centreon-bam-server',
                'mod_release' => '',
                'is_removable' => '1',
                'infos' => '',
                'author' => '',
                'svc_tools' => '0',
                'host_tools' => '0',
            ],
        ],
    ];

    /** @var CentreonModulesWebservice|\PHPUnit\Framework\MockObject\MockObject */
    private $webservice;

    protected function setUp(): void
    {
        $this->webservice = $this->createPartialMock(CentreonModulesWebservice::class, [
            'loadDb',
            'loadArguments',
            'loadToken',
        ]);
    }

    /**
     * @covers \CentreonModule\Application\Webservice\CentreonModulesWebservice::postGetBamModuleInfo
     */
    public function testPostGetBamModuleInfoWithoutModule(): void
    {
        // dependencies
        $container = new Container();
        $container[\CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION] = $this
            ->getMockBuilder(\CentreonLegacy\Core\Module\Information::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getList',
            ])
            ->getMock();

        $container[\CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION]
            ->method('getList')
            ->will($this->returnCallback(fn() => [
                'centreon-bam-server' => [
                    'is_installed' => false,
                ],
            ]));

        $this->webservice->setDi($container);

        $result = $this->webservice->postGetBamModuleInfo();
        $this->assertArrayHasKey('enabled', $result);
        $this->assertFalse($result['enabled']);
    }

    /**
     * @covers \CentreonModule\Application\Webservice\CentreonModulesWebservice::postGetBamModuleInfo
     */
    public function testPostGetBamModuleInfoWithModule(): void
    {
        $container = new Container();
        $container[\CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION] = $this
            ->getMockBuilder(\CentreonLegacy\Core\Module\Information::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getList',
            ])
            ->getMock();
        $container[\CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION]
            ->method('getList')
            ->will($this->returnCallback(fn() => [
                'centreon-bam-server' => [
                    'is_installed' => true,
                ],
            ]));

        $this->webservice->setDi($container);

        $result = $this->webservice->postGetBamModuleInfo();
        $this->assertArrayHasKey('enabled', $result);
        $this->assertTrue($result['enabled']);
    }

    public function testAuthorize(): void
    {
        $result = $this->webservice->authorize(null, null);
        $this->assertTrue($result);
    }

    public function testGetName(): void
    {
        $this->assertEquals('centreon_modules_webservice', CentreonModulesWebservice::getName());
    }
}
