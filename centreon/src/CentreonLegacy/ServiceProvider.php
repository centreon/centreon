<?php declare(strict_types=1);

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

namespace CentreonLegacy;

use Centreon\Infrastructure\Provider\AutoloadServiceProviderInterface;
use CentreonLegacy\Core\Module;
use CentreonLegacy\Core\Module\License;
use CentreonLegacy\Core\Utils;
use CentreonLegacy\Core\Widget;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;
use Symfony\Component\Finder\Finder;

class ServiceProvider implements AutoloadServiceProviderInterface
{
    public const CONFIGURATION = 'configuration';
    public const CENTREON_REST_HTTP = 'centreon.rest.http';
    public const CENTREON_LEGACY_UTILS = 'centreon.legacy.utils';
    public const CENTREON_LEGACY_MODULE_HEALTHCHECK = 'centreon.legacy.module.healthcheck';
    public const CENTREON_LEGACY_MODULE_INFORMATION = 'centreon.legacy.module.information';
    public const CENTREON_LEGACY_MODULE_INSTALLER = 'centreon.legacy.module.installer';
    public const CENTREON_LEGACY_MODULE_UPGRADER = 'centreon.legacy.module.upgrader';
    public const CENTREON_LEGACY_MODULE_REMOVER = 'centreon.legacy.module.remover';
    public const CENTREON_LEGACY_MODULE_LICENSE = 'centreon.legacy.module.license';
    public const CENTREON_LEGACY_LICENSE = 'centreon.legacy.license';
    public const CENTREON_LEGACY_WIDGET_INFORMATION = 'centreon.legacy.widget.information';
    public const CENTREON_LEGACY_WIDGET_INSTALLER = 'centreon.legacy.widget.installer';
    public const CENTREON_LEGACY_WIDGET_UPGRADER = 'centreon.legacy.widget.upgrader';
    public const CENTREON_LEGACY_WIDGET_REMOVER = 'centreon.legacy.widget.remover';
    public const SYMFONY_FINDER = 'sf.finder';

    /**
     * Register CentreonLegacy services.
     *
     * @param Container $pimple
     */
    public function register(Container $pimple): void
    {
        $pimple[static::CENTREON_LEGACY_UTILS] = function (Container $container): Utils\Utils {
            $services = [
                'realtime_db',
                'configuration_db',
                'configuration',
            ];

            $locator = new ServiceLocator($container, $services);

            return new Utils\Utils($locator);
        };

        $pimple[static::SYMFONY_FINDER] = fn(Container $container): Finder => new Finder();

        $this->registerConfiguration($pimple);
        $this->registerRestHttp($pimple);
        $this->registerModule($pimple);
        $this->registerWidget($pimple);
    }

    public static function order(): int
    {
        return 0;
    }

    protected function registerConfiguration(Container $pimple): void
    {
        $pimple[static::CONFIGURATION] = function (Container $container): Core\Configuration\Configuration {
            global $conf_centreon, $centreon_path;

            return new Core\Configuration\Configuration(
                $conf_centreon,
                $centreon_path,
                $container[static::SYMFONY_FINDER]
            );
        };
    }

    /**
     * @param Container $pimple
     */
    protected function registerRestHttp(Container $pimple): void
    {
        $pimple[static::CENTREON_REST_HTTP] = fn(Container $container) => function ($contentType = 'application/json', $logFile = null) {
            // @codeCoverageIgnoreStart
            return new \CentreonRestHttp($contentType, $logFile); // @codeCoverageIgnoreEnd
        };
    }

    protected function registerModule(Container $pimple): void
    {
        $pimple[static::CENTREON_LEGACY_MODULE_HEALTHCHECK] = function (Container $container): Module\Healthcheck {
            $services = [
                'configuration',
            ];

            $locator = new ServiceLocator($container, $services);

            return new Module\Healthcheck($locator);
        };

        $pimple[static::CENTREON_LEGACY_MODULE_INFORMATION] = function (Container $container): Module\Information {
            $services = [
                'finder',
                'filesystem',
                'configuration_db',
                ServiceProvider::CENTREON_LEGACY_UTILS,
                ServiceProvider::CENTREON_LEGACY_MODULE_LICENSE,
            ];

            $locator = new ServiceLocator($container, $services);

            return new Module\Information($locator);
        };

        $pimple[static::CENTREON_LEGACY_MODULE_INSTALLER] = $pimple->factory(function (Container $container) {
            $services = [
                'filesystem',
                'configuration_db',
                ServiceProvider::CENTREON_LEGACY_UTILS,
                ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION,
            ];

            $locator = new ServiceLocator($container, $services);

            return fn($moduleName): Module\Installer => new Module\Installer($locator, null, $moduleName);
        });

        $pimple[static::CENTREON_LEGACY_MODULE_UPGRADER] = $pimple->factory(function (Container $container) {
            $services = [
                'finder',
                'filesystem',
                'configuration_db',
                ServiceProvider::CENTREON_LEGACY_UTILS,
                ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION,
            ];

            $locator = new ServiceLocator($container, $services);

            return fn($moduleName, $moduleId): Module\Upgrader => new Module\Upgrader($locator, null, $moduleName, null, $moduleId);
        });

        $pimple[static::CENTREON_LEGACY_MODULE_REMOVER] = $pimple->factory(function (Container $container) {
            $services = [
                'filesystem',
                'configuration_db',
                ServiceProvider::CENTREON_LEGACY_UTILS,
                ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION,
            ];

            $locator = new ServiceLocator($container, $services);

            return fn($moduleName, $moduleId): Module\Remover => new Module\Remover($locator, null, $moduleName, null, $moduleId);
        });

        $pimple[static::CENTREON_LEGACY_MODULE_LICENSE] = $pimple->factory(function (Container $container) {
            $services = [
                ServiceProvider::CENTREON_LEGACY_MODULE_HEALTHCHECK,
            ];

            $locator = new ServiceLocator($container, $services);

            return new License($locator);
        });

        // alias to centreon.legacy.module.license service
        $pimple[static::CENTREON_LEGACY_LICENSE] = fn(Container $container): License => $container[ServiceProvider::CENTREON_LEGACY_MODULE_LICENSE];
    }

    protected function registerWidget(Container $pimple): void
    {
        $pimple[static::CENTREON_LEGACY_WIDGET_INFORMATION] = function (Container $container): Widget\Information {
            $services = [
                'finder',
                'filesystem',
                'configuration_db',
                ServiceProvider::CENTREON_LEGACY_UTILS,
            ];

            $locator = new ServiceLocator($container, $services);

            return new Widget\Information($locator);
        };

        $pimple[static::CENTREON_LEGACY_WIDGET_INSTALLER] = $pimple->factory(function (Container $container) {
            $services = [
                'configuration_db',
                ServiceProvider::CENTREON_LEGACY_UTILS,
                ServiceProvider::CENTREON_LEGACY_WIDGET_INFORMATION,
            ];

            $locator = new ServiceLocator($container, $services);

            return fn($widgetDirectory): Widget\Installer => new Widget\Installer($locator, null, $widgetDirectory, null);
        });

        $pimple[static::CENTREON_LEGACY_WIDGET_UPGRADER] = $pimple->factory(function (Container $container) {
            $services = [
                'configuration_db',
                ServiceProvider::CENTREON_LEGACY_UTILS,
                ServiceProvider::CENTREON_LEGACY_WIDGET_INFORMATION,
            ];

            $locator = new ServiceLocator($container, $services);

            return fn($widgetDirectory): Widget\Upgrader => new Widget\Upgrader($locator, null, $widgetDirectory, null);
        });

        $pimple[static::CENTREON_LEGACY_WIDGET_REMOVER] = $pimple->factory(function (Container $container) {
            $services = [
                'configuration_db',
                ServiceProvider::CENTREON_LEGACY_UTILS,
                ServiceProvider::CENTREON_LEGACY_WIDGET_INFORMATION,
            ];

            $locator = new ServiceLocator($container, $services);

            return fn($widgetDirectory): Widget\Remover => new Widget\Remover($locator, null, $widgetDirectory, null);
        });
    }
}
