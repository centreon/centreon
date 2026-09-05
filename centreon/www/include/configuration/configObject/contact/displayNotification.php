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

// Connect to Database
$pearDBO = new CentreonDB('centstorage');

/**
 * Get user list (ACL-filtered for non-admin users)
 */
$contact = ['' => null];
if ($centreon->user->admin) {
    // contact_register = '1' keeps contact templates out of the list, matching
    // what getContactAclConf() returns on the non-admin branch below.
    $DBRESULT = $pearDB->query("SELECT contact_id, contact_alias FROM contact WHERE contact_register = '1' ORDER BY contact_alias");
    while ($ct = $DBRESULT->fetchRow()) {
        $contact[$ct['contact_id']] = $ct['contact_alias'];
    }
    $DBRESULT->closeCursor();
} else {
    $ctAcl = $centreon->user->access->getContactAclConf(
        ['fields' => ['contact_id', 'contact_alias'], 'get_row' => 'contact_alias', 'keys' => ['contact_id'], 'order' => ['contact_alias']]
    );
    foreach ($ctAcl as $ctId => $ctAlias) {
        $contact[$ctId] = $ctAlias;
    }
}

// Object init
$mediaObj       = new CentreonMedia($pearDB);
$host_method    = new CentreonHost($pearDB);
$oNotification     = new CentreonNotification($pearDB);

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
// The selector form submits via GET (onChange), so the value lands in $_GET.
$contactId = isset($_GET['contact']) ? (int) $_GET['contact'] : 0;

$contactUnavailable = false;
if ($contactId && ! array_key_exists($contactId, $contact)) {
    // Not in the caller's scoped list (out of ACL scope, or gone). Refuse it
    // without probing whether it exists, so the message cannot enumerate ids.
    Adaptation\Log\Logger::create(Adaptation\Log\Enum\LogChannelEnum::WEB)->warning(
        'Notification view: contact not available in scope',
        ['contact_id' => $contactId, 'user_id' => $centreon->user->get_id()]
    );
    $contactId = 0;
    $contactUnavailable = true;
}

$formData = ['contact' => $contactId];

// Create select form
$form = new HTML_QuickFormCustom('select_form', 'GET', '?p=' . $p);

$form->addElement('select', 'contact', _('Contact'), $contact, ['id' => 'contact', 'onChange' => 'submit();']);
$form->setDefaults($formData);

// Host escalations
$elemArrHostEsc = [];
if ($contactId) {
    $hostEscResources = $oNotification->getNotifications(2, $contactId);
}
if (isset($hostEscResources)) {
    foreach ($hostEscResources as $hostId => $hostName) {
        $elemArrHostEsc[] = ['MenuClass' => 'list_' . $style, 'RowMenu_hico' => './img/icons/host.png', 'RowMenu_host' => myDecode($hostName)];
        $style = $style != 'two' ? 'two' : 'one';
    }
}
$tpl->assign('elemArrHostEsc', $elemArrHostEsc);

// Service escalations
$elemArrSvcEsc = [];
if ($contactId) {
    $svcEscResources = $oNotification->getNotifications(3, $contactId);
}
if (isset($svcEscResources)) {
    foreach ($svcEscResources as $hostId => $hostTab) {
        foreach ($hostTab as $serviceId => $tab) {
            $elemArrSvcEsc[] = ['MenuClass' => 'list_' . $style, 'RowMenu_hico' => './img/icons/host.png', 'RowMenu_host' => myDecode($tab['host_name']), 'RowMenu_service' => myDecode($tab['service_description'])];
            $style = $style != 'two' ? 'two' : 'one';
        }
    }
}
$tpl->assign('elemArrSvcEsc', $elemArrSvcEsc);

// Hosts
$elemArrHost = [];
if ($contactId) {
    $hostResources = $oNotification->getNotifications(0, $contactId);
}
if (isset($hostResources)) {
    foreach ($hostResources as $hostId => $hostName) {
        $elemArrHost[] = ['MenuClass' => 'list_' . $style, 'RowMenu_hico' => './img/icons/host.png', 'RowMenu_host' => myDecode($hostName)];
        $style = $style != 'two' ? 'two' : 'one';
    }
}
$tpl->assign('elemArrHost', $elemArrHost);

// Services
$elemArrSvc = [];
if ($contactId) {
    $svcResources = $oNotification->getNotifications(1, $contactId);
}
if (isset($svcResources)) {
    foreach ($svcResources as $hostId => $hostTab) {
        foreach ($hostTab as $serviceId => $tab) {
            $elemArrSvc[] = ['MenuClass' => 'list_' . $style, 'RowMenu_hico' => './img/icons/host.png', 'RowMenu_host' => myDecode($tab['host_name']), 'RowMenu_service' => myDecode($tab['service_description'])];
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
$msgSelect = _('Please select a user in order to view his notifications');
if ($contactUnavailable) {
    $msgSelect = _('This contact is not available');
}
$tpl->assign('msgSelect', $msgSelect);
$tpl->assign('p', $p);
$tpl->assign('contact', $contactId);
$tpl->assign('labels', $labels);
$tpl->display('displayNotification.ihtml');
