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

namespace CentreonLegacy\Core\Install\Step;

class Step1 extends AbstractStep
{
    public function getContent()
    {
        $installDir = __DIR__ . '/../../../../../www/install';
        require_once $installDir . '/steps/functions.php';

        $template = getTemplate($installDir . '/steps/templates');

        try {
            checkPhpPrerequisite();
            $this->setConfiguration();
        } catch (\Exception $e) {
            $template->assign('errorMessage', $e->getMessage());
            $template->assign('validate', false);
        }

        $template->assign('title', _('Welcome to Centreon Setup'));
        $template->assign('step', 1);

        return $template->fetch('content.tpl');
    }

    public function setConfiguration()
    {
        $configurationFile = __DIR__ . '/../../../../../www/install/install.conf.php';

        if (! $this->dependencyInjector['filesystem']->exists($configurationFile)) {
            throw new \Exception('Configuration file "install.conf.php" does not exist.');
        }

        $conf_centreon = [];
        require $configurationFile;

        $tmpDir = __DIR__ . '/../../../../../www/install/tmp';
        if (! $this->dependencyInjector['filesystem']->exists($tmpDir)) {
            $this->dependencyInjector['filesystem']->mkdir($tmpDir);
        }

        file_put_contents($tmpDir . '/configuration.json', json_encode($conf_centreon));
    }
}
