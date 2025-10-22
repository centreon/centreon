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
DEFINE('STEP_NUMBER', 3);
$_SESSION['step'] = STEP_NUMBER;

require_once '../steps/functions.php';
$template = getTemplate('templates');

require_once __DIR__ . '/../../../config/centreon.config.php';
require_once __DIR__ . '/../../class/centreonDB.class.php';

$title = _('Release notes');

$db = new CentreonDB();
$res = $db->query("SELECT `value` FROM `informations` WHERE `key` = 'version'");
$row = $res->fetchRow();
$current = $row['value'];

$_SESSION['CURRENT_VERSION'] = $current;

/*
** To find the final version that we should update to, we will look in
** the www/install/php directory where all PHP update scripts are
** stored. We will extract the target version from the filename and find
** the farthest version to the current version.
*/
$next = '';
if ($handle = opendir('../php')) {
    while (false !== ($file = readdir($handle))) {
        if (preg_match('/Update-([a-zA-Z0-9\-\.]+)\.php/', $file, $matches)) {
            if ((version_compare($current, $matches[1]) < 0)
                && (empty($next) || (version_compare($next, $matches[1]) < 0))) {
                $next = $matches[1];
            }
        }
    }
    closedir($handle);
}

$majors = preg_match('/^(\d+\.\d+)/', $next, $matches);
if (! isset($matches[1])) {
    $matches[1] = 'current';
}
$releaseNoteLink = 'https://documentation.centreon.com/' . $matches[1] . '/en/releases/centreon-core.html';

$title = _('Release notes');

$contents = '<p><b>' . _('Everything is ready!') . '</b></p>';
$contents .= '<p>' . _('Your Centreon Platform is about to be upgraded from version ') . $current . _(' to ') . $next . '</p>';
$contents .= '<p>' . _('For further details on changes, please find the complete changelog on ');
$contents .= '<a href="' . $releaseNoteLink . '"target="_blank" style="text-decoration:underline;font-size:11px">documentation.centreon.com</a></p>';

$template->assign('step', STEP_NUMBER);
$template->assign('title', $title);
$template->assign('content', $contents);
$template->assign('blockPreview', 1);
$template->display('content.tpl');
?>
<script type='text/javascript'>
    var step3_s = 3;
    var step3_t;

    jQuery(function () {
        jQuery('#next').attr('disabled', 'disabled');
        timeout_button();
    });

    function timeout_button() {
        if (step3_t) {
            clearTimeout(step3_t);
        }
        jQuery("#next").val("Next (" + step3_s + ")");
        step3_s--;
        if (step3_s == 0) {
            jQuery("#next").val("Next");
            jQuery("#next").removeAttr('disabled');
        } else {
            step3_t = setTimeout('timeout_button()', 1000);
        }
    }

    function validation() {
        return true;
    }
</script>
