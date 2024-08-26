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

class Step9 extends AbstractStep
{
    public function getContent()
    {
        $installDir = __DIR__ . '/../../../../../www/install';
        require_once $installDir . '/steps/functions.php';
        $template = getTemplate($installDir . '/steps/templates');

        $backupDir = __DIR__ . '/../../../../../installDir';
        $contents = '';
        if (! is_dir($backupDir)) {
            $contents .= '<br>Warning : The installation directory cannot be move. '
                . 'Please create the directory ' . $backupDir . ' '
                . 'and give it the rigths to apache user to write.';
        }

        $template->assign('title', _('Installation finished'));
        $template->assign('step', 9);
        $template->assign('finish', 1);
        $template->assign('blockPreview', 1);
        $template->assign('contents', $contents);

        return $template->fetch('content.tpl');
    }
}
