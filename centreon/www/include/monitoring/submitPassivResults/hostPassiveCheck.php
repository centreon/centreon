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

$o = 'hd';

if (! isset($centreon)) {
    exit();
}

require_once _CENTREON_PATH_ . 'www/class/centreonHost.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonACL.class.php';

$host_name = $_GET['host_name'] ?? null;
$cmd = $_GET['cmd'] ?? null;

$hObj = new CentreonHost($pearDB);
$path = './include/monitoring/submitPassivResults/';

$pearDBndo = new CentreonDB('centstorage');

$aclObj = new CentreonACL($centreon->user->get_id());

if (! $is_admin) {
    $hostTab = explode(',', $centreon->user->access->getHostsString('NAME', $pearDBndo));
    foreach ($hostTab as $value) {
        if ($value == "'" . $host_name . "'") {
            $flag_acl = 1;
        }
    }
}
$hostTab = [];

if ($is_admin || ($flag_acl && ! $is_admin)) {
    $form = new HTML_QuickFormCustom('select_form', 'GET', '?p=' . $p);
    $form->addElement('header', 'title', _('Command Options'));

    $hosts = [$host_name => $host_name];

    $form->addElement('select', 'host_name', _('Host Name'), $hosts, ['onChange' => 'this.form.submit();']);

    $form->addRule('host_name', _('Required Field'), 'required');

    $return_code = ['0' => 'UP', '1' => 'DOWN', '2' => 'UNREACHABLE'];

    $form->addElement('select', 'return_code', _('Check result'), $return_code);
    $form->addElement('text', 'output', _('Check output'), ['size' => '100']);
    $form->addElement('text', 'dataPerform', _('Performance data'), ['size' => '100']);

    $form->addElement('hidden', 'author', $centreon->user->get_alias());
    $form->addElement('hidden', 'cmd', $cmd);
    $form->addElement('hidden', 'p', $p);

    $form->addElement('submit', 'submit', _('Save'), ['class' => 'btc bt_success']);
    $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);

    // Smarty template initialization
    $tpl = SmartyBC::createSmartyTemplate($path);

    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);

    $tpl->assign('form', $renderer->toArray());
    $tpl->display('hostPassiveCheck.ihtml');
}
