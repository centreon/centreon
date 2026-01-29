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

namespace Centreon\Tests\Infrastructure\Provider;

use Centreon\Infrastructure\Provider\AutoloadServiceProvider;
use Centreon\Tests\Resources\CheckPoint;
use Centreon\Tests\Resources\Mock\ServiceProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class AutoloadServiceProviderTest extends TestCase
{
    /** @var CheckPoint */
    protected $checkPoint;

    /** @var Finder&MockObject */
    protected $finder;

    public function setUp(): void
    {
        $this->checkPoint = (new CheckPoint())
            ->add('finder.files')
            ->add('finder.name')
            ->add('finder.depth')
            ->add('finder.in');

        $this->finder = $this->createMock(Finder::class);
        $this->finder->method('files')
            ->will($this->returnCallback(function () {
                $this->checkPoint->mark('finder.files');

                return $this->finder;
            }));
        $this->finder->method('name')
            ->will($this->returnCallback(function ($name) {
                $this->checkPoint->mark('finder.name');

                $this->assertEquals('ServiceProvider.php', $name);

                return $this->finder;
            }));
        $this->finder->method('depth')
            ->will($this->returnCallback(function () {
                $this->checkPoint->mark('finder.depth');

                return $this->finder;
            }));
        $this->finder->method('in')
            ->will($this->returnCallback(function () {
                $this->checkPoint->mark('finder.in');

                return $this->finder;
            }));
    }

    public function testRegister(): void
    {
        $this->checkPoint
            ->add('finder.getIterator')
            ->add('finder.getIterator.getRelativePath1')
            ->add('finder.getIterator.getRelativePath2');

        $this->finder->method('getIterator')
            ->will($this->returnCallback(function () {
                $this->checkPoint->mark('finder.getIterator');

                $fileInfo = $this->createMock(SplFileInfo::class);
                $fileInfo->method('getRelativePath')
                    ->will($this->returnCallback(function () {
                        $this->checkPoint->mark('finder.getIterator.getRelativePath1');

                        return 'Centreon\\Tests\\Resources\\Mock';
                    }));

                $fileInfo2 = $this->createMock(SplFileInfo::class);
                $fileInfo2->method('getRelativePath')
                    ->will($this->returnCallback(function () {
                        $this->checkPoint->mark('finder.getIterator.getRelativePath2');

                        return 'Centreon\\Tests\\Resources\\Mock\\NonExistent';
                    }));

                return new \ArrayIterator([
                    $fileInfo,
                    $fileInfo2,
                ]);
            }));

        $container = new Container();
        $container['finder'] = $this->finder;

        AutoloadServiceProvider::register($container);

        $this->checkPoint->assert($this);

        $this->assertArrayHasKey(ServiceProvider::DUMMY_SERVICE, $container);
        $this->assertTrue($container[ServiceProvider::DUMMY_SERVICE]);
    }

    /**
     * Test service register with duplicated files loaded
     */
    public function testRegisterWithException(): void
    {
        $this->finder->method('getIterator')
            ->will($this->returnCallback(function () {
                $fileInfo = $this->createMock(SplFileInfo::class);
                $fileInfo->method('getRelativePath')
                    ->willReturn('Centreon\\Tests\\Resources\\Mock');

                return new \ArrayIterator([
                    $fileInfo,
                    $fileInfo,
                ]);
            }));

        $container = new Container();
        $container['finder'] = $this->finder;

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(AutoloadServiceProvider::ERR_TWICE_LOADED);

        AutoloadServiceProvider::register($container);
    }
}
