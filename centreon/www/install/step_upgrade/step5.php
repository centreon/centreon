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
define('STEP_NUMBER', 5);

$_SESSION['step'] = STEP_NUMBER;

require_once '../steps/functions.php';
require_once __DIR__ . '/../../../bootstrap.php';
$db = $dependencyInjector['configuration_db'];

/**
 * @var CentreonDB $db
 */
$res = $db->query("SELECT `value` FROM `options` WHERE `key` = 'send_statistics'");
$stat = $res->fetch();
$template = getTemplate('templates');

// If CEIP is disabled and if it's a major version of Centreon ask again
$aVersion = explode('.', $_SESSION['CURRENT_VERSION']);
if ((int) $stat['value'] != 1 && (int) $aVersion[2] === 0) {
    $stat = false;
}

$title = _('Upgrade finished');

if (is_dir(_CENTREON_VARLIB_ . '/installs') === false) {
    $contents .= '<br>Warning : The installation directory cannot be moved. Please create the directory '
        . _CENTREON_VARLIB_ . '/installs and give apache user write permissions.';
    $moveable = false;
} else {
    $moveable = true;
    $contents = sprintf(
        _('Congratulations, you have successfully upgraded to Centreon version <b>%s</b>.'),
        $_SESSION['CURRENT_VERSION']
    );
}

if ($stat === false) {
    $contents .= '<br/> <hr> <br/> <form id=\'form_step5\'>
                    <table cellpadding=\'0\' cellspacing=\'0\' border=\'0\' class=\'StyleDottedHr\' align=\'center\'>
                        <tbody>
                        <tr>
                            <td class=\'formValue\'>
                                <div class=\'md-checkbox md-checkbox-inline\' style=\'display:none;\'>
                                    <input id=\'send_statistics\' value=\'1\' name=\'send_statistics\' type=\'checkbox\' checked=\'checked\'/>
                                    <label class=\'empty-label\' for=\'send_statistics\'></label>
                                </div>
                            </td>
                            <td class=\'formlabel\'>
                                <p style="text-align:justify">Centreon uses a telemetry system and a Centreon Customer Experience
                                Improvement Program whereby anonymous information about the usage of this server
                                may be sent to Centreon. This information will solely be used to improve the
                                software user experience. You will be able to opt-out at any time about CEIP program
                                through administration menu.
                                    Refer to
                                    <a target="_blank" style="text-decoration: underline" 
                                    href="http://ceip.centreon.com/">ceip.centreon.com</a>
                                    for further details.
                                </p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </form>';
}

$template->assign('step', STEP_NUMBER);
$template->assign('title', $title);
$template->assign('content', $contents);
$template->assign('finish', 1);
$template->assign('blockPreview', 1);
$template->display('content.tpl');

if ($moveable) {
    ?>
    <script>
        /**
         * Validates info
         *
         * @return bool
         */
        function validation() {
            jQuery.ajax({
                type: 'POST',
                url: './step_upgrade/process/process_step5.php',
                data: jQuery('input[name="send_statistics"]').serialize(),
                success: () => {
                    javascript:self.location = "../index.html"
                }
            })
        }
    </script>
    <?php
}
