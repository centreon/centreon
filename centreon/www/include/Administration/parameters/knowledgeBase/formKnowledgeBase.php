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

$DBRESULT = $pearDB->query(
    "SELECT * FROM `options` WHERE options.key LIKE 'kb_%'"
);
$originalPassword = null;
while ($opt = $DBRESULT->fetchRow()) {
    $gopt[$opt['key']] = myDecode($opt['value']);

    // store the value before occultation to be able to extract Vault Path if it is configured
    if ($opt['key'] === 'kb_wiki_password') {
        $originalPassword = $opt['value'];
        $gopt[$opt['key']] = CentreonAuth::PWS_OCCULTATION;
    }
}

$DBRESULT->closeCursor();

$attrsAdvSelect = null;

$autocompleteOff = ['autocomplete' => 'new-password'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);

// Knowledge base form
$form->addElement('text', 'kb_wiki_url', _('Knowledge base url'));
$form->addRule('kb_wiki_url', _('Mandatory field'), 'required');
$form->addElement('text', 'kb_wiki_account', _('Knowledge wiki account (with delete right)'), $autocompleteOff);
$form->addRule('kb_wiki_account', _('Mandatory field'), 'required');
$form->addElement('password', 'kb_wiki_password', _('Knowledge wiki account password'), $autocompleteOff);
$form->addRule('kb_wiki_password', _('Mandatory field'), 'required');
$form->addElement('checkbox', 'kb_wiki_certificate', 'ssl certificate', _('Ignore ssl certificate'));

$form->addElement('hidden', 'gopt_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$form->applyFilter('__ALL__', 'myTrim');

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path . '/knowledgeBase');

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
    updateKnowledgeBaseData($pearDB, $form, $oreon, $originalPassword);

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
    ['onClick' => "javascript:window.location.href='?p=" . $p . "&o=knowledgeBase'", 'class' => 'btc bt_info']
);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('valid', $valid);

$tpl->display('formKnowledgeBase.html');
