<?php
/**
 * Copyright 2016 Centreon
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
namespace CentreonLegacy\Core\Widget;

use Pimple\Container;
use Centreon\Test\Mock\DependencyInjector\ServiceContainer;
use CentreonLegacy\Core\Widget;
use CentreonLegacy\ServiceProvider;

/**
 * Description of factoryTest
 *
 * @author lionel
 */
class FactoryTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $this->container = new ServiceContainer();

        $this->container[ServiceProvider::CENTREON_LEGACY_WIDGET_INFORMATION] = $this
            ->getMockBuilder(Widget\Information::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->container[ServiceProvider::CENTREON_LEGACY_WIDGET_INSTALLER] = fn(Container $container) => fn($widgetDirectory) => $this->getMockBuilder(Widget\Installer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->container[ServiceProvider::CENTREON_LEGACY_WIDGET_UPGRADER] = fn(Container $container) => fn($widgetDirectory) => $this->getMockBuilder(Widget\Upgrader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->container[ServiceProvider::CENTREON_LEGACY_WIDGET_REMOVER] = fn(Container $container) => fn($widgetDirectory) => $this->getMockBuilder(Widget\Remover::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function tearDown(): void
    {
        $this->container->terminate();
        $this->container = null;
    }

    public function testNewInformation(): void
    {
        $factory = new Widget\Factory($this->container);
        $this->assertInstanceOf(Widget\Information::class, $factory->newInformation());
    }

    public function testNewInstaller(): void
    {
        $factory = new Widget\Factory($this->container);
        $this->assertInstanceOf(Widget\Installer::class, $factory->newInstaller('MyWidget'));
    }
    
    public function testNewUpgrader(): void
    {
        $factory = new Widget\Factory($this->container);
        $this->assertInstanceOf(Widget\Upgrader::class, $factory->newUpgrader('MyWidget'));
    }
    
    public function testNewRemover(): void
    {
        $factory = new Widget\Factory($this->container);
        $this->assertInstanceOf(Widget\Remover::class, $factory->newRemover('MyWidget'));
    }
}
