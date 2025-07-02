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

$DBRESULT = $pearDB->query('SELECT * FROM `options`');

while ($opt = $DBRESULT->fetchRow()) {
    $gopt[$opt['key']] = myDecode($opt['value']);
}
$DBRESULT->closeCursor();

$attrsText        = ['size' => '40'];
$attrsText2        = ['size' => '5'];
$attrsAdvSelect = null;

$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
$form->addElement('header', 'title', _('Modify General Options'));
$form->addElement('header', 'debug', _('Debug'));

$form->addElement('text', 'debug_path', _('Logs Directory'), $attrsText);

$form->addElement('checkbox', 'debug_auth', _('Authentication debug'));
$form->addElement(
    'select',
    'debug_level',
    _('Debug level'),
    [
        000 => 'None',
        100 => 'Debug',
        200 => 'Info',
        250 => 'Notice',
        300 => 'Warning',
        400 => 'Error',
        500 => 'Critical',
        550 => 'Alert',
        600 => 'Emergency',
    ]
);
$form->addElement('checkbox', 'debug_sql', _('SQL debug'));
$form->addElement('checkbox', 'debug_nagios_import', _('Monitoring Engine Import debug'));
$form->addElement('checkbox', 'debug_rrdtool', _('RRDTool debug'));
$form->addElement('checkbox', 'debug_ldap_import', _('LDAP User Import debug'));
$form->addElement('checkbox', 'debug_gorgone', _('Centreon Gorgone debug'));
$form->addElement('checkbox', 'debug_centreontrapd', _('Centreontrapd debug'));

$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('debug_path', 'slash');

$form->registerRule('is_valid_path', 'callback', 'is_valid_path');
$form->registerRule('is_readable_path', 'callback', 'is_readable_path');
$form->registerRule('is_executable_binary', 'callback', 'is_executable_binary');
$form->registerRule('is_writable_path', 'callback', 'is_writable_path');
$form->registerRule('is_writable_file', 'callback', 'is_writable_file');
$form->registerRule('is_writable_file_if_exist', 'callback', 'is_writable_file_if_exist');

$form->addRule('debug_path', _("Can't write in directory"), 'is_writable_path');

$form->addElement('hidden', 'gopt_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path . 'debug/');

$form->setDefaults($gopt);

$subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
$DBRESULT = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);

$valid = false;

if ($form->validate()) {
    // Update in DB
    updateDebugConfigData($form->getSubmitValue('gopt_id'));
    // Update in Oreon Object
    $oreon->initOptGen($pearDB);

    $o = null;
    $valid = true;
    $form->freeze();

    if (isset($_POST['debug_auth_clear'])) {
        @unlink($oreon->optGen['debug_path'] . 'auth.log');
    }

    if (isset($_POST['debug_nagios_import_clear'])) {
        @unlink($oreon->optGen['debug_path'] . 'cfgimport.log');
    }

    if (isset($_POST['debug_rrdtool_clear'])) {
        @unlink($oreon->optGen['debug_path'] . 'rrdtool.log');
    }

    if (isset($_POST['debug_ldap_import_clear'])) {
        @unlink($oreon->optGen['debug_path'] . 'ldapsearch.log');
    }

    if (isset($_POST['debug_inventory_clear'])) {
        @unlink($oreon->optGen['debug_path'] . 'inventory.log');
    }
}

if (! $form->validate() && isset($_POST['gopt_id'])) {
    echo "<div class='msg' align='center'>" . _('Impossible to validate, one or more field is incorrect') . '</div>';
}

$form->addElement(
    'button',
    'change',
    _('Modify'),
    ['onClick' => "javascript:window.location.href='?p=" . $p . "&o=debug'", 'class' => 'btc bt_info']
);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('genOpt_debug_options', _('Debug Properties'));
$tpl->assign('valid', $valid);

$tpl->display('form.ihtml');
