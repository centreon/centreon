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

require_once _CENTREON_PATH_ . 'www/class/centreonNotification.class.php';

/**
 * Get user list
 */
$contact = ['' => null];
$DBRESULT = $pearDB->query('SELECT cg_id, cg_name FROM contactgroup cg ORDER BY cg_alias');
while ($ct = $DBRESULT->fetchRow()) {
    $contact[$ct['cg_id']] = $ct['cg_name'];
}
$DBRESULT->closeCursor();

// Object init
$mediaObj = new CentreonMedia($pearDB);
$host_method = new CentreonHost($pearDB);
$oNotification = new CentreonNotification($pearDB);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// start header menu
$tpl->assign('headerMenu_host', _('Hosts'));
$tpl->assign('headerMenu_service', _('Services'));
$tpl->assign('headerMenu_host_esc', _('Escalated Hosts'));
$tpl->assign('headerMenu_service_esc', _('Escalated Services'));

// Different style between each lines
$style = 'one';

$groups = "''";
if (isset($_POST['contact'])) {
    $contactgroup_id = (int) htmlentities($_POST['contact'], ENT_QUOTES, 'UTF-8');
} else {
    $contactgroup_id = 0;
    $formData = ['contact' => $contactgroup_id];
}

$formData = ['contact' => $contactgroup_id];

// Create select form
$form = new HTML_QuickFormCustom('select_form', 'GET', '?p=' . $p);

$form->addElement('select', 'contact', _('Contact'), $contact, ['id' => 'contact', 'onChange' => 'submit();']);
$form->setDefaults($formData);

// Host escalations
$elemArrHostEsc = [];
if ($contactgroup_id) {
    $hostEscResources = $oNotification->getNotificationsContactGroup(2, $contactgroup_id);
}
if (isset($hostEscResources)) {
    foreach ($hostEscResources as $hostId => $hostName) {
        $elemArrHostEsc[] = ['MenuClass' => 'list_' . $style, 'RowMenu_hico' => './img/icones/16x16/server_network.gif', 'RowMenu_host' => myDecode($hostName)];
        $style = $style != 'two' ? 'two' : 'one';
    }
}
$tpl->assign('elemArrHostEsc', $elemArrHostEsc);

// Service escalations
$elemArrSvcEsc = [];
if ($contactgroup_id) {
    $svcEscResources = $oNotification->getNotificationsContactGroup(3, $contactgroup_id);
}
if (isset($svcEscResources)) {
    foreach ($svcEscResources as $hostId => $hostTab) {
        foreach ($hostTab as $serviceId => $tab) {
            $elemArrSvcEsc[] = ['MenuClass' => 'list_' . $style, 'RowMenu_hico' => './img/icones/16x16/server_network.gif', 'RowMenu_host' => myDecode($tab['host_name']), 'RowMenu_service' => myDecode($tab['service_description'])];
            $style = $style != 'two' ? 'two' : 'one';
        }
    }
}
$tpl->assign('elemArrSvcEsc', $elemArrSvcEsc);

// Hosts
$elemArrHost = [];
if ($contactgroup_id) {
    $hostResources = $oNotification->getNotificationsContactGroup(0, $contactgroup_id);
}
if (isset($hostResources)) {
    foreach ($hostResources as $hostId => $hostName) {
        $elemArrHost[] = ['MenuClass' => 'list_' . $style, 'RowMenu_hico' => './img/icones/16x16/server_network.gif', 'RowMenu_host' => myDecode($hostName)];
        $style = $style != 'two' ? 'two' : 'one';
    }
}
$tpl->assign('elemArrHost', $elemArrHost);

// Services
$elemArrSvc = [];
if ($contactgroup_id) {
    $svcResources = $oNotification->getNotificationsContactGroup(1, $contactgroup_id);
}
if (isset($svcResources)) {
    foreach ($svcResources as $hostId => $hostTab) {
        foreach ($hostTab as $serviceId => $tab) {
            $elemArrSvc[] = ['MenuClass' => 'list_' . $style, 'RowMenu_hico' => './img/icones/16x16/server_network.gif', 'RowMenu_host' => myDecode($tab['host_name']), 'RowMenu_service' => myDecode($tab['service_description'])];
            $style = $style != 'two' ? 'two' : 'one';
        }
    }
}
$tpl->assign('elemArrSvc', $elemArrSvc);

$labels = ['host_escalation' => _('Host escalations'), 'service_escalation' => _('Service escalations'), 'host_notifications' => _('Host notifications'), 'service_notifications' => _('Service notifications')];

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('msgSelect', _('Please select a user in order to view his notifications'));
$tpl->assign('p', $p);
$tpl->assign('contact', $contactgroup_id);
$tpl->assign('labels', $labels);
$tpl->display('displayNotification.ihtml');
