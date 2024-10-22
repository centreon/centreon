<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

class Step6Vault extends AbstractStep
{
    public function getContent()
    {
        $installDir = __DIR__ . '/../../../../../www/install';
        require_once $installDir . '/steps/functions.php';
        $template = getTemplate($installDir . '/steps/templates');

        $parameters = $this->getVaultConfiguration();

        $template->assign('title', _('Vault information'));
        $template->assign('step', 6.1);
        $template->assign('parameters', $parameters);

        return $template->fetch('content.tpl');
    }

    public function setVaultConfiguration(array $configuration): void
    {
        $configurationFile = __DIR__ . '/../../../../../www/install/tmp/vault.json';
        file_put_contents($configurationFile, json_encode($configuration));
    }
}