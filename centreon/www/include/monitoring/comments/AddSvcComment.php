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
require_once _CENTREON_PATH_ . 'www/class/centreonService.class.php';

// Init GMT class
$centreonGMT = new CentreonGMT($pearDB);
$centreonGMT->getMyGMTFromSession(session_id());

$hostStr = $centreon->user->access->getHostsString('ID', $pearDBO);

if ($centreon->user->access->checkAction('service_comment')) {
    $LCA_error = 0;

    $cG = $_GET['host_id'] ?? null;
    $cP = $_POST['host_id'] ?? null;
    $host_id = $cG ?: $cP;

    $host_name = null;
    $svc_description = null;
    if (isset($_GET['host_name'], $_GET['service_description'])) {
        $host_id = getMyHostID($_GET['host_name']);
        $service_id = getMyServiceID($_GET['service_description'], $host_id);
        $host_name = $_GET['host_name'];
        $svc_description = $_GET['service_description'];
        if ($host_name == '_Module_Meta' && preg_match('/^meta_(\d+)/', $svc_description, $matches)) {
            $host_name = 'Meta';
            $serviceObj = new CentreonService($pearDB);
            $serviceParameters = $serviceObj->getParameters($service_id, ['display_name']);
            $svc_description = $serviceParameters['display_name'];
        }
    }

    /*
     * Database retrieve information for differents
     * elements list we need on the page
     */
    $query = "SELECT host_id, host_name FROM `host` WHERE (host_register = '1'  OR host_register = '2' )"
        . $centreon->user->access->queryBuilder('AND', 'host_id', $hostStr) . 'ORDER BY host_name';
    $DBRESULT = $pearDB->query($query);
    $hosts = [null => null];
    while ($row = $DBRESULT->fetchRow()) {
        $hosts[$row['host_id']] = $row['host_name'];
    }
    $DBRESULT->closeCursor();

    $services = [];
    if (isset($host_id)) {
        $services = $centreon->user->access->getHostServices($pearDBO, $host_id);
    }

    $debug = 0;
    $attrsTextI = ['size' => '3'];
    $attrsText = ['size' => '30'];
    $attrsTextarea = ['rows' => '7', 'cols' => '100'];

    // Form begin
    $form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
    $form->addElement('header', 'title', _('Add a comment for Service'));

    // Indicator basic information
    $redirect = $form->addElement('hidden', 'o');
    $redirect->setValue($o);

    if (isset($host_id, $service_id)) {
        $form->addElement('hidden', 'host_id', $host_id);
        $form->addElement('hidden', 'service_id', $service_id);
    } else {
        $disabled = ' ';
        $attrServices = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => './api/internal.php?object=centreon_configuration_service&action=list&e=enable', 'multiple' => true, 'linkedObject' => 'centreonService'];
        $form->addElement('select2', 'service_id', _('Services'), [$disabled], $attrServices);
    }

    $persistant = $form->addElement('checkbox', 'persistant', _('Persistent'));
    $persistant->setValue('1');

    $form->addElement('textarea', 'comment', _('Comments'), $attrsTextarea);
    $form->addRule('comment', _('Required Field'), 'required');

    $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);

    $valid = false;
    if ((isset($_POST['submitA']) && $_POST['submitA']) && $form->validate()) {
        if (! isset($_POST['persistant']) || ! in_array($_POST['persistant'], ['0', '1'])) {
            $_POST['persistant'] = '0';
        }
        if (! isset($_POST['comment'])) {
            $_POST['comment'] = 0;
        }

        // global services comment
        if (! isset($_POST['host_id'])) {
            foreach ($_POST['service_id'] as $value) {
                $info = explode('-', $value);
                AddSvcComment(
                    $info[0],
                    $info[1],
                    $_POST['comment'],
                    $_POST['persistant']
                );
            }
        } else {
            // specific service comment
            AddSvcComment($_POST['host_id'], $_POST['service_id'], $_POST['comment'], $_POST['persistant']);
        }

        $valid = true;
        require_once $path . 'listComment.php';
    } else {
        // Smarty template initialization
        $tpl = SmartyBC::createSmartyTemplate($path, 'template/');

        // Apply a template definition
        $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
        $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
        $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
        $form->accept($renderer);

        if (isset($host_id, $service_id)) {
            $tpl->assign('host_name', $host_name);
            $tpl->assign('service_description', $svc_description);
        }

        $tpl->assign('form', $renderer->toArray());
        $tpl->assign('o', $o);
        $tpl->display('AddSvcComment.ihtml');
    }
}
