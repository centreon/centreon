<?php
/**
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
 * SVN : $URL$
 * SVN : $Id$
 *
 */

if (!isset($centreon)) {
    exit();
}

/**
 * GetUserAclAllowedResources returns allowed resources for the user regarding resource type
 *
 * @param CentreonACL $userAcl
 * @param string $resourceType
 */
function getUserAclAllowedResources(CentreonACL $userAcl, string $resourceType)
{
    return match ($resourceType) {
        'hosts' => $userAcl->getHostAclConf(null, 'broker'),
        'servicegroups' => $userAcl->getServiceGroupAclConf(null, 'broker'),
        'hostgroups' => $userAcl->getHostGroupAclConf(null, 'broker'),
    };
}

/**
 * Check resources access regarding selected resources
 *
 * @param CentreonACL $userAcl
 * @param int[] $selectedResources
 * @param string $resourceType
 * @return bool
 **/
function checkResourcesRelations(CentreonACL $userAcl, array $selectedResources, string $resourceType): bool
{
    $allowedResources = getUserAclAllowedResources($userAcl, $resourceType);

    $selectedResourceIds = array_map(
        static fn (array $resource) => $resource['id'],
        $selectedResources
    );

    $diff = array_diff($selectedResourceIds, array_keys($allowedResources));

    if ($diff === []) {
        return true;
    }

    foreach ($selectedResources as $resource) {
        if (
            in_array($resource['id'], $diff)
            && $resource['activated'] === '1'
        ) {
            return false;
        }
    }

    return true;
}

/*
 * QuickForm Rules
 */
function testDowntimeNameExistence($downtimeName = null)
{
    global $pearDB, $form;

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('dt_id');
    }
    $res = $pearDB->query("SELECT dt_id FROM downtime WHERE dt_name = '" . $pearDB->escape($downtimeName) . "'");
    $d = $res->fetchRow();
    $nbRes = $res->rowCount();
    if ($nbRes && $d["dt_id"] == $id) {
        return true;
    } elseif ($nbRes && $d["dt_id"] != $id) {
        return false;
    }
    return true;
}

if (($o == 'c' || $o == 'w') && isset($_GET['dt_id'])) {
    $id = filter_var($_GET['dt_id'], FILTER_VALIDATE_INT);
} else {
    $o = 'a';
}

/*
 * Var information to format the element
 */
$attrsText = array("size" => "30");
$attrsText2 = array("size" => "6");
$attrsTextLong = array("size" => "70");
$attrsAdvSelect_small = array("style" => "width: 270px; height: 70px;");
$attrsAdvSelect = array("style" => "width: 270px; height: 100px;");
$attrsAdvSelect_big = array("style" => "width: 270px; height: 200px;");
$attrsTextarea = array("rows" => "5", "cols" => "40");
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br />' .
    '<br /><br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

$hostsRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=list';
$attrHosts = array(
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $hostsRoute,
    'multiple' => true,
    'linkedObject' => 'centreonHost',
    'showDisabled' => true
);
$hostgroupsRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_hostgroup&action=list';
$attrHostgroups = array(
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $hostgroupsRoute,
    'multiple' => true,
    'linkedObject' => 'centreonHostgroups',
    'showDisabled' => true
);
$servicesRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list';
$attrServices = array(
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $servicesRoute,
    'multiple' => true,
    'linkedObject' => 'centreonService',
    'showDisabled' => true
);
$servicegroupsRoute = './include/common/webServices/rest/internal.php' .
    '?object=centreon_configuration_servicegroup&action=list';
$attrServicegroups = array(
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $servicegroupsRoute,
    'multiple' => true,
    'linkedObject' => 'centreonServicegroups',
    'showDisabled' => true
);

/*
 * Init QuickFrom
 */
$form = new HTML_QuickFormCustom('form_dt', 'post', "?p=$p");
if ($o == "a") {
    $form->addElement('header', 'title', _("Add a downtime"));
} elseif ($o == "c") {
    $form->addElement('header', 'title', _("Modify a downtime"));
} elseif ($o == "w") {
    $form->addElement('header', 'title', _("View a downtime"));
}


$form->addElement('header', 'periods', _("Periods"));

/*
 * Tab 1
 */
$form->addElement('header', 'information', _("General Information"));
$form->addElement('header', 'linkManagement', _("Links Management"));
$form->addElement('text', 'downtime_name', _("Name"), $attrsText);
$form->addElement('text', 'downtime_description', _("Alias"), $attrsTextLong);

$donwtime_activate[] = $form->createElement('radio', 'downtime_activate', null, _("Yes"), '1');
$donwtime_activate[] = $form->createElement('radio', 'downtime_activate', null, _("No"), '0');
$form->addGroup($donwtime_activate, 'downtime_activate', _("Enable"), '&nbsp;');
$form->setDefaults(array('downtime_activate' => '1'));

$page = $form->addElement('hidden', 'p');
$page->setValue($p);
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);
$form->addElement('hidden', 'dt_id');

/*
 * Tab 2
 * Hosts
 */
$routeAttrHosts = './include/common/webServices/rest/internal.php?object=centreon_configuration_host' .
    '&action=defaultValues&target=downtime&field=host_relation&id=' . $downtime_id;
$attrHost1 = array_merge(
    $attrHosts,
    array('defaultDatasetRoute' => $routeAttrHosts)
);

$form->addElement('select2', 'host_relation', _("Linked with Hosts"), array(), $attrHost1);

/*
 * Hostgroups
 */
$routeAttrHostgroup = './include/common/webServices/rest/internal.php?object=centreon_configuration_hostgroup' .
    '&action=defaultValues&target=downtime&field=hostgroup_relation&id=' . $downtime_id;
$attrHostgroup1 = array_merge(
    $attrHostgroups,
    array('defaultDatasetRoute' => $routeAttrHostgroup),
);
$form->addElement('select2', 'hostgroup_relation', _("Linked with Host Groups"), array(), $attrHostgroup1);

/*
 * Service
 */
$routeAttrService = './include/common/webServices/rest/internal.php?object=centreon_configuration_service' .
    '&action=defaultValues&target=downtime&field=svc_relation&id=' . $downtime_id;
$attrService1 = array_merge(
    $attrServices,
    array('defaultDatasetRoute' => $routeAttrService)
);
$form->addElement('select2', 'svc_relation', _("Linked with Services"), array(), $attrService1);

/*
 * Servicegroups
 */
$routeAttrServicegroup = './include/common/webServices/rest/internal.php?object=centreon_configuration_servicegroup' .
    '&action=defaultValues&target=downtime&field=svcgroup_relation&id=' . $downtime_id;
$attrServicegroup1 = array_merge(
    $attrServicegroups,
    array('defaultDatasetRoute' => $routeAttrServicegroup)
);
$form->addElement('select2', 'svcgroup_relation', _("Linked with Service Groups"), array(), $attrServicegroup1);

$form->addRule('downtime_name', _("Name"), 'required');
$form->registerRule('exist', 'callback', 'testDowntimeNameExistence');
$form->addRule('downtime_name', _("Name is already in use"), 'exist');

$form->setRequiredNote("<i class='red'>*</i>&nbsp;" . _("Required fields"));

if ($o == "c" || $o == 'w') {
    $infos = $downtime->getInfos($id);
    $relations = $downtime->getRelations((int) $id);
    $extractRelationId = static fn(array $item): string => (string) ($item['id'] ?? '');
    $default_dt = [
        'dt_id' => $id,
        'downtime_name' => $infos['name'],
        'downtime_description' => $infos['description'],
        'downtime_activate' => $infos['activate'],
        'host_relation' => array_map($extractRelationId, $relations['hosts']),
        'hostgroup_relation' => array_map($extractRelationId, $relations['hostgroups']),
        'svc_relation' => array_map($extractRelationId, $relations['services']),
        'svcgroup_relation' => array_map($extractRelationId, $relations['servicegroups']),
    ];
}


/*
 * Smarty template Init
 */
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

/**
 * $o parameter possible values
 *
 * $o = w - Watch the recurrent downtime = no possible edit
 * $o = c - Edit the recurrent downtime
 * $o = a - Add a recurrent downtime
 */
if ($o == "w") {
    if (!$min && $centreon->user->access->page($p) != 2) {
        $form->addElement("button", "change", _("Modify"), array(
            "onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&dt_id=" . $id . "'",
            "class" => "btc bt_default"
        ));
    }
    $form->setDefaults($default_dt);
    $form->freeze();
} elseif ($o == "c") {
    /**
     * Only search for ACL if the user is not admin
     */
    $userId = $centreon->user->user_id;
    $userIsAdmin = $centreon->user->admin;

    if ($userIsAdmin !== '1') {
        require_once _CENTREON_PATH_ . "/www/class/centreonACL.class.php";
        $userAcl = new CentreonACL($userId, $userIsAdmin);

        if (
            ! checkResourcesRelations($userAcl, $default_dt['host_relation'], 'hosts')
            || ! checkResourcesRelations($userAcl, $default_dt['hostgroup_relation'], 'hostgroups')
            || ! checkResourcesRelations($userAcl, $default_dt['svcgroup_relation'], 'servicegroups')
        ) {
            $form->addElement('text', 'msgacl', _("error"), 'error');
            $form->freeze();
        }
    }

    $subC = $form->addElement(
        'button',
        'submitC',
        _("Save"),
        array("onClick" => "validForm();", "class" => "btc bt_success")
    );
    $res = $form->addElement(
        'button',
        'reset',
        _("Reset"),
        array("onClick" => "history.go(0);", "class" => "btc bt_default")
    );
    $form->setDefaults($default_dt);
} elseif ($o == "a") {
    $subA = $form->addElement(
        'button',
        'submitA',
        _("Save"),
        array("onClick" => "validForm();", "class" => "btc bt_success")
    );
    $res = $form->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
}

$tpl->assign("sort1", _("Downtime Configuration"));
$tpl->assign("sort2", _("Relations"));
$tpl->assign("periods", _("Periods"));
$tpl->assign("period", _("Period"));
$tpl->assign("add", _("Add new period"));

/*
 * prepare help texts
 */
$helptext = "";
include_once("help.php");
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign("helptext", $helptext);

$valid = false;

if ($form->validate()) {
    $values = $form->getSubmitValues();
    $valid = true;
    foreach ($values['periods'] as $periods) {
        $time_end_period = strtotime($periods['end_period']);
        if ($periods['end_period'] == '24:00') {
            $time_end_period = strtotime('00:00') + 3600 * 24; // Fix with 00:00 and 24 h for with before 5.3
        }
        if (strtotime($periods['start_period']) > $time_end_period) {
            $valid = false;
            $tpl->assign('period_err', _("The end time must be greater than the start time."));
        }
    }
    /** validate that at least one relation has been configured */
    if (
        (! isset($values['host_relation']) || count($values['host_relation']) === 0)
        && (! isset($values['hostgroup_relation']) || count($values['hostgroup_relation']) === 0)
        && (! isset($values['svc_relation']) || count($values['svc_relation']) === 0)
        && (! isset($values['svcgroup_relation']) || count($values['svcgroup_relation']) === 0)
    ) {
        $valid = false;
        $tpl->assign('msg_err', _('No relation set for this downtime'));
    }
    if ($valid) {
        if ($values['o'] == 'a') {
            $activate = $values['downtime_activate']['downtime_activate'];
            $id = $downtime->add($values['downtime_name'], $values['downtime_description'], $activate);
            if (false !== $id) {
                foreach ($values['periods'] as $periods) {
                    $downtime->addPeriod($id, $periods);
                }
                if (isset($values['host_relation'])) {
                    $downtime->addRelations($id, $values['host_relation'], 'host');
                }
                if (isset($values['hostgroup_relation'])) {
                    $downtime->addRelations($id, $values['hostgroup_relation'], 'hostgrp');
                }
                if (isset($values['svc_relation'])) {
                    $downtime->addRelations($id, $values['svc_relation'], 'svc');
                }
                if (isset($values['svcgroup_relation'])) {
                    $downtime->addRelations($id, $values['svcgroup_relation'], 'svcgrp');
                }
                $o = "w";
                $form->addElement(
                    "button",
                    "change",
                    _("Modify"),
                    array("onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&dt_id=" . $id . "'")
                );
                $form->freeze();
                $valid = true;
            }
        } elseif ($values['o'] == 'c') {
            $id = $values['dt_id'];
            $activate = $values['downtime_activate']['downtime_activate'];
            $downtime->modify($id, $values['downtime_name'], $values['downtime_description'], $activate);
            $downtime->deletePeriods($id);
            foreach ($values['periods'] as $periods) {
                $downtime->addPeriod($id, $periods);
            }
            $downtime->deteleRelations($id);
            if (isset($values['host_relation'])) {
                $downtime->addRelations($id, $values['host_relation'], 'host');
            }
            if (isset($values['hostgroup_relation'])) {
                $downtime->addRelations($id, $values['hostgroup_relation'], 'hostgrp');
            }
            if (isset($values['svc_relation'])) {
                $downtime->addRelations($id, $values['svc_relation'], 'svc');
            }
            if (isset($values['svcgroup_relation'])) {
                $downtime->addRelations($id, $values['svcgroup_relation'], 'svcgrp');
            }
            $o = "w";
            $form->addElement(
                "button",
                "change",
                _("Modify"),
                array("onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&dt_id=" . $id . "'")
            );
            $form->freeze();
            $valid = true;
        }
    }

    if ($valid) {
        require_once($path . "listDowntime.php");
    }

    if (!$valid) {
        $form->setDefaults($values);
    }
}
if (!$valid) {
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<i class="red">*</i>');
    $renderer->setErrorTemplate('<i class="red">{$error}</i><br />{$html}');
    if ($o == 'w') {
        $tpl->assign("time_period", _("Time period"));
        $tpl->assign("days", _("Days"));
        $tpl->assign("seconds", _("Seconds"));
        $tpl->assign("downtime_type", _("Downtime type"));
        $tpl->assign("fixed", _("Fixed"));
        $tpl->assign("flexible", _("Flexible"));
        $tpl->assign("weekly_basis", _("Weekly basis"));
        $tpl->assign("monthly_basis", _("Monthly basis"));
        $tpl->assign("specific_date", _("Specific date"));
        $tpl->assign("week_days", array(
            1 => _("Monday"),
            2 => _("Tuesday"),
            3 => _("Wednesday"),
            4 => _("Thursday"),
            5 => _("Friday"),
            6 => _("Saturday"),
            7 => _("Sunday")
        ));
        $tpl->assign('periods_tab', $downtime->getPeriods($id));
    }

    $tpl->assign('msg_err_norelation', addslashes(_('No relation set for this downtime')));

    $form->accept($renderer);
    $tpl->assign('o', $o);
    $tpl->assign('p', $p);
    $tpl->assign('form', $renderer->toArray());

    $tpl->display("formDowntime.html");
}
