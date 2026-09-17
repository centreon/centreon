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

$attrsText = ['size' => '40'];
$attrsText2 = ['size' => '5'];
$attrsAdvSelect = null;

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
$form->addElement('header', 'title', _('Modify Centcore options'));

// Centcore Options
$form->addElement('checkbox', 'enable_broker_stats', _('Enable Broker Statistics Collection'));
$form->addElement('text', 'gorgone_cmd_timeout', _('Timeout value for Gorgone commands'), $attrsText2);

$attrContacts = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => './include/common/webServices/rest/internal.php?'
    . 'object=centreon_configuration_contact&action=list', 'multiple' => true, 'linkedObject' => 'centreonContact'];
$attrContact1 = array_merge(
    $attrContacts,
    ['defaultDatasetRoute' => './include/common/webServices/rest/internal.php?'
        . 'object=centreon_configuration_contact&action=defaultValues&target=contactgroup&field=cg_contacts&id='
        . $cg_id]
);
$form->addElement('select2', 'cg_contacts', _('Linked Contacts'), [], $attrContact1);

$form->addElement('hidden', 'gopt_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$form->applyFilter('__ALL__', 'myTrim');

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path . '/api');

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
    updateAPIConfigData($pearDB, $form, $centreon);

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
    ['onClick' => "javascript:window.location.href='?p=" . $p . "&o=centcore'", 'class' => 'btc bt_info']
);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('valid', $valid);

$tpl->display('api.ihtml');
