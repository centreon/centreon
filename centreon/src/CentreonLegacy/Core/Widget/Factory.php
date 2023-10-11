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

namespace CentreonLegacy\Core\Widget;

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
     * @param mixed $utils
     */
    public function __construct(\Pimple\Container $dependencyInjector, $utils = null)
    {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * @return \CentreonLegacy\Core\Widget\Information
     */
    public function newInformation()
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_WIDGET_INFORMATION];
    }

    /**
     * @param string $widgetDirectory
     *
     * @return \CentreonLegacy\Core\Widget\Installer
     */
    public function newInstaller($widgetDirectory)
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_WIDGET_INSTALLER]($widgetDirectory);
    }

    /**
     * @param string $widgetDirectory
     *
     * @return \CentreonLegacy\Core\Widget\Upgrader
     */
    public function newUpgrader($widgetDirectory)
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_WIDGET_UPGRADER]($widgetDirectory);
    }

    /**
     * @param string $widgetDirectory
     *
     * @return \CentreonLegacy\Core\Widget\Remover
     */
    public function newRemover($widgetDirectory)
    {
        return $this->dependencyInjector[ServiceProvider::CENTREON_LEGACY_WIDGET_REMOVER]($widgetDirectory);
    }
}
