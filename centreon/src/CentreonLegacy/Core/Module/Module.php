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

namespace CentreonLegacy\Core\Module;

use CentreonLegacy\Core\Utils\Utils;
use CentreonLegacy\ServiceProvider;
use Psr\Container\ContainerInterface;

class Module
{
    /** @var Information */
    protected $informationObj;

    /** @var string */
    protected $moduleName;

    /** @var int */
    protected $moduleId;

    /** @var Utils */
    protected $utils;

    /** @var array */
    protected $moduleConfiguration;

    /** @var ContainerInterface */
    protected $services;

    /**
     * @param ContainerInterface $services
     * @param Information $informationObj
     * @param string $moduleName
     * @param Utils $utils
     * @param int $moduleId
     */
    public function __construct(
        ContainerInterface $services,
        ?Information $informationObj = null,
        $moduleName = '',
        ?Utils $utils = null,
        $moduleId = null
    ) {
        $this->moduleId = $moduleId;
        $this->services = $services;
        $this->informationObj = $informationObj ?? $services->get(ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION);
        $this->moduleName = $moduleName;
        $this->utils = $utils ?? $services->get(ServiceProvider::CENTREON_LEGACY_UTILS);

        $this->moduleConfiguration = $this->informationObj->getConfiguration($this->moduleName);
    }

    /**
     * @param string $moduleName
     *
     * @return string
     */
    public function getModulePath($moduleName = '')
    {
        return $this->utils->buildPath('/modules/' . $moduleName);
    }
}
