<?php
/**
 * Copyright 2019 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Tests\CentreonLegacy\Core\Utils;

use CentreonLegacy\Core\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Pimple\Psr11\Container;
use VirtualFileSystem\FileSystem;
use Centreon\Test\Mock\DependencyInjector\ServiceContainer;
use CentreonLegacy\ServiceProvider;
use CentreonLegacy\Core\Configuration\Configuration;
use Centreon\Test\Mock;

/**
 * @group CentreonLegacy
 * @group CentreonLegacy\Utils
 */
class UtilsTest extends TestCase
{
    /** @var FileSystem */
    public $fs;
    /** @var ServiceContainer */
    public $container;
    /** @var Utils */
    public $service
    ;
    public function setUp(): void
    {
        // mount VFS
        $this->fs = new FileSystem();
        $this->fs->createDirectory('/tmp');

        $this->container = new ServiceContainer();
        $this->container[ServiceProvider::CONFIGURATION] = $this->createMock(Configuration::class);
        $this->container['configuration_db'] = new Mock\CentreonDB;
        $this->container['configuration_db']->addResultSet("SELECT 'OK';", []);

        $this->service = new Utils(new Container($this->container));
    }

    public function tearDown(): void
    {
        $this->container->terminate();
        $this->container = null;
    }

    /**
     * @covers Utils::objectIntoArray
     */
    public function testObjectIntoArray(): void
    {
        $object = new \stdClass();
        $object->message = 'test';
        $object->subMessage = ['test'];

        $value = [
            'message' => 'test',
            'subMessage' => [
                'test',
            ],
        ];

        $result = $this->service->objectIntoArray($object);

        $this->assertEquals($result, $value);
    }

    /**
     * @covers Utils::objectIntoArray
     */
    public function testObjectIntoArrayWithSkippedKeys(): void
    {
        $object = new \stdClass();
        $object->message = 'test';
        $object->subMessage = ['test'];

        $value = [
            'message' => 'test',
        ];

        $result = $this->service->objectIntoArray($object, ['subMessage']);

        $this->assertEquals($result, $value);
    }

    /**
     * @covers Utils::objectIntoArray
     */
    public function testObjectIntoArrayWithEmptyObject(): void
    {
        $result = $this->service->objectIntoArray(new \stdClass);

        $this->assertEmpty($result);
    }

    public function testBuildPath(): void
    {
        $endPath = '.';

        $result = $this->service->buildPath($endPath);

        $this->assertStringEndsWith('www', $result);
    }

    public function testRequireConfiguration(): void
    {
        $configurationFile = '';
        $type = '';

        $result = $this->service->requireConfiguration($configurationFile, $type);

        $this->assertEmpty($result);
    }

    /**
     * Unable to find the wrapper "vfs" can't be tested
     *
     * @todo the method must be refactored
     */
    public function testExecutePhpFileWithUnexistsFile(): void
    {
        $fileName = $this->fs->path('/tmp/conf2.php');
        $this->service->executePhpFile($fileName);
        $this->expectException(\Exception::class);
    }

    public function testExecuteSqlFile(): void
    {
        $this->fs->createFile('/tmp/conf.sql', "SELECT 'OK';");
        $fileName = $this->fs->path('/tmp/conf.sql');
        $this->service->executeSqlFile($fileName);
        $this->expectException(\Exception::class);
    }

    public function testExecuteSqlFileWithWithUnexistsFileAndRealtimeDb(): void
    {
        $fileName = $this->fs->path('/tmp/conf2.sql');
        $result = null;

        try {
            $this->service->executeSqlFile($fileName, [], true);
        } catch (\Exception $ex) {
            $result = $ex;
        }

        $this->assertInstanceOf(\Exception::class, $result);
    }
}
