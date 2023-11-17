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

use CentreonLegacy\Core\Utils\Utils;
use CentreonLegacy\ServiceProvider;
use Psr\Container\ContainerInterface;

class Widget
{
    /** @var \CentreonLegacy\Core\Widget\Information */
    protected $informationObj;

    /** @var string */
    protected $widgetName;

    /** @var \CentreonLegacy\Core\Utils\Utils */
    protected $utils;

    /** @var array */
    protected $widgetConfiguration;

    /** @var ContainerInterface */
    protected $services;

    /**
     * Construct.
     *
     * @param \Psr\Container\ContainerInterface $services
     * @param \CentreonLegacy\Core\Widget\Information $informationObj
     * @param string $widgetName
     * @param \CentreonLegacy\Core\Utils\Utils $utils
     */
    public function __construct(
        ContainerInterface $services,
        ?Information $informationObj = null,
        $widgetName = '',
        ?Utils $utils = null
    ) {
        $this->services = $services;
        $this->informationObj = $informationObj ?? $services->get(ServiceProvider::CENTREON_LEGACY_WIDGET_INFORMATION);
        $this->widgetName = $widgetName;
        $this->utils = $utils ?? $services->get(ServiceProvider::CENTREON_LEGACY_UTILS);
        $this->widgetConfiguration = $this->informationObj->getConfiguration($this->widgetName);
    }

    /**
     * @param string $widgetName
     *
     * @return string
     */
    public function getWidgetPath($widgetName = '')
    {
        return $this->utils->buildPath('/widgets/' . $widgetName);
    }
}
