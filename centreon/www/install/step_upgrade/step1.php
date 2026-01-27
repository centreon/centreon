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

require_once __DIR__ . '/../../class/centreonDB.class.php';

session_start();
define('STEP_NUMBER', 1);
$_SESSION['step'] = STEP_NUMBER;

require_once '../steps/functions.php';
$template = getTemplate('templates');

$title = _('Centreon Upgrade');

$status = 0;
$content = '';

$errorMessages = [];

try {
    checkPhpPrerequisite();
} catch (Exception $e) {
    $errorMessages[] = $e->getMessage();
}

try {
    $db = new CentreonDB();
    checkMariaDBPrerequisite($db);
} catch (Exception $e) {
    $errorMessages[] = $e->getMessage();
}

foreach ($errorMessages as $errorMessage) {
    $status = 1;
    $content .= "<p class='required'>" . $errorMessage . '</p>';
}

if (! is_file('../install.conf.php')) {
    $status = 1;
    $content .= sprintf("<p class='required'>%s (install.conf.php)</p>", _('Configuration file not found.'));
}

if ($status !== 1) {
    $content .= sprintf(
        '<p>%s%s</p>',
        _('You are about to upgrade Centreon.'),
        _('The entire process should take around ten minutes.')
    );
    $content .= sprintf(
        '<p>%s</p>',
        _('It is strongly recommended to make a backup of your databases before going any further.')
    );
    require_once '../install.conf.php';
    setSessionVariables($conf_centreon);
}

$template->assign('step', STEP_NUMBER);
$template->assign('title', $title);
$template->assign('content', $content);
$template->display('content.tpl');
?>
<script type='text/javascript'>
    var status = <?php echo $status; ?>;

    /**
     * Validates info
     *
     * @return bool
     */
    function validation() {
        if (status == 0) {
            return true;
        }
        return false;
    }
</script>
