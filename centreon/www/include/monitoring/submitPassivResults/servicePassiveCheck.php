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

$o = 'svcd';

if (! isset($centreon)) {
    exit();
}

require_once _CENTREON_PATH_ . 'www/class/centreonHost.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonService.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreonMeta.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';

$host_name = $_GET['host_name'] ?? null;
$service_description = $_GET['service_description'] ?? null;
$cmd = $_GET['cmd'] ?? null;
$is_meta = isset($_GET['is_meta']) && $_GET['is_meta'] == 'true' ? $_GET['is_meta'] : 'false';

$hObj = new CentreonHost($pearDB);
$serviceObj = new CentreonService($pearDB);
$metaObj = new CentreonMeta($pearDB);
$path = './include/monitoring/submitPassivResults/';

$pearDBndo = new CentreonDB('centstorage');

$host_id = $hObj->getHostId($host_name);
$hostDisplayName = $host_name;
$serviceDisplayName = $service_description;

if ($is_meta == 'true') {
    $metaId = null;
    if (preg_match('/meta_(\d+)/', $service_description, $matches)) {
        $metaId = $matches[1];
    }
    $hostDisplayName = 'Meta';
    $serviceId = $metaObj->getRealServiceId($metaId);
    $serviceParameters = $serviceObj->getParameters($serviceId, ['display_name']);
    $serviceDisplayName = $serviceParameters['display_name'];
}

if (! $is_admin && $host_id) {
    $flag_acl = 0;
    if ($is_meta == 'true') {
        $aclMetaServices = $centreon->user->access->getMetaServices();
        $aclMetaIds = array_keys($aclMetaServices);
        if (in_array($metaId, $aclMetaIds)) {
            $flag_acl = 1;
        }
    } else {
        $serviceTab = $centreon->user->access->getHostServices($pearDBndo, $host_id);
        if (in_array($service_description, $serviceTab)) {
            $flag_acl = 1;
        }
    }
}

if (($is_admin || $flag_acl) && $host_id) {
    $form = new HTML_QuickFormCustom('select_form', 'GET', '?p=' . $p);
    $form->addElement('header', 'title', _('Command Options'));

    $return_code = ['0' => 'OK', '1' => 'WARNING', '3' => 'UNKNOWN', '2' => 'CRITICAL'];

    $form->addElement('select', 'return_code', _('Check result'), $return_code);
    $form->addElement('text', 'output', _('Check output'), ['size' => '100']);
    $form->addElement('text', 'dataPerform', _('Performance data'), ['size' => '100']);

    $form->addElement('hidden', 'host_name', $host_name);
    $form->addElement('hidden', 'service_description', $service_description);
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

    $tpl->assign('host_name', $hostDisplayName);
    $tpl->assign('service_description', $serviceDisplayName);
    $tpl->assign('form', $renderer->toArray());
    $tpl->display('servicePassiveCheck.ihtml');
}
