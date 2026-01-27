<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Tests\CentreonLegacy\Core\Utils;

use Centreon\Test\Mock;
use Centreon\Test\Mock\DependencyInjector\ServiceContainer;
use CentreonLegacy\Core\Configuration\Configuration;
use CentreonLegacy\Core\Utils\Utils;
use CentreonLegacy\ServiceProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Psr11\Container;
use VirtualFileSystem\FileSystem;
use VirtualFileSystem\Structure\File;

/**
 * @group CentreonLegacy
 * @group CentreonLegacy\Utils
 */
class UtilsTest extends TestCase
{
    /** @var FileSystem */
    public $fileSystem;

    /** @var ServiceContainer */
    public $container;

    /** @var Utils */
    public $service;

    public function setUp(): void
    {
        // mount VFS
        $this->fileSystem = new FileSystem();
        $this->fileSystem->createDirectory('/tmp');

        $this->container = new ServiceContainer();
        $this->container[ServiceProvider::CONFIGURATION] = $this->createMock(Configuration::class);
        $this->container['configuration_db'] = new Mock\CentreonDB();
        $this->container['configuration_db']->addResultSet("SELECT 'OK';", []);

        $this->service = new Utils(new Container($this->container));
    }

    public function tearDown(): void
    {
        $this->container->terminate();
        $this->container = null;
    }

    /**
     * @covers \CentreonLegacy\Core\Utils\Utils::objectIntoArray
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
     * @covers \CentreonLegacy\Core\Utils\Utils::objectIntoArray
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
     * @covers \CentreonLegacy\Core\Utils\Utils::objectIntoArray
     */
    public function testObjectIntoArrayWithEmptyObject(): void
    {
        $result = $this->service->objectIntoArray(new \stdClass());

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
        $fileName = $this->fileSystem->path('/tmp/conf2.php');
        $this->expectException(\Exception::class);
        $this->service->executePhpFile($fileName);
    }

    public function testExecuteSqlFile(): void
    {
        $file = $this->fileSystem->createFile('/tmp/conf.sql', "SELECT 'OK';");
        $this->assertInstanceOf(File::class, $file);
        $fileName = $this->fileSystem->path('/tmp/conf.sql');
        $this->assertIsString($fileName);
        try {
            $isException = false;
            $this->service->executeSqlFile($fileName);
        } catch (\Exception) {
            $isException = true;
        }
        $this->assertFalse($isException);
    }

    public function testExecuteSqlFileWithWithUnexistsFileAndRealtimeDb(): void
    {
        $fileName = $this->fileSystem->path('/tmp/conf2.sql');
        $result = null;

        try {
            $this->service->executeSqlFile($fileName, [], true);
        } catch (\Exception $ex) {
            $result = $ex;
        }

        $this->assertInstanceOf(\Exception::class, $result);
    }
}
