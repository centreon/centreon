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

if (! isset($oreon)) {
    exit();
}

$DBRESULT = $pearDB->query('SELECT * FROM `options`');
while ($opt = $DBRESULT->fetchRow()) {
    $gopt[$opt['key']] = myDecode($opt['value']);
}
$DBRESULT->closeCursor();

$attrsText = ['size' => '40'];
$attrsText2 = ['size' => '5'];
$attrsAdvSelect = null;

$autocompleteOff = ['autocomplete' => 'new-password'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
$form->addElement('header', 'title', _('Modify Gorgone options'));

// Gorgone Options
$form->addElement('checkbox', 'enable_broker_stats', _('Enable Broker statistics collection'));
$form->addElement('text', 'gorgone_cmd_timeout', _('Timeout value for Gorgone commands'), $attrsText2);
$form->addRule('gorgone_cmd_timeout', _('Must be a number'), 'numeric');
$form->addElement('text', 'gorgone_illegal_characters', _('Illegal characters for Gorgone commands'), $attrsText);

// API
$form->addElement('text', 'gorgone_api_address', _('IP address or hostname'), $attrsText);
$form->addElement('text', 'gorgone_api_port', _('Port'), $attrsText2);
$form->addRule('gorgone_api_port', _('Must be a number'), 'numeric');
$form->addElement('text', 'gorgone_api_username', _('Username'), array_merge($attrsText, $autocompleteOff));
$form->addElement('password', 'gorgone_api_password', _('Password'), array_merge($attrsText, $autocompleteOff));
$form->addElement(
    'checkbox',
    'gorgone_api_ssl',
    _('Use SSL/TLS'),
    null
);
$form->setDefaults(1);
$form->addElement(
    'checkbox',
    'gorgone_api_allow_self_signed',
    _('Allow self signed certificate'),
    null
);
$form->setDefaults(1);

$form->addElement('hidden', 'gopt_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$form->applyFilter('__ALL__', 'myTrim');

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path . '/gorgone');

$form->setDefaults($gopt);

$subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
$DBRESULT = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
if ($form->validate()) {
    // Update in DB
    updateGorgoneConfigData($pearDB, $form, $oreon);

    $o = null;
    $valid = true;
    $form->freeze();
}
if (! $form->validate() && isset($_POST['gopt_id'])) {
    echo "<div class='msg' align='center'>" . _('impossible to validate, one or more field is incorrect') . '</div>';
}

$form->addElement(
    'button',
    'change',
    _('Modify'),
    ['onClick' => "javascript:window.location.href='?p=" . $p . "&o=gorgone'", 'class' => 'btc bt_info']
);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('valid', $valid);

$tpl->display('gorgone.ihtml');
