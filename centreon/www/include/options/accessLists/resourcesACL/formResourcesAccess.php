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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;

if (! isset($centreon)) {
    exit();
}

// Database retrieve information for LCA
if ($o === RESOURCE_ACCESS_MODIFY || $o === RESOURCE_ACCESS_WATCH) {

    try {
        $queryParameters = new QueryParameters([
            QueryParameter::int('resourceAccessId', $aclId),
        ]);

        $aclResourceInformation = $pearDB->fetchAssociative(
            'SELECT * FROM acl_resources WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        $acl = array_map('myDecode', $aclResourceInformation);

        // poller relations
        $acl['acl_pollers'] = $pearDB->fetchFirstColumn(
            'SELECT poller_id FROM acl_resources_poller_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // host relations
        $acl['acl_hosts'] = $pearDB->fetchFirstColumn(
            'SELECT host_host_id FROM acl_resources_host_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // host exclusions
        $acl['acl_hostexclude'] = $pearDB->fetchFirstColumn(
            'SELECT host_host_id FROM acl_resources_hostex_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // host groups relations
        $acl['acl_hostgroup'] = $pearDB->fetchFirstColumn(
            'SELECT hg_hg_id FROM acl_resources_hg_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // ACL Groups relations
        $acl['acl_groups'] = $pearDB->fetchFirstColumn(
            'SELECT DISTINCT acl_group_id FROM acl_res_group_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // Service categories relations
        $acl['acl_sc'] = $pearDB->fetchFirstColumn(
            'SELECT DISTINCT sc_id FROM acl_resources_sc_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // Host categories relations
        $acl['acl_hc'] = $pearDB->fetchFirstColumn(
            'SELECT DISTINCT hc_id FROM acl_resources_hc_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // Service groups relations
        $acl['acl_sg'] = $pearDB->fetchFirstColumn(
            'SELECT DISTINCT sg_id FROM acl_resources_sg_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // Meta services relations
        $acl['acl_meta'] = $pearDB->fetchFirstColumn(
            'SELECT DISTINCT meta_id FROM acl_resources_meta_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );

        // Image folder relations
        $acl['acl_image_folder'] = $pearDB->fetchFirstColumn(
            'SELECT DISTINCT dir_id FROM acl_resources_image_folder_relations WHERE acl_res_id = :resourceAccessId',
            $queryParameters
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        $exception = new RepositoryException(
            message: 'Error while retrieving ACL information',
            context: ['aclId' => $aclId],
            previous: $e
        );
        ExceptionLogger::create()->log($exception);

        throw $exception;
    }
}

// GET ALL data that will fill the selectors
try {
    $groups = [];
    $accessGroups = $pearDB->fetchAllAssociative('SELECT acl_group_id, acl_group_name FROM acl_groups ORDER BY acl_group_name');

    foreach ($accessGroups as $accessGroup) {
        $groups[$accessGroup['acl_group_id']] = HtmlSanitizer::createfromstring($accessGroup['acl_group_name'])->getstring();
    }

    $pollers = [];
    $monitoringServers = $pearDB->fetchAllAssociative('SELECT id, name FROM nagios_server ORDER BY name');

    foreach ($monitoringServers as $monitoringServer) {
        $pollers[$monitoringServer['id']] = HtmlSanitizer::createfromstring($monitoringServer['name'])->getstring();
    }

    $hosts = $pearDB->fetchAllKeyValue("SELECT host_id, host_name FROM host WHERE host_register = '1' ORDER BY host_name");
    $hostsToExclude = $hosts;
    $hostGroups = $pearDB->fetchAllKeyValue('SELECT hg_id, hg_name FROM hostgroup ORDER BY hg_name');
    $serviceCategories = $pearDB->fetchAllKeyValue('SELECT sc_id, sc_name FROM service_categories ORDER BY sc_name');
    $hostCategories = $pearDB->fetchAllKeyValue('SELECT hc_id, hc_name FROM hostcategories ORDER BY hc_name');
    $serviceGroups = $pearDB->fetchAllKeyValue('SELECT sg_id, sg_name FROM servicegroup ORDER BY sg_name');
    $metaServices = $pearDB->fetchAllKeyValue('SELECT meta_id, meta_name FROM meta_service ORDER BY meta_name');
    $imageFolders = $pearDB->fetchAllKeyValue("SELECT dir_id, dir_name FROM view_img_dir WHERE dir_name NOT IN ('dashboards', 'ppm', 'centreon-map') ORDER BY dir_name");
} catch (ConnectionException $e) {
    $exception = new RepositoryException(
        message: 'Error while retrieving data to fill the selectors for ACL form',
        previous: $e
    );
    ExceptionLogger::create()->log($exception);

    throw $exception;
}

// Var information to format the element
$attrsText = [
    'size' => '30',
];
$attrsText2 = [
    'size' => '60',
];
$attrsAdvSelect = [
    'style' => 'width: 300px; height: 220px;',
];
$attrsTextarea = [
    'rows' => '3',
    'cols' => '80',
];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br />'
. '<br /><br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

// Form begin
$form = new HTML_QuickFormCustom('Form', 'POST', '?p=' . $p);
if ($o == RESOURCE_ACCESS_ADD) {
    $form->addElement('header', 'title', _('Add an ACL'));
} elseif ($o == RESOURCE_ACCESS_MODIFY) {
    $form->addElement('header', 'title', _('Modify an ACL'));
} elseif ($o == RESOURCE_ACCESS_WATCH) {
    $form->addElement('header', 'title', _('View an ACL'));
}

// LCA basic information
$form->addElement('header', 'information', _('General Information'));
$form->addElement('header', 'hostgroups', _('Host Groups Shared'));
$form->addElement('header', 'services', _('Filters'));
$form->addElement('text', 'acl_res_name', _('Access list name'), $attrsText);
$form->addElement('text', 'acl_res_alias', _('Description'), $attrsText2);

$tab = [];
$tab[] = $form->createElement('radio', 'acl_res_activate', null, _('Enabled'), '1');
$tab[] = $form->createElement('radio', 'acl_res_activate', null, _('Disabled'), '0');
$form->addGroup($tab, 'acl_res_activate', _('Status'), '&nbsp;');
$form->setDefaults(['acl_res_activate' => '1']);

// All hosts checkbox definition
$allHosts[] = $form->createElement(
    'checkbox',
    'all_hosts',
    '&nbsp;',
    '',
    ['id' => 'all_hosts', 'onClick' => 'toggleTableDeps(this)']
);
$form->addGroup($allHosts, 'all_hosts', _('Include all hosts'), '&nbsp;&nbsp;');

// All host groups checkbox definition
$allHostgroups[] = $form->createElement(
    'checkbox',
    'all_hostgroups',
    '&nbsp;',
    '',
    ['id' => 'all_hostgroups', 'onClick' => 'toggleTableDeps(this)']
);
$form->addGroup($allHostgroups, 'all_hostgroups', _('Include all hostgroups'), '&nbsp;&nbsp;');

// All service groups checkbox definition
$allServiceGroups[] = $form->createElement(
    'checkbox',
    'all_servicegroups',
    '&nbsp;',
    '',
    ['id' => 'all_servicegroups', 'onClick' => 'toggleTableDeps(this)']
);
$form->addGroup($allServiceGroups, 'all_servicegroups', _('Include all servicegroups'), '&nbsp;&nbsp;');

// All directories (medias) checkbox definition
$allImageFolders[] = $form->createElement(
    'checkbox',
    'all_image_folders',
    '&nbsp;',
    '',
    ['id' => 'all_image_folders', 'onClick' => 'toggleTableDeps(this)', 'checked' => true]
);
$form->addGroup($allImageFolders, 'all_image_folders', _('Include all image folders'), '&nbsp;&nbsp;');

// Contact implied
$form->addElement('header', 'contacts_infos', _('People linked to this Access list'));

$ams1 = $form->addElement(
    'advmultiselect',
    'acl_groups',
    [_('Linked Groups'), _('Available'), _('Selected')],
    $groups,
    $attrsAdvSelect,
    SORT_ASC
);
$ams1->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams1->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams1->setElementTemplate($eTemplate);
echo $ams1->getElementJs(false);

$form->addElement('header', 'Host_infos', _('Shared Resources'));
$form->addElement('header', 'Image_Folder_info', _('Shared image folders'));
$form->addElement('header', 'help', _('Help'));
$form->addElement(
    'header',
    'HSharedExplain',
    _('<b><i>Help :</i></b> Select hosts and hostgroups that can be seen by associated users. '
        . 'You also have the possibility to exclude host(s) from selected hostgroup(s).')
);
$form->addElement(
    'header',
    'SSharedExplain',
    _('<b><i>Help :</i></b> Select services that can be seen by associated users.')
);
$form->addElement(
    'header',
    'MSSharedExplain',
    _('<b><i>Help :</i></b> Select meta services that can be seen by associated users.')
);

$form->addElement(
    'header',
    'ImageFoldersSharedExplain',
    _('<b><i>Help :</i></b> Select image folders that can be seen by associated users.')
);
$form->addElement(
    'header',
    'FilterExplain',
    _('<b><i>Help :</i></b> Select the filter(s) you want to apply to the '
        . 'resource definition for a more restrictive view.')
);

// Pollers
$ams0 = $form->addElement(
    'advmultiselect',
    'acl_pollers',
    [_('Poller Filter'), _('Available'), _('Selected')],
    $pollers,
    $attrsAdvSelect,
    SORT_ASC
);
$ams0->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams0->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams0->setElementTemplate($eTemplate);
echo $ams0->getElementJs(false);

// Hosts
$attrsAdvSelect['id'] = 'hostAdvancedSelect';
$ams2 = $form->addElement(
    'advmultiselect',
    'acl_hosts',
    [_('Hosts'), _('Available'), _('Selected')],
    $hosts,
    $attrsAdvSelect,
    SORT_ASC
);
$ams2->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams2->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams2->setElementTemplate($eTemplate);
echo $ams2->getElementJs(false);

// Host Groups
$attrsAdvSelect['id'] = 'hostgroupAdvancedSelect';
$ams2 = $form->addElement(
    'advmultiselect',
    'acl_hostgroup',
    [_('Host Groups'), _('Available'), _('Selected')],
    $hostGroups,
    $attrsAdvSelect,
    SORT_ASC
);
$ams2->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams2->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams2->setElementTemplate($eTemplate);
echo $ams2->getElementJs(false);

unset($attrsAdvSelect['id']);

$ams2 = $form->addElement(
    'advmultiselect',
    'acl_hostexclude',
    [_('Exclude hosts from selected host groups'), _('Available'), _('Selected')],
    $hostsToExclude,
    $attrsAdvSelect,
    SORT_ASC
);
$ams2->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams2->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams2->setElementTemplate($eTemplate);
echo $ams2->getElementJs(false);

// Service Filters
$ams2 = $form->addElement(
    'advmultiselect',
    'acl_sc',
    [_('Service Category Filter'), _('Available'), _('Selected')],
    $serviceCategories,
    $attrsAdvSelect,
    SORT_ASC
);
$ams2->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams2->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams2->setElementTemplate($eTemplate);
echo $ams2->getElementJs(false);

// Host Filters
$ams2 = $form->addElement(
    'advmultiselect',
    'acl_hc',
    [_('Host Category Filter'), _('Available'), _('Selected')],
    $hostCategories,
    $attrsAdvSelect,
    SORT_ASC
);
$ams2->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams2->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams2->setElementTemplate($eTemplate);
echo $ams2->getElementJs(false);

// Service Groups Add
$attrsAdvSelect['id'] = 'servicegroupAdvancedSelect';
$ams2 = $form->addElement(
    'advmultiselect',
    'acl_sg',
    [_('Service Groups'), _('Available'), _('Selected')],
    $serviceGroups,
    $attrsAdvSelect,
    SORT_ASC
);
$ams2->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams2->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams2->setElementTemplate($eTemplate);
echo $ams2->getElementJs(false);
unset($attrsAdvSelect['id']);

// Meta Services
$ams2 = $form->addElement(
    'advmultiselect',
    'acl_meta',
    [_('Meta Services'), _('Available'), _('Selected')],
    $metaServices,
    $attrsAdvSelect,
    SORT_ASC
);
$ams2->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams2->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams2->setElementTemplate($eTemplate);
echo $ams2->getElementJs(false);

// Images
$attrsAdvSelect['id'] = 'imageFolderAdvancedSelect';
$ams2 = $form->addElement(
    'advmultiselect',
    'acl_image_folder',
    [_('Image folders'), _('Available'), _('Selected')],
    $imageFolders,
    $attrsAdvSelect,
    SORT_ASC
);

$ams2->setButtonAttributes('add', ['value' => _('Add'), 'class' => 'btc bt_success']);
$ams2->setButtonAttributes('remove', ['value' => _('Remove'), 'class' => 'btc bt_danger']);
$ams2->setElementTemplate($eTemplate);
echo $ams2->getElementJs(false);

// Further informations
$form->addElement('header', 'furtherInfos', _('Additional Information'));
$form->addElement('textarea', 'acl_res_comment', _('Comments'), $attrsTextarea);

$form->addElement('hidden', 'acl_res_id');

$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

// Form Rules
$form->applyFilter('__ALL__', 'myTrim');
$form->addRule('acl_res_name', _('Required'), 'required');
$form->registerRule('exist', 'callback', 'testExistence');

if (
    $o === RESOURCE_ACCESS_ADD
    || $o === RESOURCE_ACCESS_MODIFY
) {
    $form->addRule('acl_res_name', _('Already exists'), 'exist');
}
$form->setRequiredNote(_('Required field'));

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate(__DIR__);

$formDefaults = $acl ?? [];
$formDefaults['all_hosts[all_hosts]'] = $formDefaults['all_hosts'] ?? '0';
$formDefaults['all_hostgroups[all_hostgroups]'] = $formDefaults['all_hostgroups'] ?? '0';
$formDefaults['all_servicegroups[all_servicegroups]'] = $formDefaults['all_servicegroups'] ?? '0';

// By default we want this to be checked
$formDefaults['all_image_folders[all_image_folders]'] = $formDefaults['all_image_folders'] ?? '1';

if ($o === RESOURCE_ACCESS_WATCH) {
    $form->addElement('button', 'change', _('Modify'), ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&acl_id=' . $aclId . "'", 'class' => 'btc bt_success']);
    $form->setDefaults($formDefaults);
    $form->freeze();
} elseif ($o === RESOURCE_ACCESS_MODIFY) {
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Delete'), ['class' => 'btc bt_danger']);
    $form->setDefaults($formDefaults);
} elseif ($o === RESOURCE_ACCESS_ADD) {
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Delete'), ['class' => 'btc bt_danger']);
}
$tpl->assign('msg', ['changeL' => 'main.php?p=' . $p . '&o=c&lca_id=' . $aclId, 'changeT' => _('Modify')]);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
if ($form->validate()) {
    $aclObj = $form->getElement('acl_res_id');
    if ($form->getSubmitValue('submitA')) {
        try {
            $aclObj->setValue(insertLCAInDB());
        } catch (RepositoryException $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: 'Error while inserting ACL: ' . $e->getMessage(),
                exception: $e,
            );

            throw $e;
        }
    } elseif ($form->getSubmitValue('submitC')) {
        try {
            updateLCAInDB($aclObj->getValue());
        } catch (RepositoryException $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: 'Error while updating ACL: ' . $e->getMessage(),
                customContext: ['aclId' => $aclObj->getValue()],
                exception: $e,
            );

            throw $e;
        }
    }
    require_once 'listsResourcesAccess.php';
} else {
    $action = $form->getSubmitValue('action');
    if ($valid && $action['action']) {
        require_once 'listsResourcesAccess.php';
    } else {
        // Apply a template definition
        $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
        $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
        $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
        $form->accept($renderer);
        $tpl->assign('form', $renderer->toArray());
        $tpl->assign('o', $o);
        $tpl->assign('sort1', _('General Information'));
        $tpl->assign('sort2', _('Host Resources'));
        $tpl->assign('sort3', _('Service Resources'));
        $tpl->assign('sort4', _('Meta Services'));
        $tpl->assign('sort5', _('Filters'));
        $tpl->assign('sort6', _('Image folders'));
        $tpl->display('formResourcesAccess.ihtml');
    }
}
?>
<script type='text/javascript'>
    function toggleTableDeps(element) {
        jQuery(element).parents('td.FormRowValue:first').children('table').toggle(
            !jQuery(element).is(':checked')
        );
    }

    jQuery(() => {
        toggleTableDeps(jQuery('#all_hosts'));
        toggleTableDeps(jQuery('#all_hostgroups'));
        toggleTableDeps(jQuery('#all_servicegroups'));
        toggleTableDeps(jQuery('#all_image_folders'));
    });
</script>
