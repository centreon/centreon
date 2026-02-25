<?php
/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
define('STEP_NUMBER', 2);
$_SESSION['step'] = STEP_NUMBER;

require_once '../steps/functions.php';
$template = getTemplate('templates');

$title = _('Dependency check up');
$requiredLib = explode("\n", file_get_contents('../var/phplib'));
// PHP Libraries
$contents = "<table cellpadding='0' cellspacing='0' border='0' width='80%' class='StyleDottedHr' align='center'>";
$contents .= '<tr>
                <th>' . _('Module name') . '</th>
                <th>' . _('File') . '</th>
                <th>' . _('Status') . '</th>
             </tr>';
$allClear = 1;
foreach ($requiredLib as $line) {
    if (! $line) {
        continue;
    }
    $contents .= '<tr>';
    [$name, $lib] = explode(':', $line);
    $contents .= '<td>' . $name . '</td>';
    $contents .= '<td>' . $lib . '</td>';
    $contents .= '<td>';
    if (extension_loaded($lib)) {
        $libMessage = '<span style="color:#88b917; font-weight:bold;">' . _('Loaded') . '</span>';
    } else {
        $libMessage = '<span style="color:#e00b3d; font-weight:bold;">' . _('Not loaded') . '</span>';
        $allClear = 0;
    }
    $contents .= $libMessage;
    $contents .= '</td>';
    $contents .= '</tr>';
}

// Test if timezone is set
if (! ini_get('date.timezone')) {
    $contents .= '<tr>';
    $contents .= '<td>Timezone</td>';
    $contents .= '<td>' . _('Set the default timezone in php.ini file') . '</td>';
    $contents .= '<td>';

    $libMessage = '<span style="color:#e00b3d; font-weight:bold;">' . _('Not initialized') . '</span>';
    $allClear = 0;
    $contents .= $libMessage;
    $contents .= '</td>';
    $contents .= '</tr>';
}
$contents .= '</table>';

// PEAR Libraries
// @todo

$template->assign('step', STEP_NUMBER);
$template->assign('title', $title);
$template->assign('content', $contents);
$template->assign('valid', $allClear);
$template->display('content.tpl');
?>
<script type='text/javascript'>
    var allClear = <?php echo $allClear; ?>
        /**
         * Validates info
         *
         * @return bool
         */
        function validation() {
            if (!allClear) {
                return false;
            }
            return true;
        }
</script>
