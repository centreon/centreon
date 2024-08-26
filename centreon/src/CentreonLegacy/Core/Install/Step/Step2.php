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

class Step2 extends AbstractStep
{
    public function getContent()
    {
        $installDir = __DIR__ . '/../../../../../www/install';
        require_once $installDir . '/steps/functions.php';
        $template = getTemplate($installDir . '/steps/templates');

        $libs = $this->getPhpLib();
        $validate = true;
        if (count($libs['unloaded'])) {
            $validate = false;
        }

        $template->assign('title', _('Dependency check up'));
        $template->assign('step', 2);
        $template->assign('libs', $libs);
        $template->assign('validate', $validate);

        return $template->fetch('content.tpl');
    }

    private function getPhpLib()
    {
        $libs = [
            'loaded' => [],
            'unloaded' => [],
        ];

        $requiredLib = explode(
            "\n",
            file_get_contents(__DIR__ . '/../../../../../www/install/var/phplib')
        );
        foreach ($requiredLib as $line) {
            if (! $line) {
                continue;
            }

            [$name, $lib] = explode(':', $line);

            if (extension_loaded($lib)) {
                $libs['loaded'][$name] = $lib . '.so';
            } else {
                $libs['unloaded'][$name] = $lib . '.so';
            }
        }

        if (! ini_get('date.timezone')) {
            $libs['unloaded']['Timezone'] = _('Set the default timezone in php.ini file');
        }

        return $libs;
    }
}
