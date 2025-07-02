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

require_once __DIR__ . '/formFunction.php';

$DBRESULT = $pearDB->query('SELECT * FROM `options`');
while ($opt = $DBRESULT->fetchRow()) {
    $gopt[$opt['key']] = myDecode($opt['value']);
}
$DBRESULT->closeCursor();

// Var information to format the element
$attrsText        = ['size' => '40'];
$attrsText2        = ['size' => '5'];
$attrSelect    = ['style' => 'width: 220px;'];
$attrSelect2    = ['style' => 'width: 50px;'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
$form->addElement('header', 'title', _('Modify General Options'));

// Various information
$form->addElement('text', 'rrdtool_path_bin', _('Directory + RRDTOOL Binary'), $attrsText);
$form->addElement('text', 'rrdtool_version', _('RRDTool Version'), $attrsText2);

$form->addElement('hidden', 'gopt_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$form->applyFilter('__ALL__', 'myTrim');
$form->registerRule('is_executable_binary', 'callback', 'is_executable_binary');
$form->registerRule('is_writable_path', 'callback', 'is_writable_path');

$form->registerRule('rrdcached_has_option', 'callback', 'rrdcached_has_option');
$form->registerRule('rrdcached_valid', 'callback', 'rrdcached_valid');

$form->addRule('rrdtool_path_bin', _("Can't execute binary"), 'is_executable_binary');
// $form->addRule('oreon_rrdbase_path', _("Can't write in directory"), 'is_writable_path'); - Field is not added so no need for rule

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path . 'rrdtool/');

$version = '';
if (isset($gopt['rrdtool_path_bin']) && trim($gopt['rrdtool_path_bin']) != '') {
    $version = getRrdtoolVersion($gopt['rrdtool_path_bin']);
}

$gopt['rrdtool_version'] = $version;

$form->freeze('rrdtool_version');

if (! isset($gopt['rrdcached_enable'])) {
    $gopt['rrdcached_enable'] = '0';
}

if (version_compare('1.4.0', $version, '>')) {
    $gopt['rrdcached_enable'] = '0';
    $form->freeze('rrdcached_enable');
    $form->freeze('rrdcached_port');
    $form->freeze('rrdcached_unix_path');
}

$form->setDefaults($gopt);

$subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
$DBRESULT = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);

$valid = false;
if ($form->validate()) {
    // Update in DB
    updateRRDToolConfigData($form->getSubmitValue('gopt_id'));

    // Update in Oreon Object
    $oreon->initOptGen($pearDB);

    $o = null;
    $valid = true;
    $form->freeze();
}
if (! $form->validate() && isset($_POST['gopt_id'])) {
    echo "<div class='msg' align='center'>" . _('Impossible to validate, one or more field is incorrect') . '</div>';
}

$form->addElement(
    'button',
    'change',
    _('Modify'),
    ['onClick' => "javascript:window.location.href='?p=" . $p . "&o=rrdtool'", 'class' => 'btc bt_info']
);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('genOpt_rrdtool_properties', _('RRDTool Properties'));
$tpl->assign('genOpt_rrdtool_configurations', _('RRDTool Configuration'));
$tpl->assign('valid', $valid);

$tpl->display('form.ihtml');
