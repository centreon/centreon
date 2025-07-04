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

include_once _CENTREON_PATH_ . 'www/class/centreonGMT.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';

// Init GMT class
$hostStr = $oreon->user->access->getHostsString('ID', $pearDBO);

$centreonGMT = new CentreonGMT($pearDB);
$centreonGMT->getMyGMTFromSession(session_id());

if ($centreon->user->access->checkAction('host_comment')) {
    // ACL
    if (isset($_GET['host_name'])) {
        $host_id = getMyHostID($_GET['host_name']);
        $host_name = $_GET['host_name'];
    } else {
        $host_name = '';
    }

    $data = [];
    if (isset($host_id)) {
        $data = ['host_id' => $host_id];
    }

    if (isset($_GET['host_name'])) {
        $host_id = getMyHostID($_GET['host_name']);
        $host_name = $_GET['host_name'];
        if ($host_name == '_Module_Meta') {
            $host_name = 'Meta';
        }
    }

    // Database retrieve information for differents elements list we need on the page
    $hosts = ['' => ''];
    $query = 'SELECT host_id, host_name '
        . 'FROM `host` '
        . "WHERE host_register = '1' "
        . "AND host_activate = '1'"
        . $oreon->user->access->queryBuilder('AND', 'host_id', $hostStr)
        . 'ORDER BY host_name';
    $DBRESULT = $pearDB->query($query);
    while ($host = $DBRESULT->fetchRow()) {
        $hosts[$host['host_id']] = $host['host_name'];
    }
    $DBRESULT->closeCursor();

    $debug = 0;
    $attrsTextI = ['size' => '3'];
    $attrsText = ['size' => '30'];
    $attrsTextarea = ['rows' => '7', 'cols' => '100'];

    // Form begin
    $form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
    if ($o == 'ah') {
        $form->addElement('header', 'title', _('Add a comment for Host'));
    }

    // Indicator basic information
    $redirect = $form->addElement('hidden', 'o');
    $redirect->setValue($o);

    if (isset($host_id)) {
        $form->addElement('hidden', 'host_id', $host_id);
    } else {
        $selHost = $form->addElement('select', 'host_id', _('Host Name'), $hosts);
        $form->addRule('host_id', _('Required Field'), 'required');
    }

    $persistant = $form->addElement('checkbox', 'persistant', _('Persistent'));
    $persistant->setValue('1');
    $form->addElement('textarea', 'comment', _('Comments'), $attrsTextarea);
    $form->addRule('comment', _('Required Field'), 'required');

    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);

    $form->setDefaults($data);

    $valid = false;
    if ((isset($_POST['submitA']) && $_POST['submitA']) && $form->validate()) {
        if (! isset($_POST['persistant']) || ! in_array($_POST['persistant'], ['0', '1'])) {
            $_POST['persistant'] = '0';
        }
        if (! isset($_POST['comment'])) {
            $_POST['comment'] = 0;
        }
        AddHostComment($_POST['host_id'], $_POST['comment'], $_POST['persistant']);
        $valid = true;
        require_once $path . 'listComment.php';
    } else {
        // Smarty template initialization
        $tpl = SmartyBC::createSmartyTemplate($path, 'template/');

        if (isset($host_id)) {
            $tpl->assign('host_name', $host_name);
        }
        // Apply a template definition
        $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
        $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
        $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
        $form->accept($renderer);
        $tpl->assign('form', $renderer->toArray());
        $tpl->assign('o', $o);

        $tpl->display('AddHostComment.ihtml');
    }
}
