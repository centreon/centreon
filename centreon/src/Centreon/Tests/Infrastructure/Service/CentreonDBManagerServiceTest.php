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

use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter;
use Centreon\Infrastructure\Service\CentreonDBManagerService;
use Centreon\Test\Mock\CentreonDB;
use Centreon\Tests\Resources\Mock\RepositoryMock;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\Psr11\Container as ContainerWrap;

class CentreonDBManagerServiceTest extends TestCase
{
    /** @var CentreonDBManagerService */
    protected $service;

    /** @var CentreonDB */
    protected $db1;

    /** @var CentreonDB */
    protected $db2;

    public function setUp(): void
    {
        $this->db1 = new CentreonDB('database_1');
        $this->db2 = new CentreonDB('database_2');

        $container = new Container();
        $container['configuration_db'] = $this->db1;
        $container['realtime_db'] = $this->db2;

        $this->service = new CentreonDBManagerService(new ContainerWrap($container));
    }

    public function testGetAdapter(): void
    {
        (function (): void {
            $adapter = $this->service->getAdapter('configuration_db');

            $this->assertInstanceOf(CentreonDBAdapter::class, $adapter);
            $this->assertEquals($this->db1, $adapter->getCentreonDBInstance());
        })();

        (function (): void {
            $adapter = $this->service->getAdapter('realtime_db');

            $this->assertInstanceOf(CentreonDBAdapter::class, $adapter);
            $this->assertEquals($this->db2, $adapter->getCentreonDBInstance());
        })();
    }

    public function testGetDefaultAdapter(): void
    {
        $adapter = $this->service->getDefaultAdapter();

        $this->assertInstanceOf(CentreonDBAdapter::class, $adapter);
        $this->assertEquals($this->db1, $adapter->getCentreonDBInstance());
    }

    public function testGetRepository(): void
    {
        $repository = $this->service->getRepository(RepositoryMock::class);

        $this->assertInstanceOf(RepositoryMock::class, $repository);
    }
}
