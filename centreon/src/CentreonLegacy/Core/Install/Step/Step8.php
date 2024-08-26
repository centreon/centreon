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

namespace CentreonLegacy\Core\Install\Step;

class Step8 extends AbstractStep
{
    public function getContent()
    {
        $installDir = __DIR__ . '/../../../../../www/install';
        require_once $installDir . '/steps/functions.php';
        $template = getTemplate($installDir . '/steps/templates');

        $modules = $this->getModules();
        $widgets = $this->getWidgets();

        $template->assign('title', _('Modules installation'));
        $template->assign('step', 8);
        $template->assign('modules', $modules);
        $template->assign('widgets', $widgets);

        return $template->fetch('content.tpl');
    }

    public function getModules()
    {
        $utilsFactory = new \CentreonLegacy\Core\Utils\Factory($this->dependencyInjector);
        $moduleFactory = new \CentreonLegacy\Core\Module\Factory($this->dependencyInjector);
        $module = $moduleFactory->newInformation();

        return $module->getList();
    }

    /**
     * Get the list of available widgets (installed on the system).
     * List filled with the content of the config.xml widget files.
     *
     * @return array
     */
    public function getWidgets()
    {
        $utilsFactory = new \CentreonLegacy\Core\Utils\Factory($this->dependencyInjector);
        $widgetFactory = new \CentreonLegacy\Core\Widget\Factory($this->dependencyInjector);
        $widget = $widgetFactory->newInformation();

        return $widget->getList();
    }
}
