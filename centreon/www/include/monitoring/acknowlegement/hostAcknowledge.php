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

require_once './include/monitoring/common-Func.php';
require_once './class/centreonDB.class.php';

// DB connexion
$pearDBndo = $pearDBO;

$host_name = isset($_GET['host_name']) ? htmlentities($_GET['host_name'], ENT_QUOTES, 'UTF-8') : null;
$cmd = isset($_GET['cmd']) ? htmlentities($_GET['cmd'], ENT_QUOTES, 'UTF-8') : null;
$en = isset($_GET['en']) ? htmlentities($_GET['en'], ENT_QUOTES, 'UTF-8') : 1;

// Smarty template initialization
$path = './include/monitoring/acknowlegement/';
$tpl = SmartyBC::createSmartyTemplate($path, './templates/');

if (! $is_admin) {
    $lcaHostByName = $centreon->user->access->getHostsServicesName($pearDBndo);
}

if ($is_admin || (isset($lcaHostByName[$host_name]))) {
    $form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p . '&host_name=' . urlencode($host_name));

    $form->addElement('header', 'title', _('Acknowledge a host'));

    $tpl->assign('hostlabel', _('Host Name'));
    $tpl->assign('hostname', $host_name);
    $tpl->assign('en', $en);
    $tpl->assign('authorlabel', _('Alias'));
    $tpl->assign('authoralias', $centreon->user->get_alias());

    $ckbx[] = $form->addElement('checkbox', 'notify', _('Notify'));
    if (isset($centreon->optGen['monitoring_ack_notify']) && $centreon->optGen['monitoring_ack_notify']) {
        $ckbx[0]->setChecked(true);
    }

    $ckbx1[] = $form->addElement('checkbox', 'persistent', _('Persistent'));
    if (isset($centreon->optGen['monitoring_ack_persistent']) && $centreon->optGen['monitoring_ack_persistent']) {
        $ckbx1[0]->setChecked(true);
    }

    $ckbx2[] = $form->addElement('checkbox', 'ackhostservice', _('Acknowledge services attached to hosts'));
    if (isset($centreon->optGen['monitoring_ack_svc']) && $centreon->optGen['monitoring_ack_svc']) {
        $ckbx2[0]->setChecked(true);
    }

    $ckbx3[] = $form->addElement('checkbox', 'sticky', _('Sticky'));
    if (isset($centreon->optGen['monitoring_ack_sticky']) && $centreon->optGen['monitoring_ack_sticky']) {
        $ckbx3[0]->setChecked(true);
    }

    $form->addElement('hidden', 'host_name', $host_name);
    $form->addElement('hidden', 'author', $centreon->user->get_alias());
    $form->addElement('hidden', 'cmd', $cmd);
    $form->addElement('hidden', 'p', $p);
    $form->addElement('hidden', 'en', $en);

    $textarea = $form->addElement('textarea', 'comment', _('Comment'), ['rows' => '8', 'cols' => '80']);
    $textarea->setValue(sprintf(_('Acknowledged by %s'), $centreon->user->get_alias()));

    $form->addRule('comment', _('Comment is required'), 'required', '', 'client');
    $form->setJsWarnings(_('Invalid information entered'), _('Please correct these fields'));

    $form->addElement('submit', 'submit', ($en == 1) ? _('Add') : _('Delete'));
    $form->addElement('reset', 'reset', _('Reset'));

    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');

    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', 'hd');
    $tpl->display('hostAcknowledge.ihtml');
}
