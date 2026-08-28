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
 * Get contact group list (ACL-filtered for non-admin users)
 */
$contact = ['' => null];
if ($centreon->user->admin) {
    $DBRESULT = $pearDB->query('SELECT cg_id, cg_name FROM contactgroup cg ORDER BY cg_alias');
    while ($ct = $DBRESULT->fetchRow()) {
        $contact[$ct['cg_id']] = $ct['cg_name'];
    }
    $DBRESULT->closeCursor();
} else {
    $cgAcl = $centreon->user->access->getContactGroupAclConf(
        ['fields' => ['cg_id', 'cg_name'], 'keys' => ['cg_id'], 'order' => ['cg_alias']],
        false
    );
    foreach ($cgAcl as $cgId => $cg) {
        $contact[$cgId] = $cg['cg_name'];
    }
}

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
} elseif (isset($_GET['contact'])) {
    // The contact group selector submits its form through GET
    $contactgroup_id = (int) $_GET['contact'];
} elseif (isset($_GET['cg_id'])) {
    $contactgroup_id = (int) $_GET['cg_id'];
} else {
    $contactgroup_id = 0;
}

// Downgrading an unusable id to "nothing selected" without a word would show
// "please select a user" to someone who did select one — reachable through a
// bookmarked panel URL, or a deletion between the listing render and the click.
// The list above holds every group the user may see, so a miss means either the
// group is gone or it is out of scope; only the second is an ACL matter, and for
// an admin the list is exhaustive, so it can only ever be the first.
$contactGroupRefused = false;
$contactGroupMissing = false;
if ($contactgroup_id && ! array_key_exists($contactgroup_id, $contact)) {
    $contactGroupExists = ! $centreon->user->admin && (bool) $pearDB->fetchOne(
        'SELECT 1 FROM contactgroup WHERE cg_id = :cgId',
        Adaptation\Database\Connection\Collection\QueryParameters::create([
            Adaptation\Database\Connection\ValueObject\QueryParameter::int('cgId', $contactgroup_id),
        ])
    );
    Adaptation\Log\Logger::create(Adaptation\Log\Enum\LogChannelEnum::WEB)->warning(
        $contactGroupExists
            ? 'Notification view: contact group outside the access scope'
            : 'Notification view: unknown contact group requested',
        ['cg_id' => $contactgroup_id, 'user_id' => $centreon->user->get_id()]
    );
    $contactgroup_id = 0;
    $contactGroupExists ? $contactGroupRefused = true : $contactGroupMissing = true;
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
$msgSelect = _('Please select a user in order to view his notifications');
if ($contactGroupRefused) {
    $msgSelect = _('This contact group is not within your access groups');
} elseif ($contactGroupMissing) {
    $msgSelect = _('This contact group no longer exists');
}
$tpl->assign('msgSelect', $msgSelect);
$tpl->assign('p', $p);
$tpl->assign('contact', $contactgroup_id);
$tpl->assign('labels', $labels);
$tpl->display('displayNotification.ihtml');
