<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace CentreonModule\Tests;

use Centreon\Infrastructure\Service\CentreonDBManagerService;
use Centreon\Test\Mock;
use CentreonLegacy\Core\Configuration\Configuration;
use CentreonModule\Application\Webservice;
use CentreonModule\Infrastructure\Service;
use CentreonModule\ServiceProvider;
use CentreonModule\Tests\Resources\Traits\SourceDependencyTrait;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;
use Symfony\Component\Finder\Finder;

/**
 * @group CentreonModule
 * @group ServiceProvider
 */
class ServiceProviderTest extends TestCase
{
    use SourceDependencyTrait;

    /** @var Container */
    protected $container;

    /** @var ServiceProvider */
    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new ServiceProvider();
        $this->container = new Container();
        $this->container['finder'] = $this->getMockBuilder(Finder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->setUpSourceDependency($this->container);

        $this->container['configuration'] = $this->createMock(Configuration::class);

        $this->container['realtime_db'] = $this->container['configuration_db'] = new Mock\CentreonDB();
        $this->container['configuration_db']
            ->addResultSet('SELECT `name` AS `id`, `mod_release` AS `version` FROM `modules_informations`', [])
            ->addResultSet('SELECT `directory` AS `id`, `version` FROM `widget_models`', []);

        $locator = new ServiceLocator($this->container, [
            'realtime_db',
            'configuration_db',
        ]);
        $this->container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER] = new CentreonDBManagerService($locator);
        $this->container[\Centreon\ServiceProvider::CENTREON_WEBSERVICE] = new class {
            /** @var array<mixed> */
            protected $services = [];

            /**
             * @param mixed $class
             */
            public function add($class): void
            {
                $this->services[$class] = $class;
            }

            /**
             * @return array<mixed>
             */
            public function getServices(): array
            {
                /**
                 * @return array<mixed>
                 */
                return $this->services;
            }
        };

        $this->provider->register($this->container);
    }

    /**
     * @covers \CentreonModule\ServiceProvider::register
     */
    public function testCheckServicesByList(): void
    {
        $checkList = [
            ServiceProvider::CENTREON_MODULE => Service\CentreonModuleService::class,
        ];

        $checkListWebservices = [
            Webservice\CentreonModuleWebservice::class,
            Webservice\CentreonModulesWebservice::class,
        ];

        // check list of services
        foreach ($checkList as $serviceName => $className) {
            $this->assertTrue($this->container->offsetExists($serviceName));

            $service = $this->container->offsetGet($serviceName);

            $this->assertInstanceOf($className, $service);
        }

        // check webservices
        $webservices = $this->container[\Centreon\ServiceProvider::CENTREON_WEBSERVICE]->getServices();
        foreach ($checkListWebservices as $webservice) {
            $this->assertArrayHasKey($webservice, $webservices);
        }
    }

    /**
     * @covers \CentreonModule\ServiceProvider::order
     */
    public function testOrder(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->provider::order());
        $this->assertLessThanOrEqual(20, $this->provider::order());
    }
}
