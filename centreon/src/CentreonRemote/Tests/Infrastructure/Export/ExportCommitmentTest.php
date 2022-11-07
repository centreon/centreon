<?php
<<<<<<< HEAD

=======
>>>>>>> centreon/dev-21.10.x
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
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
 *
 * For more information : contact@centreon.com
 *
 */

namespace CentreonRemote\Tests\Infrastructure\Export;

use PHPUnit\Framework\TestCase;
use CentreonRemote\Infrastructure\Export\ExportCommitment;
use CentreonRemote\Infrastructure\Export\ExportParserYaml;
use CentreonRemote\Infrastructure\Export\ExportParserInterface;

/**
 * @group CentreonRemote
 */
class ExportCommitmentTest extends TestCase
{
<<<<<<< HEAD
    /**
     * @var ExportCommitment
     */
    protected $commitment;

    /**
     * @var int
     */
    protected $remote = 1;

    /**
     * @var int[]
     */
    protected $pollers = [2, 3];

    /**
     * @var array<int,string>
     */
    protected $meta = [''];

    /**
     * @var string
     */
    protected $path = '/tmp';


    /**
     * @var array<mixed>
     */
=======

    protected $commitment;
    protected $remote = 1;
    protected $pollers = [2, 3];
    protected $meta = [
        '',
    ];
    protected $path = '/tmp';
>>>>>>> centreon/dev-21.10.x
    protected $exporters = [];

    protected function setUp(): void
    {
        $parser = $this->getMockBuilder(ExportParserYaml::class)
            ->onlyMethods(['parse', 'dump'])
            ->getMock();

        $this->commitment = new ExportCommitment(
            $this->remote,
            $this->pollers,
            $this->meta,
            $parser,
            $this->path,
            $this->exporters
        );
    }

    /**
     * @covers \CentreonRemote\Infrastructure\Export\ExportCommitment::getRemote
     */
<<<<<<< HEAD
    public function testGetRemote(): void
=======
    public function testGetRemote()
>>>>>>> centreon/dev-21.10.x
    {
        $this->assertEquals($this->remote, $this->commitment->getRemote());
    }

    /**
     * @covers \CentreonRemote\Infrastructure\Export\ExportCommitment::getPollers
     */
<<<<<<< HEAD
    public function testGetPollers(): void
=======
    public function testGetPollers()
>>>>>>> centreon/dev-21.10.x
    {
        $result = array_merge($this->pollers, [$this->remote]);
        $this->assertEquals($result, $this->commitment->getPollers());
    }

    /**
     * @covers \CentreonRemote\Infrastructure\Export\ExportCommitment::getMeta
     */
<<<<<<< HEAD
    public function testGetMeta(): void
=======
    public function testGetMeta()
>>>>>>> centreon/dev-21.10.x
    {
        $this->assertEquals($this->meta, $this->commitment->getMeta());
    }

    /**
     * @covers \CentreonRemote\Infrastructure\Export\ExportCommitment::getPath
     */
<<<<<<< HEAD
    public function testGetPath(): void
=======
    public function testGetPath()
>>>>>>> centreon/dev-21.10.x
    {
        $this->assertEquals($this->path, $this->commitment->getPath());
    }

    /**
     * @covers \CentreonRemote\Infrastructure\Export\ExportCommitment::getExporters
     */
<<<<<<< HEAD
    public function testGetExporters(): void
=======
    public function testGetExporters()
>>>>>>> centreon/dev-21.10.x
    {
        $this->assertEquals($this->exporters, $this->commitment->getExporters());
    }

    /**
     * @covers \CentreonRemote\Infrastructure\Export\ExportCommitment::getFilePermission
     */
<<<<<<< HEAD
    public function testGetFilePermission(): void
=======
    public function testGetFilePermission()
>>>>>>> centreon/dev-21.10.x
    {
        $this->assertEquals(0775, $this->commitment->getFilePermission());
    }

    /**
     * @covers \CentreonRemote\Infrastructure\Export\ExportCommitment::getParser
     */
<<<<<<< HEAD
    public function testGetParser(): void
=======
    public function testGetParser()
>>>>>>> centreon/dev-21.10.x
    {
        $this->assertInstanceOf(ExportParserInterface::class, $this->commitment->getParser());
    }
}
