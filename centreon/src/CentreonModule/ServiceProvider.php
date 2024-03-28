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

namespace CentreonModule;

use Centreon\Infrastructure\Provider\AutoloadServiceProviderInterface;
use CentreonModule\Application\Webservice;
use CentreonModule\Infrastructure\Service;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;

class ServiceProvider implements AutoloadServiceProviderInterface
{
    public const CENTREON_MODULE = 'centreon.module';

    /**
     * Register services.
     *
     * @param Container $pimple
     */
    public function register(Container $pimple): void
    {
        $pimple[\Centreon\ServiceProvider::CENTREON_WEBSERVICE]
            ->add(Webservice\CentreonModuleWebservice::class);

        // alias of CentreonModuleWebservice need for back compatibility and it's deprecated for using
        $pimple[\Centreon\ServiceProvider::CENTREON_WEBSERVICE]
            ->add(Webservice\CentreonModulesWebservice::class);

        $pimple[static::CENTREON_MODULE] = function (Container $container): Service\CentreonModuleService {
            $services = [
                'finder',
                'configuration',
                \Centreon\ServiceProvider::CENTREON_DB_MANAGER,
                \CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_LICENSE,
                \CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_INSTALLER,
                \CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADER,
                \CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_REMOVER,
                \CentreonLegacy\ServiceProvider::CENTREON_LEGACY_WIDGET_INSTALLER,
                \CentreonLegacy\ServiceProvider::CENTREON_LEGACY_WIDGET_UPGRADER,
                \CentreonLegacy\ServiceProvider::CENTREON_LEGACY_WIDGET_REMOVER,
            ];

            $locator = new ServiceLocator($container, $services);

            return new Service\CentreonModuleService($locator);
        };
    }

    public static function order(): int
    {
        return 5;
    }
}
