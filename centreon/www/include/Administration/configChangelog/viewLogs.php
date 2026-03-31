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

if (! isset($centreon)) {
    exit();
}

// Path to the configuration dir
$path = './include/Administration/configChangelog/';

// PHP functions
require_once './include/common/common-Func.php';
require_once './class/centreonDB.class.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Detail view: when object_id is in GET and no search form submitted
if (isset($_GET['object_id']) && ! isset($_POST['searchO']) && ! isset($_POST['searchU']) && ! isset($_POST['otype'])) {
    $pearDBO = new CentreonDB('centstorage');

    $tpl->assign('object_id', _('Object ID'));
    $tpl->assign('action', _('Action'));
    $tpl->assign('contact_name', _('Contact Name'));
    $tpl->assign('field_name', _('Field Name'));
    $tpl->assign('field_value', _('Field Value'));
    $tpl->assign('before', _('Before'));
    $tpl->assign('after', _('After'));
    $tpl->assign('logs', _('Logs for '));
    $tpl->assign('objTypeLabel', _('Object type : '));
    $tpl->assign('objNameLabel', _('Object name : '));
    $tpl->assign('noModifLabel', _('No modification was made.'));

    $listAction = $centreon->CentreonLogAction->listAction(
        (int) $_GET['object_id'],
        $_GET['object_type']
    );
    $listModification = $centreon->CentreonLogAction->listModification(
        (int) $_GET['object_id'],
        $_GET['object_type']
    );

    if (isset($listAction)) {
        $tpl->assign('action', $listAction);
    }
    if (isset($listModification)) {
        $tpl->assign('modification', $listModification);
    }

    // QuickForm needed for {$form.hidden} in the detail template
    $form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());

    $tpl->display('viewLogsDetails.ihtml');
} else {
    // Listing view — AJAX-driven
    $defaultLimit = 30;
    $dbResult = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'maxViewConfiguration'");
    if ($gopt = $dbResult->fetch()) {
        $defaultLimit = (int) $gopt['value'] ?: 30;
    }

    $tpl->assign('p', $p);
    $tpl->assign('defaultLimit', $defaultLimit);
    $tpl->display('viewLogs.ihtml');
}
