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

if (!isset($centreon)) {
    exit();
}
		
require_once _CENTREON_PATH_ . 'www/class/centreonLDAP.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonContactgroup.class.php';

/* Init connection to storage db */
require_once _CENTREON_PATH_ . "/www/class/centreonBroker.class.php";
$brk = new CentreonBroker($pearDB);
if ($brk->getBroker() == 'broker') {
    $pearDBMonitoring = new CentreonDB('centstorage');
} else {
    $pearDBMonitoring = new CentreonDB('ndo');
}

/* hosts */
$hosts = $acl->getHostAclConf(
    null,
    $oreon->broker->getBroker(),
    array(
        'fields'  => array('host.host_id', 'host.host_name'),
        'keys'    => array('host_id'),
        'get_row' => 'host_name',
        'order'   => array('host.host_name')
    )
);

/* notification contact groups */
$notifCgs = array();
$cg = new CentreonContactgroup($pearDB);
if ($oreon->user->admin) {
    $notifCgs = $cg->getListContactgroup(true);
} else {
    $cgAcl = $acl->getContactGroupAclConf(
        array(
            'fields'  => array('cg_id', 'cg_name'),
            'get_row' => 'cg_name',
            'keys'    => array('cg_id'),
            'order'   => array('cg_name')
        )
    );
    $cgLdap = $cg->getListContactgroup(true, true);
    $notifCgs = array_intersect_key($cgLdap, $cgAcl);
}
    
/*
 * Database retrieve information for Escalation
 */
$initialValues = array();
$esc = array();
if (($o == "c" || $o == "w") && $esc_id) {
	$DBRESULT = $pearDB->query("SELECT * FROM escalation WHERE esc_id = '".$esc_id."' LIMIT 1");

	# Set base value
	$esc = array_map("myDecode", $DBRESULT->fetchRow());

	# Set Host Options
	$esc["escalation_options1"] = explode(',', $esc["escalation_options1"]);
	foreach ($esc["escalation_options1"] as $key => $value) {
		$esc["escalation_options1"][trim($value)] = 1;
    }

	# Set Service Options
	$esc["escalation_options2"] = explode(',', $esc["escalation_options2"]);
	foreach ($esc["escalation_options2"] as $key => $value) {
		$esc["escalation_options2"][trim($value)] = 1;
    }

	# Set Host Groups relations
	$DBRESULT = $pearDB->query("SELECT DISTINCT hostgroup_hg_id FROM escalation_hostgroup_relation WHERE escalation_esc_id = '".$esc_id."'");
	for($i = 0; $hg = $DBRESULT->fetchRow(); $i++) {
        if (!$oreon->user->admin && false === strpos($hgString, "'".$hg['hostgroup_hg_id']."'")) {
            $initialValues['esc_hgs'][] = $hg["hostgroup_hg_id"];
        } else {
            $esc["esc_hgs"][$i] = $hg["hostgroup_hg_id"];
        }
    }
	$DBRESULT->free();

	# Set Service Groups relations
	$DBRESULT = $pearDB->query("SELECT DISTINCT servicegroup_sg_id FROM escalation_servicegroup_relation WHERE escalation_esc_id = '".$esc_id."'");
	for($i = 0; $sg = $DBRESULT->fetchRow(); $i++) {
        if (!$oreon->user->admin && false === strpos($sgString, "'".$sg['servicegroup_sg_id']."'")) {
            $initialValues['esc_sgs'][] = $sg["servicegroup_sg_id"];
        } else {
            $esc["esc_sgs"][$i] = $sg["servicegroup_sg_id"];
        }
    }
	$DBRESULT->free();

	# Set Host relations
	$DBRESULT = $pearDB->query("SELECT DISTINCT host_host_id FROM escalation_host_relation WHERE escalation_esc_id = '".$esc_id."'");
	for ($i = 0; $host = $DBRESULT->fetchRow(); $i++) {
        if (!$oreon->user->admin && !isset($hosts[$host['host_host_id']])) {
            $initialValues['esc_hosts'][] = $host['host_host_id'];
        } else {
            $esc["esc_hosts"][$i] = $host["host_host_id"];
        }
    }
	$DBRESULT->free();

	# Set Meta Service
    $aclMetaService = $acl->getMetaServiceString();
	$DBRESULT = $pearDB->query("SELECT DISTINCT emsr.meta_service_meta_id FROM escalation_meta_service_relation emsr WHERE emsr.escalation_esc_id = '".$esc_id."'");
	for($i = 0; $metas = $DBRESULT->fetchRow(); $i++) {
        if (!$oreon->user->admin && false === strpos($aclMetaService, "'".$metas['meta_service_meta_id']."'")) {
            $initialValues['esc_metas'][] = $metas['meta_service_meta_id'];
        } else {
            $esc["esc_metas"][$i] = $metas["meta_service_meta_id"];
        }
    }
	$DBRESULT->free();

	# Set Host Service
    $aclService = $acl->getServicesString('ID', $pearDBMonitoring);
    $query = "SELECT distinct host_host_id, host_name, service_service_id, service_description
        FROM service s, escalation_service_relation esr, host h
        WHERE s.service_id = esr.service_service_id
        AND esr.host_host_id = h.host_id
        AND h.host_register = '1'
        AND esr.escalation_esc_id = " . $esc_id;
    $DBRESULT = $pearDB->query($query);
	for ($i = 0; $services = $DBRESULT->fetchRow(); $i++) {
        $key = $services["host_host_id"]."-".$services["service_service_id"];
        if (!$oreon->user->admin && false === strpos($aclService, "'".$services['service_service_id']."'")) {
            $initialValues['esc_hServices'][] = $key;
        } else {
            $hServices[$key] = $services["host_name"]."&nbsp;-&nbsp;".$services['service_description'];
            $esc["esc_hServices"][$i] = $key;
        }
    }
	$DBRESULT->free();

	# Set Contact Groups relations
	$DBRESULT = $pearDB->query("SELECT DISTINCT contactgroup_cg_id FROM escalation_contactgroup_relation WHERE escalation_esc_id = '".$esc_id."'");
	for ($i = 0; $cg = $DBRESULT->fetchRow(); $i++) {
        if (!isset($oreon->user->admin) && !isset($notifCgs[$cg['contactgroup_cg_id']])) {
            $initialValues["esc_cgs"][] = $cg["contactgroup_cg_id"];
        } else {
            $esc["esc_cgs"][$i] = $cg["contactgroup_cg_id"];
        }
    }
	$DBRESULT->free();
}


/*
 * Database retrieve information for differents elements list we need on the page
 */

#
# Host comes from DB -> Store in $hosts Array
$hosts = array();
$DBRESULT = $pearDB->query("SELECT host_id, host_name FROM host WHERE host_register = '1' ORDER BY host_name");
while($host = $DBRESULT->fetchRow()) {
	$hosts[$host["host_id"]] = $host["host_name"];
}
$DBRESULT->free();

# Meta Services comes from DB -> Store in $metas Array
$metas = array();
$DBRESULT = $pearDB->query(
    "SELECT meta_id, meta_name 
    FROM meta_service ".
    $acl->queryBuilder("WHERE", "meta_id", $acl->getMetaServiceString()).
    " ORDER BY meta_name"
);
while ($meta = $DBRESULT->fetchRow()) {
	$metas[$meta["meta_id"]] = $meta["meta_name"];
}
$DBRESULT->free();

# Contact Groups comes from DB -> Store in $cgs Array
$cgs = array();
$cg = new CentreonContactgroup($pearDB);
$cgs = $cg->getListContactgroup(true);

# TimePeriods comes from DB -> Store in $tps Array
$tps = array();
$DBRESULT = $pearDB->query("SELECT tp_id, tp_name FROM timeperiod ORDER BY tp_name");
while ($tp = $DBRESULT->fetchRow()) {
	$tps[$tp["tp_id"]] = $tp["tp_name"];
}
$DBRESULT->free();

#
# End of "database-retrieved" information
##########################################################
##########################################################
# Var information to format the element
#
$attrsText 		= array("size"=>"30");
$attrsText2 	= array("size"=>"10");
$attrsAdvSelect = array("style" => "width: 300px; height: 150px;");
$attrsAdvSelect2 = array("style" => "width: 300px; height: 400px;");
$attrsTextarea 	= array("rows"=>"3", "cols"=>"30");
$eTemplate	= '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br /><br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

#
## Form begin
#
$form = new HTML_QuickForm('Form', 'post', "?p=".$p);
if ($o == "a") {
	$form->addElement('header', 'title', _("Add an Escalation"));
} elseif ($o == "c") {
	$form->addElement('header', 'title', _("Modify an Escalation"));
} elseif ($o == "w") {
	$form->addElement('header', 'title', _("View an Escalation"));
}

#
## Escalation basic information
#
$form->addElement('header', 'information', _("Information"));
$form->addElement('text', 'esc_name', _("Escalation Name"), $attrsText);
$form->addElement('text', 'esc_alias', _("Alias"), $attrsText);
$form->addElement('text', 'first_notification', _("First Notification"), $attrsText2);
$form->addElement('text', 'last_notification', _("Last Notification"), $attrsText2);
$form->addElement('text', 'notification_interval', _("Notification Interval"), $attrsText2);
$form->addElement('select2', 'escalation_period', _("Escalation Period"), $tps);
$tab = array();
$tab[] = HTML_QuickForm::createElement('checkbox', 'd', '&nbsp;', _("Down"));
$tab[] = HTML_QuickForm::createElement('checkbox', 'u', '&nbsp;', _("Unreachable"));
$tab[] = HTML_QuickForm::createElement('checkbox', 'r', '&nbsp;', _("Recovery"));
$form->addGroup($tab, 'escalation_options1', _("Hosts Escalation Options"), '&nbsp;&nbsp;');
$tab = array();
$tab[] = HTML_QuickForm::createElement('checkbox', 'w', '&nbsp;', _("Warning"));
$tab[] = HTML_QuickForm::createElement('checkbox', 'u', '&nbsp;', _("Unknown"));
$tab[] = HTML_QuickForm::createElement('checkbox', 'c', '&nbsp;', _("Critical"));
$tab[] = HTML_QuickForm::createElement('checkbox', 'r', '&nbsp;', _("Recovery"));
$form->addGroup($tab, 'escalation_options2', _("Services Escalation Options"), '&nbsp;&nbsp;');
$form->addElement('textarea', 'esc_comment', _("Comments"), $attrsTextarea);

$form->addElement('select2', 'esc_cgs', _("Linked Contact Groups"), $cgs, array('multiple' => true));

#
## Sort 2
#
$form->addElement('header', 'hosts', _("Implied Hosts"));
$form->addElement('select2', 'esc_hosts', _("Hosts"), $hosts, array('multiple' => true));

#
## Sort 3
#
$form->addElement('header', 'services', _("Implied Services"));
$attrServices = array(
    'datasourceOrigin' => 'ajax',
    'defaultDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=defaultEscalationValues&id=' . $esc_id,
    'availableDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list',
    'multiple' => true
);
$form->addElement('select2', 'esc_hServices', _("Services by Host"), array(), $attrServices);

#
## Sort 4
#
$form->addElement('header', 'hgs', _("Implied Host Groups"));
$form->addElement('select2', 'esc_hgs', _("Host Group"), $hgs, array('multiple' => true));

#
## Sort 5
#
$form->addElement('header', 'metas', _("Implied Meta Services"));
$form->addElement('select2', 'esc_metas', _("Meta Service"), $metas, array('multiple' => true));


#
## Sort 6
#
$form->addElement('header', 'sgs', _("Implied Service Groups"));
$form->addElement('select2', 'esc_sgs', _("Service Group"), $sgs, array('multiple' => true));

$form->addElement('hidden', 'esc_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);
    
$init = $form->addElement('hidden', 'initialValues');
$init->setValue(serialize($initialValues));
    
#
## Form Rules
#
$form->applyFilter('__ALL__', 'myTrim');
$form->addRule('esc_name', _("Compulsory Name"), 'required');
$form->addRule('first_notification', _("Required Field"), 'required');
$form->addRule('last_notification', _("Required Field"), 'required');
$form->addRule('notification_interval', _("Required Field"), 'required');
$form->addRule('esc_cgs', _("Required Field"), 'required');
$form->addRule('dep_hostChilds', _("Required Field"), 'required');
$form->registerRule('exist', 'callback', 'testExistence');
$form->addRule('esc_name', _("Name is already in use"), 'exist');
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;". _("Required fields"));

#
##End of form definition
#

# Smarty template Init
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

# Just watch an Escalation information
if ($o == "w")	{
	if ($centreon->user->access->page($p) != 2) {
		$form->addElement(
            "button",
            "change",
            _("Modify"),
            array("onClick"=>"javascript:window.location.href='?p=".$p."&o=c&esc_id=".$esc_id."'")
        );
    }
    $form->setDefaults($esc);
	$form->freeze();
} elseif ($o == "c") { # Modify an Escalation information
	$subC = $form->addElement('submit', 'submitC', _("Save"), array("class" => "btc bt_success"));
	$res = $form->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
    $form->setDefaults($esc);
} elseif ($o == "a") { # Add an Escalation information
	$subA = $form->addElement('submit', 'submitA', _("Save"), array("class" => "btc bt_success"));
	$res = $form->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
}

$tpl->assign("sort1", _("Information"));
$tpl->assign("sort2", _("Hosts Escalation"));
$tpl->assign("sort3", _("Services Escalation"));
$tpl->assign("sort4", _("Hostgroups Escalation"));
$tpl->assign("sort5", _("Meta Services Escalation"));
$tpl->assign("sort6", _("Servicegroups Escalation"));

$tpl->assign('time_unit', " * ".$oreon->optGen["interval_length"]." "._("seconds"));

$tpl->assign(
    "helpattr",
    'TITLE, "'._("Help").'", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, "orange",'
    . ' TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], WIDTH, -300,'
    . ' SHADOW, true, TEXTALIGN, "justify"'
);
# prepare help texts
$helptext = "";
include_once("help.php");
foreach ($help as $key => $text) {
	$helptext .= '<span style="display:none" id="help:'.$key.'">'.$text.'</span>'."\n";
}
$tpl->assign("helptext", $helptext);

$valid = false;
if ($form->validate())	{
	$escObj = $form->getElement('esc_id');
	if ($form->getSubmitValue("submitA")) {
		$escObj->setValue(insertEscalationInDB());
    } elseif ($form->getSubmitValue("submitC")) {
		updateEscalationInDB($escObj->getValue("esc_id"));
    }
	$o = NULL;
	$valid = true;
}
if ($valid) {
	require_once("listEscalation.php");
} else {
	#Apply a template definition
	$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
	$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
	$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
	$form->accept($renderer);
	$tpl->assign('form', $renderer->toArray());
	$tpl->assign('o', $o);
	$tpl->display("formEscalation.ihtml");
}
?>
