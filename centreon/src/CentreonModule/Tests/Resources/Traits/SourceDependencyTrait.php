<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonModule\Tests\Resources\Traits;

use CentreonLegacy\Core\Module;
use CentreonLegacy\Core\Widget;
use CentreonLegacy\ServiceProvider;
use CentreonModule\Tests\Infrastructure\Source\ModuleSourceTest;
use Pimple\Container;

/**
 * @group CentreonModule
 * @group ServiceProvider
 */
trait SourceDependencyTrait
{
    /**
     * @param array<ServiceProvider::CENTREON_LEGACY_MODULE_LICENSE> $container
     */
    public function setUpSourceDependency(&$container): void
    {
        // Legacy dependency
        $container[ServiceProvider::CENTREON_LEGACY_MODULE_LICENSE] = $this
            ->getMockBuilder(Module\License::class)
            ->disableOriginalConstructor()
            ->getMock();

        $container[ServiceProvider::CENTREON_LEGACY_MODULE_LICENSE]->method('getLicenseExpiration')->willReturn(null);

        $container[ServiceProvider::CENTREON_LEGACY_MODULE_INSTALLER] = function (Container $container) {
            return function ($moduleName) {
                return $this->getMockBuilder(Module\Installer::class)
                    ->disableOriginalConstructor()
                    ->getMock();
            };
        };

        $container[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADER] = function (Container $container) {
            return function ($moduleName, $moduleId) {
                return $this->getMockBuilder(Module\Upgrader::class)
                    ->disableOriginalConstructor()
                    ->getMock();
            };
        };

        $container[ServiceProvider::CENTREON_LEGACY_MODULE_REMOVER] = function (Container $container) {
            return function ($moduleName, $moduleId) {
                $service = $this->getMockBuilder(Module\Remover::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods([
                        'remove',
                    ])
                    ->getMock();

                // mock remove to dump moduleName and moduleId
                $service
                    ->method('remove')
                    ->will($this->returnCallback(function () use ($moduleName, $moduleId): void {
                        if ($moduleName !== ModuleSourceTest::$moduleName) {
                            throw new \Exception($moduleName, (int) $moduleId);
                        }
                    }));

                return $service;
            };
        };

        $container[ServiceProvider::CENTREON_LEGACY_WIDGET_INSTALLER] = function (Container $container) {
            return function ($widgetDirectory) {
                return $this->getMockBuilder(Widget\Installer::class)
                    ->disableOriginalConstructor()
                    ->getMock();
            };
        };

        $container[ServiceProvider::CENTREON_LEGACY_WIDGET_UPGRADER] = function (Container $container) {
            return function ($widgetDirectory) {
                return $this->getMockBuilder(Widget\Upgrader::class)
                    ->disableOriginalConstructor()
                    ->getMock();
            };
        };

        $container[ServiceProvider::CENTREON_LEGACY_WIDGET_REMOVER] = function (Container $container) {
            return function ($widgetDirectory) {
                return $this->getMockBuilder(Widget\Remover::class)
                    ->disableOriginalConstructor()
                    ->getMock();
            };
        };
    }
}
