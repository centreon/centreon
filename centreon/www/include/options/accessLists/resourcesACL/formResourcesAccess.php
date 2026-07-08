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
$aclResourceName = isset($acl['acl_res_name'])
    ? CentreonUtils::escapeAll($acl['acl_res_name'])
    : '';
if ($o == RESOURCE_ACCESS_ADD) {
    $form->addElement('header', 'title', _('Resources ACL'));
} elseif ($o == RESOURCE_ACCESS_MODIFY || $o == RESOURCE_ACCESS_WATCH) {
    $form->addElement('header', 'title', _('Resources ACL') . ' - ' . $aclResourceName);
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
    _('Include all hosts'),
    ['id' => 'all_hosts', 'onClick' => 'toggleTableDeps(this)']
);
$form->addGroup($allHosts, 'all_hosts', _('Include all hosts'), '&nbsp;&nbsp;');

// All host groups checkbox definition
$allHostgroups[] = $form->createElement(
    'checkbox',
    'all_hostgroups',
    '&nbsp;',
    _('Include all hostgroups'),
    ['id' => 'all_hostgroups', 'onClick' => 'toggleTableDeps(this)']
);
$form->addGroup($allHostgroups, 'all_hostgroups', _('Include all hostgroups'), '&nbsp;&nbsp;');

// All service groups checkbox definition
$allServiceGroups[] = $form->createElement(
    'checkbox',
    'all_servicegroups',
    '&nbsp;',
    _('Include all servicegroups'),
    ['id' => 'all_servicegroups', 'onClick' => 'toggleTableDeps(this)']
);
$form->addGroup($allServiceGroups, 'all_servicegroups', _('Include all servicegroups'), '&nbsp;&nbsp;');

// All directories (medias) checkbox definition
$allImageFolders[] = $form->createElement(
    'checkbox',
    'all_image_folders',
    '&nbsp;',
    _('Include all image folders'),
    ['id' => 'all_image_folders', 'onClick' => 'toggleTableDeps(this)', 'checked' => true]
);
$form->addGroup($allImageFolders, 'all_image_folders', _('Include all image folders'), '&nbsp;&nbsp;');

// Contact implied
$form->addElement('header', 'contacts_infos', _('People linked to this Access list'));

$wsRoute = './include/common/webServices/rest/internal.php?object=%s&action=list';
$form->addElement('select2', 'acl_groups', _('Linked Access Groups'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_administration_aclgroup'),
    'multiple' => true,
    'linkedObject' => 'centreonAclGroup',
]);

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
$form->addElement('select2', 'acl_pollers', _('Poller Filter'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_configuration_poller'),
    'multiple' => true,
    'linkedObject' => 'centreonInstance',
]);

// Hosts
$form->addElement('select2', 'acl_hosts', _('Hosts'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_configuration_host'),
    'multiple' => true,
    'linkedObject' => 'centreonHost',
]);

// Host Groups
$form->addElement('select2', 'acl_hostgroup', _('Host Groups'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_configuration_hostgroup'),
    'multiple' => true,
    'linkedObject' => 'centreonHostgroups',
]);

// Hosts to exclude from the selected host groups
$form->addElement('select2', 'acl_hostexclude', _('Exclude hosts from selected host groups'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_configuration_host'),
    'multiple' => true,
    'linkedObject' => 'centreonHost',
]);

// Service Filters
$form->addElement('select2', 'acl_sc', _('Service Category Filter'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_configuration_servicecategory'),
    'multiple' => true,
    'linkedObject' => 'centreonServicecategories',
]);

// Host Filters
$form->addElement('select2', 'acl_hc', _('Host Category Filter'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_configuration_hostcategory'),
    'multiple' => true,
    'linkedObject' => 'centreonHostcategories',
]);

// Service Groups
$form->addElement('select2', 'acl_sg', _('Service Groups'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_configuration_servicegroup'),
    'multiple' => true,
    'linkedObject' => 'centreonServicegroups',
]);

// Meta Services
$form->addElement('select2', 'acl_meta', _('Meta Services'), [], [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => sprintf($wsRoute, 'centreon_configuration_meta'),
    'multiple' => true,
    'linkedObject' => 'centreonMeta',
]);

// Images (no ajax datasource exists for media directories, use a static select2;
// its selected labels are rebuilt from $imageFolders further down, see $formDefaults).
$form->addElement('select2', 'acl_image_folder', _('Image folders'), $imageFolders, [
    'multiple' => true,
]);

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

// Performance / safety on large estates: when an "Include all" flag is on, do not
// preload the individual selection into its picker. Resolving tens of thousands of
// pre-selected options (e.g. 50k hosts) would make the page extremely heavy. The
// picker is left empty and the template shows a lightweight "--- All ... ---" hint.
if ((int) $formDefaults['all_hosts[all_hosts]'] === 1) {
    $formDefaults['acl_hosts'] = [];
}
if ((int) $formDefaults['all_hostgroups[all_hostgroups]'] === 1) {
    $formDefaults['acl_hostgroup'] = [];
}
if ((int) $formDefaults['all_servicegroups[all_servicegroups]'] === 1) {
    $formDefaults['acl_sg'] = [];
}
if ((int) $formDefaults['all_image_folders[all_image_folders]'] === 1) {
    $formDefaults['acl_image_folder'] = [];
}

// The image folder picker uses a static select2 (no ajax datasource exists for media
// directories). Rebuild its default as [label => id] so the selected folders render
// with their names instead of raw ids.
if (! empty($formDefaults['acl_image_folder'])) {
    $labeledImageFolders = [];
    foreach ($formDefaults['acl_image_folder'] as $dirId) {
        if ((int) $dirId > 0 && isset($imageFolders[$dirId])) {
            $labeledImageFolders[$imageFolders[$dirId]] = $dirId;
        }
    }
    $formDefaults['acl_image_folder'] = $labeledImageFolders;
}

if ($o === RESOURCE_ACCESS_WATCH) {
    $form->addElement('button', 'change', _('Modify'), ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&acl_id=' . $aclId . "'", 'class' => 'btc bt_success']);
    $form->setDefaults($formDefaults);
    $form->freeze();
} elseif ($o === RESOURCE_ACCESS_MODIFY) {
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($formDefaults);
} elseif ($o === RESOURCE_ACCESS_ADD) {
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
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
        $tpl->assign('sort2', _('Hosts'));
        $tpl->assign('sort3', _('Services'));
        $tpl->assign('sort4', _('Meta Services'));
        $tpl->assign('sort5', _('Filters'));
        $tpl->assign('sort6', _('Image folders'));
        $tpl->display('formResourcesAccess.ihtml');
    }
}
?>
<script type='text/javascript'>
    function toggleTableDeps(element) {
        // When the matching "include all" checkbox is ticked, hide the individual
        // picker entirely and show a lightweight "--- All ... ---" placeholder
        // instead. This keeps the page fast on large estates (the picker is also
        // left empty server-side, so nothing heavy is loaded or rendered).
        var on = jQuery(element).is(':checked');
        var row = jQuery(element).closest('.cf-row');
        row.find('.cf-field').toggle(!on);
        row.find('.cf-all-placeholder').toggle(on);
    }

    jQuery(() => {
        toggleTableDeps(jQuery('#all_hosts'));
        toggleTableDeps(jQuery('#all_hostgroups'));
        toggleTableDeps(jQuery('#all_servicegroups'));
        toggleTableDeps(jQuery('#all_image_folders'));
    });
</script>
