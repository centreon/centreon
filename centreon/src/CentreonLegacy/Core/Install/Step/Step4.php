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

class Step4 extends AbstractStep
{
    public function getContent()
    {
        $installDir = __DIR__ . '/../../../../../www/install';
        require_once $installDir . '/steps/functions.php';
        $template = getTemplate($installDir . '/steps/templates');

        $parameters = $this->getBrokerParameters();

        $template->assign('title', _('Broker module information'));
        $template->assign('step', 4);
        $template->assign('parameters', $parameters);

        return $template->fetch('content.tpl');
    }

    public function getBrokerParameters()
    {
        $configuration = $this->getBaseConfiguration();
        $file = __DIR__ . '/../../../../../www/install/var/brokers/centreon-broker';
        $lines = explode("\n", file_get_contents($file));

        $parameters = [];
        foreach ($lines as $line) {
            if (! $line || $line[0] == '#') {
                continue;
            }
            [$key, $label, $required, $paramType, $default] = explode(';', $line);
            $val = $default;
            $configurationKey = strtolower(str_replace(' ', '_', $key));
            if (isset($configuration[$configurationKey])) {
                $val = $configuration[$configurationKey];
            }
            $parameters[$configurationKey] = [
                'name' => $configurationKey,
                'type' => $paramType,
                'label' => $label,
                'required' => $required,
                'value' => $val,
            ];
        }

        return $parameters;
    }

    public function setBrokerConfiguration($parameters): void
    {
        $configurationFile = __DIR__ . '/../../../../../www/install/tmp/broker.json';
        file_put_contents($configurationFile, json_encode($parameters));
    }
}
