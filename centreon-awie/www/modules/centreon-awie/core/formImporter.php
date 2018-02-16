<?php
/**
 * Copyright 2018 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

if (!isset($oreon)) {
    exit();
}

require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/centreon-awie.conf.php';
require_once _CENTREON_PATH_ . '/www/lib/HTML/QuickForm.php';
require_once _CENTREON_PATH_ . '/www/lib/HTML/QuickForm/Renderer/ArraySmarty.php';
//require_once _MODULE_PATH_ . 'core/help.php';

$import = realpath(dirname(__FILE__));
// Smarty template Init
$path = _MODULE_PATH_ . "/core/template/";
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);
$form = new HTML_QuickForm('Form', 'post', "?p=" . $p);

$valid = false;
if ($form->validate()) {
    $valid = true;
    $form->freeze();
}

$form->addElement('header', 'title', _("Api Web Import"));


$subC = $form->addElement('submit', 'submitC', _("Import"), array("class" => "btc bt_success"));
$res = $form->addElement('reset', 'reset', _("Reset"));

if ($valid) {
    $form->freeze();
}


$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
$form->accept($renderer);

/*
$helpText = "";
foreach ($help as $key => $text) {
    $helpText .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign("helpText", $helpText);
*/

$tpl->assign('form', $renderer->toArray());
$tpl->assign('valid', $valid);


$tpl->display($import . "/templates/formExport.tpl");
