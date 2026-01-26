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

namespace Centreon\Tests\Application\Webservice;

use Centreon\Application\Webservice\TopologyWebservice;
use Centreon\Domain\Repository\TopologyRepository;
use Centreon\ServiceProvider;
use Centreon\Test\Mock\CentreonDB;
use Centreon\Test\Traits\TestCaseExtensionTrait;
use Centreon\Tests\Resources\CheckPoint;
use Centreon\Tests\Resources\Dependency;
use Centreon\Tests\Resources\Traits;
use CentreonUser;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

/**
 * @group Centreon
 * @group Webservice
 */
class TopologyWebserviceTest extends TestCase
{
    use Dependency\CentreonDbManagerDependencyTrait;
    use TestCaseExtensionTrait;
    use Traits\WebServiceAuthorizePublicTrait;

    /** @var Container */
    public $container;

    /** @var CentreonDB */
    public $db;

    /** @var TopologyWebservice|(TopologyWebservice&object&\PHPUnit\Framework\MockObject\MockObject)|(TopologyWebservice&\PHPUnit\Framework\MockObject\MockObject)|(object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject */
    public $webservice;

    protected function setUp(): void
    {
        // dependencies
        $this->container = new Container();
        $this->db = new CentreonDB();

        $this->setUpCentreonDbManager($this->container);

        $this->webservice = $this->createPartialMock(TopologyWebservice::class, [
            'loadDb',
            'loadArguments',
            'loadToken',
            'query',
        ]);

        $this->setProtectedProperty($this->webservice, 'pearDB', $this->db);

        // load dependencies
        $this->webservice->setDi($this->container);
    }

    public function testGetName(): void
    {
        $this->assertEquals('centreon_topology', TopologyWebservice::getName());
    }

    public function testDependencies(): void
    {
        $this->assertEquals([
            ServiceProvider::CENTREON_DB_MANAGER,
        ], $this->webservice::dependencies());
    }

    public function testGetGetTopologyByPage(): void
    {
        $marker = __METHOD__;
        $checkpoint = (new CheckPoint())
            ->add($marker);

        $_GET['topology_page'] = 1;
        $this->db->addResultSet(
            'SELECT * FROM `topology` WHERE `topology_page` = :id',
            [['k']],
            null,
            function () use ($checkpoint, $marker): void {
                $checkpoint->mark($marker);
            }
        );

        $this->webservice->getGetTopologyByPage();
        $checkpoint->assert($this);
    }

    public function testGetGetTopologyByPageWithoutResult(): void
    {
        $_GET['topology_page'] = 1;
        $this->db->addResultSet('SELECT * FROM `topology` WHERE `topology_page` = :id', []);

        $this->expectException(\RestBadRequestException::class);

        $this->webservice->getGetTopologyByPage();
    }

    public function testGetGetTopologyByPageWithoutTopologyPage(): void
    {
        if (isset($_GET['topology_page'])) {
            unset($_GET['topology_page']);
        }

        $this->expectException(\RestBadRequestException::class);

        $this->webservice->getGetTopologyByPage();
    }

    public function testGetNavigationListWithoutAuth(): void
    {
        $this->container[ServiceProvider::CENTREON_USER] = null;

        $this->expectException(\RestBadRequestException::class);

        $this->webservice->getNavigationList();
    }

    public function testGetNavigationList(): void
    {
        $calledGetTopologyList = false;
        $repository = $this->createMock(TopologyRepository::class);
        $repository->method('getTopologyList')
            ->will($this->returnCallback(function () use (&$calledGetTopologyList) {
                $calledGetTopologyList = true;

                return [];
            }));

        $centreonAclMock = $this->createMock(\CentreonACL::class);
        $centreonAclMock->method('getTopology')
            ->will($this->returnCallback(function () {
                return [];
            }));

        $userMock = $this->createMock(CentreonUser::class);
        $userMock->access = $centreonAclMock;

        // register mocked repository in DB manager
        $this->container[ServiceProvider::CENTREON_DB_MANAGER]
            ->addRepositoryMock(TopologyRepository::class, $repository);

        // mock user service
        $this->container[ServiceProvider::CENTREON_USER] = $userMock;
        $this->container[ServiceProvider::YML_CONFIG] = [
            'navigation' => [],
        ];

        $result = $this->webservice->getNavigationList();
        $this->assertTrue($calledGetTopologyList);
    }

    public function testGetNavigationListWithReact(): void
    {
        $_GET['reactOnly'] = 1;

        $calledGetTopologyList = false;
        $repository = $this->createMock(TopologyRepository::class);
        $repository->method('getTopologyList')
            ->will($this->returnCallback(function () use (&$calledGetTopologyList) {
                $calledGetTopologyList = true;

                return [];
            }));

        $centreonAclMock = $this->createMock(\CentreonACL::class);
        $centreonAclMock->method('getTopology')
            ->will($this->returnCallback(function () {
                return [];
            }));

        $userMock = $this->createMock(CentreonUser::class);
        $userMock->access = $centreonAclMock;

        // register mocked repository in DB manager
        $this->container[ServiceProvider::CENTREON_DB_MANAGER]
            ->addRepositoryMock(TopologyRepository::class, $repository);

        // mock user service
        $this->container[ServiceProvider::CENTREON_USER] = $userMock;
        $this->container[ServiceProvider::YML_CONFIG] = [
            'navigation' => [],
        ];

        $this->webservice->getNavigationList();
        $this->assertTrue($calledGetTopologyList);
    }
}
