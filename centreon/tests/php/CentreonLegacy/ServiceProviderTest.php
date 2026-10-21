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

namespace CentreonLegacy;

use Centreon\Test\Mock;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

/**
 * @group CentreonLegacy
 * @group ServiceProvider
 */
class ServiceProviderTest extends TestCase
{
    protected $container;

    protected $provider;

    protected function setUp(): void
    {
        global $conf_centreon, $centreon_path;
        $conf_centreon = [];
        $centreon_path = '_PATH_';

        $this->provider = new ServiceProvider();
        $this->container = new Container();
        $this->container['configuration_db'] = new Mock\CentreonDB();

        $this->provider->register($this->container);
    }

    /**
     * @covers \CentreonLegacy\ServiceProvider::register
     * @covers \CentreonLegacy\ServiceProvider::registerConfiguration
     * @covers \CentreonLegacy\ServiceProvider::registerModule
     * @covers \CentreonLegacy\ServiceProvider::registerWidget
     */
    public function testCheckServicesByList(): void
    {
        $checkList = [
            ServiceProvider::CONFIGURATION => Core\Configuration\Configuration::class,
            ServiceProvider::CENTREON_LEGACY_UTILS => Core\Utils\Utils::class,
            ServiceProvider::CENTREON_LEGACY_MODULE_HEALTHCHECK => Core\Module\Healthcheck::class,
            ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION => Core\Module\Information::class,
            ServiceProvider::CENTREON_LEGACY_MODULE_LICENSE => Core\Module\License::class,
            ServiceProvider::CENTREON_LEGACY_LICENSE => Core\Module\License::class, // alias
            ServiceProvider::CENTREON_LEGACY_WIDGET_INFORMATION => Core\Widget\Information::class,
            ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER => Core\Migration\MigrationLauncher::class,
            ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER => Core\Cache\CacheClearer::class,
            ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS => Core\Module\UpgradeProgress::class,
        ];

        // check list of services
        foreach ($checkList as $serviceName => $className) {
            $this->assertTrue($this->container->offsetExists($serviceName));

            $service = $this->container->offsetGet($serviceName);

            $this->assertInstanceOf($className, $service);
        }
    }

    /**
     * A module object only reaches the services its locator lists: a missing one fails at install or upgrade time.
     */
    public function testModuleObjectsReachTheServicesTheyUse(): void
    {
        // Registered by the Centreon service provider.
        $this->container['filesystem'] = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $this->container['finder'] = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_UTILS] = $this->createMock(Core\Utils\Utils::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION] = $this->createMock(Core\Module\Information::class);

        $checkList = [
            ServiceProvider::CENTREON_LEGACY_MODULE_INSTALLER => [
                ['mod-name'],
                ['filesystem', ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER],
            ],
            ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADER => [
                ['mod-name', 1],
                [
                    'finder',
                    'filesystem',
                    'configuration_db',
                    ServiceProvider::CENTREON_LEGACY_MIGRATION_LAUNCHER,
                    ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER,
                    ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS,
                ],
            ],
            ServiceProvider::CENTREON_LEGACY_MODULE_REMOVER => [
                ['mod-name', 1],
                ['filesystem', 'configuration_db', ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS],
            ],
        ];

        foreach ($checkList as $serviceName => [$arguments, $usedServices]) {
            $module = $this->container[$serviceName](...$arguments);
            $services = (new \ReflectionProperty(Core\Module\Module::class, 'services'))->getValue($module);

            foreach ($usedServices as $usedService) {
                $this->assertTrue($services->has($usedService), \sprintf('%s cannot reach %s', $serviceName, $usedService));
            }
        }

        // Shared: suspending the clears of a batch must reach every module of it.
        $upgrader = $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADER]('mod-name', 1);
        $this->assertSame(
            $this->container[ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER],
            (new \ReflectionProperty(Core\Module\Module::class, 'services'))->getValue($upgrader)->get(ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER),
        );
    }

    /**
     * Check CentreonRestHttp service
     */
    public function testRegisterRestHttp(): void
    {
        $this->assertTrue($this->container->offsetExists(ServiceProvider::CENTREON_REST_HTTP));
        $service = $this->container->offsetGet(ServiceProvider::CENTREON_REST_HTTP);

        $this->assertInstanceOf(\Closure::class, $service);
    }

    /**
     * @covers \CentreonLegacy\ServiceProvider::registerModule
     * @covers \CentreonLegacy\ServiceProvider::registerWidget
     */
    public function testCheckServicesByModuleList(): void
    {
        // mock some of services
        $this->container[ServiceProvider::CENTREON_LEGACY_UTILS] = $this->createMock(Core\Utils\Utils::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION] = $this->createMock(Core\Module\Information::class);
        $this->container[ServiceProvider::CENTREON_LEGACY_WIDGET_INFORMATION] = $this->createMock(Core\Widget\Information::class);

        $moduleName = 'mod-name';
        $moduleId = 'mod-id';

        $checkList = [
            ServiceProvider::CENTREON_LEGACY_MODULE_INSTALLER => [
                'class' => Core\Module\Installer::class,
                'attr' => [
                    $moduleName,
                ],
            ],
            ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADER => [
                'class' => Core\Module\Upgrader::class,
                'attr' => [
                    $moduleName,
                    $moduleId,
                ],
            ],
            ServiceProvider::CENTREON_LEGACY_MODULE_REMOVER => [
                'class' => Core\Module\Remover::class,
                'attr' => [
                    $moduleName,
                    $moduleId,
                ],
            ],
            ServiceProvider::CENTREON_LEGACY_WIDGET_INSTALLER => [
                'class' => Core\Widget\Installer::class,
                'attr' => [
                    $moduleName,
                ],
            ],
            ServiceProvider::CENTREON_LEGACY_WIDGET_UPGRADER => [
                'class' => Core\Widget\Upgrader::class,
                'attr' => [
                    $moduleName,
                ],
            ],
            ServiceProvider::CENTREON_LEGACY_WIDGET_REMOVER => [
                'class' => Core\Widget\Remover::class,
                'attr' => [
                    $moduleName,
                ],
            ],
        ];

        // check list of services
        foreach ($checkList as $serviceName => $data) {
            $className = $data['class'];
            $this->assertTrue($this->container->offsetExists($serviceName));

            $callable = $this->container->offsetGet($serviceName);

            $service = call_user_func_array($callable, $data['attr']);

            $this->assertInstanceOf($className, $service);
        }
    }

    /**
     * @covers \CentreonLegacy\ServiceProvider::order
     */
    public function testOrder(): void
    {
        $this->assertEquals(0, $this->provider::order());
    }
}
