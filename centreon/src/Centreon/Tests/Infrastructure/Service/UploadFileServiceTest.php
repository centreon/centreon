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

namespace Centreon\Tests\Infrastructure\Service;

use Centreon\Infrastructure\FileManager\File;
use Centreon\Infrastructure\Service\UploadFileService;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\Psr11\Container as ContainerWrap;

class UploadFileServiceTest extends TestCase
{
    /** @var array<string, array<string, array<int,string>|string>> */
    private $filesRequest;

    /** @var UploadFileService */
    private $service;

    public function setUp(): void
    {
        $container = new ContainerWrap(new Container());
        $this->filesRequest = [
            'field1' => [
                'name' => 'A.txt',
                'type' => 'B',
                'tmp_name' => 'C',
                'error' => 'D',
                'size' => 'E',
            ],
            'field2' => [
                'name' => [
                    'A1.svg',
                    'A2.exe',
                ],
                'type' => [
                    'B1',
                    'B2',
                ],
                'tmp_name' => [
                    'C1',
                    'C2',
                ],
                'error' => [
                    'D1',
                    'D2',
                ],
                'size' => [
                    'E1',
                    'E2',
                ],
            ],
        ];

        $this->service = new UploadFileService($container, $this->filesRequest);
    }

    public function testGetFiles(): void
    {
        (function (): void {
            $result = $this->service->getFiles('field1');

            $this->assertCount(1, $result);
            $this->assertInstanceOf(File::class, $result[0]);
            $this->assertEquals($this->filesRequest['field1']['name'], $result[0]->getName());
        })();

        (function (): void {
            $result = $this->service->getFiles('field2', ['svg']);

            $this->assertCount(1, $result);
            $this->assertEquals('svg', $result[0]->getExtension());
        })();
    }

    public function testPrepare(): void
    {
        (function (): void {
            $result = $this->service->prepare('field1');

            $this->assertCount(1, $result);
        })();

        (function (): void {
            $result = $this->service->prepare('field2');

            $this->assertCount(2, $result);

            $value = [
                'name' => 'A1.svg',
                'type' => 'B1',
                'tmp_name' => 'C1',
                'error' => 'D1',
                'size' => 'E1',
            ];
            $this->assertEquals($value, $result[0]);
        })();

        (function (): void {
            $result = $this->service->prepare('field3');

            $this->assertEquals([], $result);
        })();
    }
}
