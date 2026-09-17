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

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';

$result = [];

$parameters = filter_input_array(INPUT_POST);

if (isset($parameters['modules'])) {
    $utilsFactory = new CentreonLegacy\Core\Utils\Factory($dependencyInjector);
    $utils = $utilsFactory->newUtils();
    $moduleFactory = new CentreonLegacy\Core\Module\Factory($dependencyInjector, $utils);

    foreach ($parameters['modules'] as $module) {
        /* If the selected module is already installed (as dependency for example)
         * then we can skip the installation process
         */
        if (
            isset($result['modules'][$module]['install'])
            && $result['modules'][$module]['install'] === true
        ) {
            continue;
        }
        /* retrieving the module's information stored in the conf.php
         * configuration file
         */
        $information = $moduleFactory->newInformation();
        $moduleInformation = $information->getConfiguration($module);
        /* if the selected module has dependencies defined in its configuration file
         * then we need to install them before installing the selected module to
         * ensure its correct installation
         */
        if (isset($moduleInformation['dependencies'])) {
            foreach ($moduleInformation['dependencies'] as $dependency) {
                // If the dependency is already installed skip install
                if (
                    isset($result['modules'][$dependency]['install'])
                    && $result['modules'][$dependency]['install'] === true
                ) {
                    continue;
                }
                $installer = $moduleFactory->newInstaller($dependency);
                $id = $installer->install();
                $install = $id ? true : false;
                $result['modules'][$dependency] = [
                    'module' => $dependency,
                    'install' => $install,
                ];
            }
        }
        // installing the selected module
        $installer = $moduleFactory->newInstaller($module);
        $id = $installer->install();
        $install = $id ? true : false;
        $result['modules'][$module] = [
            'module' => $module,
            'install' => $install,
        ];
    }
}

if (isset($parameters['widgets'])) {
    $utilsFactory = new CentreonLegacy\Core\Utils\Factory($dependencyInjector);
    $utils = $utilsFactory->newUtils();
    $widgetFactory = new CentreonLegacy\Core\Widget\Factory($dependencyInjector, $utils);
    foreach ($parameters['widgets'] as $widget) {
        $installer = $widgetFactory->newInstaller($widget);
        $id = $installer->install();
        $install = ($id) ? true : false;
        $result['widgets'][$widget] = ['widget' => $widget, 'install' => $install];
    }
}

echo json_encode($result);
