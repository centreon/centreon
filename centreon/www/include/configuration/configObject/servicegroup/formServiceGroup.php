<?php

/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (! isset($centreon)) {
    exit();
}

const BASE_ROUTE = './include/common/webServices/rest/internal.php';

if (! $centreon->user->admin) {
    if ($serviceGroupId && ! str_contains($sgString, "'" . $serviceGroupId . "'")) {
        $msg = new CentreonMsg();
        $msg->setImage('./img/icons/warning.png');
        $msg->setTextStyle('bold');
        $msg->setText(_('You are not allowed to access this service group'));

        return;
    }
}

$initialValues = ['sg_hServices' => [], 'sg_hgServices' => []];

// Database retrieve information for ServiceGroup
$sg = [];
$hServices = [];
if (($o === SERVICE_GROUP_MODIFY || $o === SERVICE_GROUP_WATCH) && $serviceGroupId) {
    $DBRESULT = $pearDB->prepare('SELECT * FROM servicegroup WHERE sg_id = :sg_id LIMIT 1');
    $DBRESULT->bindValue(':sg_id', $serviceGroupId, PDO::PARAM_INT);
    $DBRESULT->execute();

    // Set base value
    $sg = array_map('myDecode', $DBRESULT->fetchRow());
}

$attrsText = ['size' => '30'];
$attrsAdvSelect = ['style' => 'width: 400px; height: 250px;'];
$attrsTextarea = ['rows' => '5', 'cols' => '40'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br />'
    . '<br /><br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list';
$attrServices = [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $route,
    'multiple' => true,
    'linkedObject' => 'centreonService',
];
$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_servicetemplate&action=list&l=1';
$attrServicetemplates = [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $route,
    'multiple' => true,
    'linkedObject' => 'centreonServicetemplates',
    'defaultDatasetOptions' => ['withHosttemplate' => true],
];
$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list&t=hostgroup';
$attrHostgroups = [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $route,
    'multiple' => true,
    'linkedObject' => 'centreonHostgroups',
];

//
// # Form begin
//
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
if ($o === SERVICE_GROUP_ADD) {
    $form->addElement('header', 'title', _('Add a Service Group'));
} elseif ($o === SERVICE_GROUP_MODIFY) {
    $form->addElement('header', 'title', _('Modify a Service Group'));
} elseif ($o === SERVICE_GROUP_WATCH) {
    $form->addElement('header', 'title', _('View a Service Group'));
}

//
// # Contact basic information
//
$form->addElement('header', 'information', _('General Information'));
$form->addElement('text', 'sg_name', _('Name'), $attrsText);
$form->addElement('text', 'sg_alias', _('Description'), $attrsText);

$form->registerRule('validate_geo_coords', 'function', 'validateGeoCoords');
$form->addElement('text', 'geo_coords', _('Geo coordinates'), $attrsText);
$form->addRule('geo_coords', _('geo coords are not valid'), 'validate_geo_coords');

$form->addElement('header', 'relation', _('Relations'));

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_service'
    . '&action=defaultValues&target=servicegroups&field=sg_hServices&id=' . $serviceGroupId;
$attrService1 = array_merge(
    $attrServices,
    ['defaultDatasetRoute' => $route]
);
$form->addElement('select2', 'sg_hServices', _('Linked Host Services'), [], $attrService1);

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_service'
    . '&action=defaultValues&target=servicegroups&field=sg_hgServices&id=' . $serviceGroupId;
$attrHostgroup1 = array_merge(
    $attrHostgroups,
    ['defaultDatasetRoute' => $route]
);
$form->addElement('select2', 'sg_hgServices', _('Linked Host Group Services'), [], $attrHostgroup1);

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_servicetemplate'
    . '&action=defaultValues&target=servicegroups&field=sg_tServices&id=' . $serviceGroupId;
$attrServicetemplate1 = array_merge(
    $attrServicetemplates,
    ['defaultDatasetRoute' => $route]
);
$form->addElement('select2', 'sg_tServices', _('Linked Service Templates'), [], $attrServicetemplate1);

// Further informations
$form->addElement('header', 'furtherInfos', _('Additional Information'));
$sgActivation[] = $form->createElement('radio', 'sg_activate', null, _('Enabled'), '1');
$sgActivation[] = $form->createElement('radio', 'sg_activate', null, _('Disabled'), '0');
$form->addGroup($sgActivation, 'sg_activate', _('Status'), '&nbsp;');
$form->setDefaults(['sg_activate' => '1']);

$form->addElement('textarea', 'sg_comment', _('Comments'), $attrsTextarea);

if (
    $o === SERVICE_GROUP_ADD
    && $isCloudPlatform === true
) {
    $form->addElement(
        'select2',
        'resource_access_rules',
        _('Resource Access Rule'),
        [],
        [
            'datasourceOrigin' => 'ajax',
            'availableDatasetRoute' => BASE_ROUTE . '?object=centreon_administration_aclgroup&action=list&use_ram=true',
            'defaultDataset' => [],
            'multiple' => true,
        ]
    );
    $form->addRule('resource_access_rules', _('Mandatory field for ACL purpose.'), 'required');
}


$form->addElement('hidden', 'sg_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$init = $form->addElement('hidden', 'initialValues');
$init->setValue(serialize($initialValues));

// Form Rules
function myReplace()
{
    global $form;
    $ret = $form->getSubmitValues();

    return str_replace(' ', '_', $ret['sg_name']);
}

$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('sg_name', 'myReplace');
$form->addRule('sg_name', _('Compulsory Name'), 'required');
$form->addRule('sg_alias', _('Compulsory Description'), 'required');
$form->registerRule('exist', 'callback', 'testServiceGroupExistence');
$form->addRule('sg_name', _('Name is already in use'), 'exist');
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Just watch a Service Group information
if ($o === SERVICE_GROUP_WATCH) {
    if ($centreon->user->access->page($p) !== 2) {
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&sg_id=' . $serviceGroupId . "'"]
        );
    }
    $form->setDefaults($sg);
    $form->freeze();
} elseif ($o === SERVICE_GROUP_MODIFY) { // Modify a Service Group information
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($sg);
} elseif ($o === SERVICE_GROUP_ADD) { // Add a Service Group information
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
}

$tpl->assign('nagios', $oreon->user->get_version());
$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, "orange", '
    . 'TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], WIDTH, -300, '
    . 'SHADOW, true, TEXTALIGN, "justify"'
);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
if ($form->validate()) {
    $sgObj = $form->getElement('sg_id');
    if ($form->getSubmitValue('submitA')) {
        $sgObj->setValue(insertServiceGroupInDB(isCloudPlatform: $isCloudPlatform));
    } elseif ($form->getSubmitValue('submitC')) {
        updateServiceGroupInDB(
            isCloudPlatform: $isCloudPlatform,
            serviceGroupId: $sgObj->getValue()
        );
    }
    $o = null;
    $valid = true;
}
$action = $form->getSubmitValue('action');

if ($valid) {
    require_once $path . 'listServiceGroup.php';
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    if ($isCloudPlatform) {
        $tpl->assign('resourceAccess', _('Resource Access Management'));
    }
    $tpl->display('formServiceGroup.ihtml');
}
