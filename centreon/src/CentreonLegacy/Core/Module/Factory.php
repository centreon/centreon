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

namespace CentreonLegacy\Core\Module;

use CentreonLegacy\ServiceProvider;

/**
 * @deprecated since version 18.10.4
 */
class Factory
{
    /** @var \Pimple\Container */
    protected $dependencyInjector;

    /**
     * @param \Pimple\Container $dependencyInjector
     */
    public function __construct(\Pimple\Container $dependencyInjector)
    {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * @return Information
     */
    public function newInformation()
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION];
    }

    /**
     * @param string $moduleName
     *
     * @return Installer
     */
    public function newInstaller($moduleName)
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_MODULE_INSTALLER]($moduleName);
    }

    /**
     * @param string $moduleName
     * @param int $moduleId
     *
     * @return Upgrader
     */
    public function newUpgrader($moduleName, $moduleId)
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADER]($moduleName, $moduleId);
    }

    /**
     * @param string $moduleName
     * @param int $moduleId
     *
     * @return Remover
     */
    public function newRemover($moduleName, $moduleId)
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_MODULE_REMOVER]($moduleName, $moduleId);
    }

    /**
     * @return License
     */
    public function newLicense()
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_MODULE_LICENSE];
    }
}
