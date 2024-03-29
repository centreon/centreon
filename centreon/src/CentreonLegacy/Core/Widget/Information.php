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

class Information
{
    /** @var ContainerInterface */
    protected $services;
    
    /** @var Utils */
    protected $utils;

    /** @var array */
    protected $cachedWidgetsList = [];

    /** @var bool */
    protected $hasWidgetsForUpgrade = false;

    /** @var bool */
    protected $hasWidgetsForInstallation = false;
    
    /**
     * Construct.
     *
     * @param ContainerInterface $services
     * @param Utils $utils
     */
    public function __construct(ContainerInterface $services, ?Utils $utils = null)
    {
        $this->services = $services;
        $this->utils = $utils ?? $services->get(ServiceProvider::CENTREON_LEGACY_UTILS);
    }

    /**
     * Get module configuration from file.
     *
     * @param string $widgetDirectory the widget directory (usually the widget name)
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getConfiguration($widgetDirectory)
    {
        $widgetPath = $this->utils->buildPath('/widgets/' . $widgetDirectory);
        if (! $this->services->get('filesystem')->exists($widgetPath . '/configs.xml')) {
            throw new \Exception('Cannot get configuration file of widget "' . $widgetDirectory . '"');
        }

        $conf = $this->utils->xmlIntoArray($widgetPath . '/configs.xml');

        $conf['directory'] = $widgetDirectory;
        $conf['autoRefresh'] ??= 0;
        $conf['version'] ??= null;

        return $conf;
    }

    /**
     * @return array
     */
    public function getTypes()
    {
        $types = [];

        $query = 'SELECT ft_typename, field_type_id '
            . 'FROM widget_parameters_field_type ';

        $result = $this->services->get('configuration_db')->query($query);

        while ($row = $result->fetchRow()) {
            $types[$row['ft_typename']] = [
                'id' => $row['field_type_id'],
                'name' => $row['ft_typename'],
            ];
        }

        return $types;
    }

    /**
     * @param string $name
     * @param null|mixed $widgetModelId
     *
     * @return mixed
     */
    public function getParameterIdByName($name, $widgetModelId = null)
    {
        $query = 'SELECT parameter_id '
            . 'FROM widget_parameters '
            . 'WHERE parameter_code_name = :name ';

        if (! is_null($widgetModelId)) {
            $query .= 'AND widget_model_id = :id ';
        }

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindValue(':name', $name, \PDO::PARAM_STR);
        if (! is_null($widgetModelId)) {
            $sth->bindValue(':id', $widgetModelId, \PDO::PARAM_INT);
        }

        $sth->execute();

        $id = null;
        if ($row = $sth->fetch()) {
            $id = $row['parameter_id'];
        }

        return $id;
    }

    /**
     * @param int $widgetId
     *
     * @return array
     */
    public function getParameters($widgetId)
    {
        $query = 'SELECT * '
            . 'FROM widget_parameters '
            . 'WHERE widget_model_id = :id ';

        $sth = $this->services->get('configuration_db')->prepare($query);
        $sth->bindParam(':id', $widgetId, \PDO::PARAM_INT);
        $sth->execute();

        $parameters = [];
        while ($row = $sth->fetch()) {
            $parameters[$row['parameter_code_name']] = $row;
        }

        return $parameters;
    }

    /**
     * @param string $name
     *
     * @return int
     */
    public function getIdByName($name)
    {
        $query = 'SELECT widget_model_id '
            . 'FROM widget_models '
            . 'WHERE directory = :directory';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':directory', $name, \PDO::PARAM_STR);

        $sth->execute();

        $id = null;
        if ($row = $sth->fetch()) {
            $id = $row['widget_model_id'];
        }

        return $id;
    }

    /**
     * Get list of available modules.
     *
     * @param string $search
     *
     * @return array
     */
    public function getAvailableList($search = '')
    {
        $widgetsConf = [];

        $widgetsPath = $this->getWidgetPath();
        $widgets = $this->services->get('finder')->directories()->depth('== 0')->in($widgetsPath);

        foreach ($widgets as $widget) {
            $widgetDirectory = $widget->getBasename();
            if (! empty($search) && ! stristr($widgetDirectory, $search)) {
                continue;
            }

            $widgetPath = $widgetsPath . $widgetDirectory;
            if (! $this->services->get('filesystem')->exists($widgetPath . '/configs.xml')) {
                continue;
            }

            // we use lowercase to avoid problems if directory name have some letters in uppercase
            $widgetsConf[strtolower($widgetDirectory)] = $this->getConfiguration($widgetDirectory);
        }

        return $widgetsConf;
    }

    /**
     * Get list of modules (installed or not).
     *
     * @return array
     */
    public function getList()
    {
        $installedWidgets = $this->getInstalledList();
        $availableWidgets = $this->getAvailableList();

        $widgets = [];

        foreach ($availableWidgets as $name => $properties) {
            $widgets[$name] = $properties;
            $widgets[$name]['source_available'] = true;
            $widgets[$name]['is_installed'] = false;
            $widgets[$name]['upgradeable'] = false;
            $widgets[$name]['installed_version'] = _('N/A');
            $widgets[$name]['available_version'] = $widgets[$name]['version'];

            unset($widgets[$name]['version']);

            if (isset($installedWidgets[$name])) {
                $widgets[$name]['id'] = $installedWidgets[$name]['widget_model_id'];
                $widgets[$name]['is_installed'] = true;
                $widgets[$name]['installed_version'] = $installedWidgets[$name]['version'];
                $widgetIsUpgradable = $installedWidgets[$name]['is_internal']
                    ? false
                    : $this->isUpgradeable(
                        $widgets[$name]['available_version'],
                        $widgets[$name]['installed_version']
                    );
                $widgets[$name]['upgradeable'] = $widgetIsUpgradable;
                $this->hasWidgetsForUpgrade = $widgetIsUpgradable ?: $this->hasWidgetsForUpgrade;
            }
        }

        foreach ($installedWidgets as $name => $properties) {
            if (! isset($widgets[$name])) {
                $widgets[$name] = $properties;
                $widgets[$name]['source_available'] = false;
            }
        }

        $this->hasWidgetsForInstallation = count($availableWidgets) > count($installedWidgets);
        $this->cachedWidgetsList = $widgets;

        return $widgets;
    }

    /**
     * @param string $widgetName
     *
     * @return array
     */
    public function isInstalled($widgetName)
    {
        $query = 'SELECT widget_model_id '
            . 'FROM widget_models '
            . 'WHERE directory = :name';
        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':name', $widgetName, \PDO::PARAM_STR);

        $sth->execute();

        return $sth->fetch();
    }

    /**
     * @param string $widgetName
     *
     * @return string
     */
    public function getWidgetPath($widgetName = '')
    {
        return $this->utils->buildPath('/widgets/' . $widgetName) . '/';
    }

    public function hasWidgetsForUpgrade()
    {
        return $this->hasWidgetsForUpgrade;
    }

    public function getUpgradeableList()
    {
        $list = empty($this->cachedWidgetsList) ? $this->getList() : $this->cachedWidgetsList;

        return array_filter($list, function ($widget) {
            return $widget['upgradeable'];
        });
    }

    public function hasWidgetsForInstallation()
    {
        return $this->hasWidgetsForInstallation;
    }

    public function getInstallableList()
    {
        $list = empty($this->cachedWidgetsList) ? $this->getList() : $this->cachedWidgetsList;

        return array_filter($list, function ($widget) {
            return ! $widget['is_installed'];
        });
    }

    /**
     * Get list of installed widgets.
     *
     * @return array
     */
    private function getInstalledList()
    {
        $query = 'SELECT * '
            . 'FROM widget_models ';

        $result = $this->services->get('configuration_db')->query($query);

        $widgets = $result->fetchAll();

        $installedWidgets = [];
        foreach ($widgets as $widget) {
            // we use lowercase to avoid problems if directory name have some letters in uppercase
            $installedWidgets[strtolower($widget['directory'])] = $widget;
            $installedWidgets[strtolower($widget['directory'])]['is_internal'] = $widget['is_internal'] === 1;
        }

        return $installedWidgets;
    }

    /**
     * @param string $availableVersion
     * @param string $installedVersion
     *
     * @return bool
     */
    private function isUpgradeable($availableVersion, $installedVersion)
    {
        $compare = version_compare($availableVersion, $installedVersion);

        return (bool) ($compare == 1);
    }
}
