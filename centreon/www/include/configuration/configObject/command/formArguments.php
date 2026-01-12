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

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');

require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonLang.class.php';
require_once _CENTREON_PATH_ . 'bootstrap.php';

session_start();
session_write_close();

$centreon = $_SESSION['centreon'];
if (! isset($centreon)) {
    exit();
}

$centreonLang = new CentreonLang(_CENTREON_PATH_, $centreon);
$centreonLang->bindLang();

$args = [];
$str = '';
$nb_arg = 0;
if (isset($_GET['cmd_line']) && $_GET['cmd_line']) {
    $str = str_replace('$', '@DOLLAR@', $_GET['cmd_line']);
    $nb_arg = preg_match_all('/@DOLLAR@ARG([0-9]+)@DOLLAR@/', $str, $matches);
}

if (isset($_GET['textArea']) && $_GET['textArea']) {
    $textArea = urldecode($_GET['textArea']);
    $tab = preg_split("/\;\;\;/", $textArea);
    foreach ($tab as $key => $value) {
        $tab2 = preg_split("/\ \:\ /", $value, 2);
        $index = str_replace('ARG', '', $tab2[0]);
        if (isset($tab2[0]) && $tab2[0]) {
            $args[$index] = htmlentities($tab2[1]);
        }
    }
}

// FORM

$path = _CENTREON_PATH_ . '/www/include/configuration/configObject/command/';

$attrsText = ['size' => '30'];
$attrsText2 = ['size' => '60'];
$attrsAdvSelect = ['style' => 'width: 200px; height: 100px;'];
$attrsTextarea = ['rows' => '5', 'cols' => '40'];

// Basic info
$form = new HTML_QuickFormCustom('Form', 'post');
$form->addElement('header', 'title', _('Argument Descriptions'));
$form->addElement('header', 'information', _('Arguments'));

$subS = $form->addElement(
    'button',
    'submitSaveAdd',
    _('Save'),
    ['onClick' => 'setDescriptions();', 'class' => 'btc bt_success']
);
$subS = $form->addElement(
    'button',
    'close',
    _('Close'),
    ['onClick' => 'closeBox();', 'class' => 'btc bt_default']
);

// Smarty template

$tpl = new SmartyBC();
$tpl->setTemplateDir($path);
$tpl->setCompileDir(_CENTREON_PATH_ . '/GPL_LIB/SmartyCache/compile');
$tpl->setConfigDir(_CENTREON_PATH_ . '/GPL_LIB/SmartyCache/config');
$tpl->setCacheDir(_CENTREON_PATH_ . '/GPL_LIB/SmartyCache/cache');
$tpl->addPluginsDir(_CENTREON_PATH_ . '/GPL_LIB/smarty-plugins');
$tpl->loadPlugin('smarty_function_eval');
$tpl->setForceCompile(true);
$tpl->setAutoLiteral(false);

$tpl->assign('nb_arg', $nb_arg);
$dummyTab = [];
$defaultDesc = [];

for ($i = 1; $i <= $nb_arg; $i++) {
    $dummyTab[$i] = $matches[1][$i - 1];
    $defaultDesc[$i] = '';
    if (isset($args[$dummyTab[$i]]) && $args[$dummyTab[$i]]) {
        $defaultDesc[$i] = $args[$dummyTab[$i]];
    }
}
$tpl->assign('dummyTab', $dummyTab);
$tpl->assign('defaultDesc', $defaultDesc);
$tpl->assign('noArgMsg', _('Sorry, your command line does not contain any $ARGn$ macro.'));

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1"></font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('args', $args);
$tpl->display('formArguments.ihtml');
