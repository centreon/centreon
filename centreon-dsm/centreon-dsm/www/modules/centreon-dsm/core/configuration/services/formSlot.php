<?php
/*
 * Copyright 2005-2009 MERETHIS
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
 * As a special exception, the copyright holders of this program give MERETHIS 
 * permission to link this program with independent modules to produce an executable, 
 * regardless of the license terms of these independent modules, and to copy and 
 * distribute the resulting executable under terms of MERETHIS choice, provided that 
 * MERETHIS also meet, for each linked independent module, the terms  and conditions 
 * of the license of that module. An independent module is a module which is not 
 * derived from this program. If you modify this program, you may extend this 
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 * 
 * For more information : pool@centreon.com
 * 
 * SVN : $URL: http://svn.centreon.com/branches/centreon-2.1/www/include/configuration/configObject/pool/formpool.php $
 * SVN : $Id: formpool.php 10131 2010-03-03 16:11:57Z shotamchay $
 * 
 */
	
	if (!isset($oreon))
		exit(); 

	ini_set("display_errors", "On");

	$pool = array();
	if (($o == "c" || $o == "w") && $slot_id)	{
		$DBRESULT =& $pearDB->query("SELECT * FROM mod_dsm_pool WHERE pool_id = '".$slot_id."' LIMIT 1");
		$pool = array_map("myDecode", $DBRESULT->fetchRow());
				
		/*
		 * Set Service Notification Options
		 */
		$tmp = explode(',', $pool["pool_notif_options"]);
		foreach ($tmp as $key => $value)
			$pool["pool_notif_options"][trim($value)] = 1;
		$DBRESULT->free();
		
		/*
		 * Contact Groups
		 */
		$pool["pool_cg"] = array();
		$DBRESULT =& $pearDB->query("SELECT cg_cg_id, pool_id FROM mod_dsm_cg_relation WHERE pool_id = '$slot_id'");
		while ($data =& $DBRESULT->fetchRow())
			$pool["pool_cg"][$data["cg_cg_id"]] = 1;
		$DBRESULT->free();
		unset($data);
		
		$pool["pool_cct"] = array();
		$DBRESULT =& $pearDB->query("SELECT cct_cct_id, pool_id FROM mod_dsm_cct_relation WHERE pool_id = '$slot_id'");
		while ($data =& $DBRESULT->fetchRow())
			$pool["pool_cct"][$data["cct_cct_id"]] = 1;
		$DBRESULT->free();
		unset($data);
	}
	
	/*
	 * Get Langs
	 */
	$langs = array();
	$langs = getLangs();
	
	/*
	 * Timeperiods comes from DB -> Store in $notifsTps Array
	 * When we make a massive change, give the possibility to not crush value
	 */
	$notifTps = array(NULL => NULL);
	$DBRESULT =& $pearDB->query("SELECT tp_id, tp_name FROM timeperiod ORDER BY tp_name");
	while($notifTp =& $DBRESULT->fetchRow())
		$notifTps[$notifTp["tp_id"]] = $notifTp["tp_name"];
	$DBRESULT->free();

	/*
	 * Commands
	 */
	$Cmds = array();
	$DBRESULT =& $pearDB->query("SELECT command_id, command_name FROM command WHERE command_type = '2' ORDER BY command_name");
	while ($Cmd =& $DBRESULT->fetchRow())
		$Cmds[$Cmd["command_id"]] = $Cmd["command_name"];
	$DBRESULT->free();
	unset($Cmd);

	/*
	 * pool contactgroups
	 */
	$notifCgs = array();
	$DBRESULT =& $pearDB->query("SELECT cg_id, cg_name FROM contactgroup ORDER BY cg_name");
	while ($notifCg =& $DBRESULT->fetchRow())
		$notifCgs[$notifCg["cg_id"]] = $notifCg["cg_name"];
	$DBRESULT->free();
	
	/*
	 * pool contacts
	 */
	$notifCcts = array();
	$DBRESULT =& $pearDB->query("SELECT contact_id, contact_name FROM contact ORDER BY contact_name");
	while($data =& $DBRESULT->fetchRow())
		$notifCcts[$data["contact_id"]] = $data["contact_name"];
	$DBRESULT->free();
	unset($data);
	
	/*
	 * pool hosts
	 */
	$poolHost = array();
	$DBRESULT =& $pearDB->query("SELECT host_id, host_name FROM host WHERE host_register = '1' ORDER BY host_name");
	while ($data =& $DBRESULT->fetchRow())
		$poolHost[$data["host_id"]] = $data["host_name"];
	$DBRESULT->free();
	
	/*
	 * pool service_template
	 */
	$poolST = array(NULL => NULL);
	$DBRESULT =& $pearDB->query("SELECT service_id, service_description FROM service WHERE service_register = '0' ORDER BY service_description");
	while ($data =& $DBRESULT->fetchRow()) {
		$data["service_description"] = str_replace("#S#", "/", $data["service_description"]);
		$data["service_description"] = str_replace("#BS#", "\\", $data["service_description"]);
		$poolST[$data["service_id"]] = $data["service_description"];
	}
	$DBRESULT->free();

	/*
	 * Template / Style for Quickform input
	 */
	$attrsText 		= array("size"=>"30");
	$attrsTextSmall	= array("size"=>"10");
	$attrsText2 	= array("size"=>"60");
	$attrsAdvSelect = array("style" => "width: 300px; height: 100px;");
	$attrsTextarea 	= array("rows"=>"5", "cols"=>"40");
	$template 		= "<table><tr><td>{unselected}</td><td align='center'>{add}<br /><br /><br />{remove}</td><td>{selected}</td></tr></table>";

	/*
	 * Form begin
	 */
	$form = new HTML_QuickForm('Form', 'post', "?p=".$p);
	if ($o == "a")
		$form->addElement('header', 'title', _("Add a pool"));
	else if ($o == "c")
		$form->addElement('header', 'title', _("Modify a pool"));
	else if ($o == "w")
		$form->addElement('header', 'title', _("View a pool"));

	/*
	 * pool basic information
	 */
	$form->addElement('header', 'information', _("General Information"));
	$form->addElement('header', 'slotInformation', _("Slots Information"));
	$form->addElement('header', 'Notification', _("Notifications Information"));
	
	
	/*
	 * No possibility to change name and alias, because there's no interest
	 */
	$form->addElement('text', 'pool_name', _("Name"), $attrsText);
	$form->addElement('text', 'pool_description', _("Description"), $attrsText);
	$form->addElement('text', 'pool_number', _("Number of Slots"), $attrsTextSmall);
	$form->addElement('text', 'pool_prefix', _("Slot name prefix"), $attrsText);
	$form->addElement('select', 'pool_host_id', _("Host Name"), $poolHost);
	$form->addElement('select', 'pool_cmd_id', _("Check commands"), $Cmds);
	$form->addElement('text', 'pool_args', _("arguments"), $attrsText2);
 	$form->addElement('select', 'pool_tp_id', _("Check Period"), $notifTps);
 	$form->addElement('select', 'pool_service_template_id', _("Service template based"), $poolST);

	/*
	 * pool Groups Field
	 */	
	$ams3 =& $form->addElement('advmultiselect', 'pool_cg', _("Contact groups linked"), $notifCgs, $attrsAdvSelect);
	$ams3->setButtonAttributes('add', array('value' =>  _("Add")));
	$ams3->setButtonAttributes('remove', array('value' => _("Delete")));
	$ams3->setElementTemplate($template);
	echo $ams3->getElementJs(false);
	
	$ams3 =& $form->addElement('advmultiselect', 'pool_cct', _("Contact linked"), $notifCcts, $attrsAdvSelect);
	$ams3->setButtonAttributes('add', array('value' =>  _("Add")));
	$ams3->setButtonAttributes('remove', array('value' => _("Delete")));
	$ams3->setElementTemplate($template);
	echo $ams3->getElementJs(false);

	/*
	 * pool Oreon information
	 */
	$form->addElement('header', 'oreon', _("Centreon"));
	
	//$form->addElement('select', 'pool_type_msg', _("Mail Type"), array(NULL=>NULL, "txt"=>"txt", "html"=>"html", "pdf"=>"pdf"));
	/*
	 * Notification informations
	 */
	$form->addElement('header', 'notification', _("Notification Type"));

	/*
	 * notifications Options
	 */
	$svNotifOpt[] = &HTML_QuickForm::createElement('checkbox', 'w', '&nbsp;', _("Warning"), array('id' => 'sWarning', 'onClick' => 'uncheckAllS(this);'));
	$svNotifOpt[] = &HTML_QuickForm::createElement('checkbox', 'u', '&nbsp;', _("Unknown"), array('id' => 'sUnknown', 'onClick' => 'uncheckAllS(this);'));
	$svNotifOpt[] = &HTML_QuickForm::createElement('checkbox', 'c', '&nbsp;', _("Critical"), array('id' => 'sCritical', 'onClick' => 'uncheckAllS(this);'));
	$svNotifOpt[] = &HTML_QuickForm::createElement('checkbox', 'r', '&nbsp;', _("Recovery"), array('id' => 'sRecovery', 'onClick' => 'uncheckAllS(this);'));
	$svNotifOpt[] = &HTML_QuickForm::createElement('checkbox', 'f', '&nbsp;', _("Flapping"), array('id' => 'sFlapping', 'onClick' => 'uncheckAllS(this);'));
	$svNotifOpt[] = &HTML_QuickForm::createElement('checkbox', 's', '&nbsp;', _("Downtime Scheduled"), array('id' => 'sScheduled', 'onClick' => 'uncheckAllS(this);'));
	$svNotifOpt[] = &HTML_QuickForm::createElement('checkbox', 'n', '&nbsp;', _("None"), array('id' => 'sNone', 'onClick' => 'uncheckAllS(this);'));
	$form->addGroup($svNotifOpt, 'pool_notif_options', _("Notification Options"), '&nbsp;&nbsp;');
    
    $form->addElement('select', 'pool_tp_id2', _("Notification Period"), $notifTps);
 	$form->addElement('text', 'pool_notif_interval', _("Notification interval"), $attrsTextSmall);
 	
	/*
	 * Further informations
	 */
	$form->addElement('header', 'furtherInfos', _("Additional Information"));
	$poolActivation[] = &HTML_QuickForm::createElement('radio', 'pool_activate', null, _("Enabled"), '1');
	$poolActivation[] = &HTML_QuickForm::createElement('radio', 'pool_activate', null, _("Disabled"), '0');
	$form->addGroup($poolActivation, 'pool_activate', _("Status"), '&nbsp;');
	$form->setDefaults(array('pool_activate' => '1'));
	
	$tab = array();
	$tab[] = &HTML_QuickForm::createElement('radio', 'action', null, _("List"), '1');
	$tab[] = &HTML_QuickForm::createElement('radio', 'action', null, _("Form"), '0');
	$form->addGroup($tab, 'action', _("Post Validation"), '&nbsp;');
	$form->setDefaults(array('action'=>'1'));

	$form->addElement('hidden', 'pool_id');
	$redirect =& $form->addElement('hidden', 'o');
	$redirect->setValue($o);
	if (is_array($select))	{
		$select_str = NULL;
		foreach ($select as $key => $value) {
			$select_str .= $key.",";			
		}
		$select_pear =& $form->addElement('hidden', 'select');
		$select_pear->setValue($select_str);
	}
	
	/*
	 * Form Rules
	 */
	function myReplace()	{
		global $form;
		$ret = $form->getSubmitValues();
		return (str_replace(" ", "_", $ret["pool_name"]));
	}
	$form->applyFilter('__ALL__', 'myTrim');
	$form->applyFilter('pool_name', 'myReplace');
	$from_list_menu = false;
	if ($o != "mc")	{
		$form->addRule('pool_name', _("Compulsory Name"), 'required');
		$form->addRule('pool_host_id', _("Compulsory Alias"), 'required');
		$form->addRule('pool_prefix', _("Compulsory Alias"), 'required');
		$form->addRule('pool_number', _("Compulsory Alias"), 'required');
		$form->addRule('pool_tp_id', _("Compulsory Alias"), 'required');
	} else if ($o == "mc")	{
		if ($form->getSubmitValue("submitMC"))
			$from_list_menu = false;
		else
			$from_list_menu = true;
	}
	$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _("Required fields"));


	/*
	 * Smarty template Init
	 */
	$tpl = new Smarty();
	$tpl = initSmartyTpl($path, $tpl);

	if ($o == "w")	{
		# Just watch a pool information
		$form->addElement("button", "change", _("Modify"), array("onClick"=>"javascript:window.location.href='?p=".$p."&o=c&pool_id=".$pool_id."'"));
	    $form->setDefaults($pool);
		$form->freeze();
	} else if ($o == "c")	{
		# Modify a pool information
		$subC =& $form->addElement('submit', 'submitC', _("Save"));
		$res =& $form->addElement('reset', 'reset', _("Reset"));
	    $form->setDefaults($pool);
	} else if ($o == "a")	{
		# Add a pool information	
		$subA =& $form->addElement('submit', 'submitA', _("Save"));
		$res =& $form->addElement('reset', 'reset', _("Reset"));
	}

	$valid = false;
	if ($form->validate() && $from_list_menu == false)	{
		$poolObj =& $form->getElement('pool_id');
		if ($form->getSubmitValue("submitA"))
			$poolObj->setValue(insertpoolInDB());
		else if ($form->getSubmitValue("submitC"))
			updatePoolInDB($poolObj->getValue());
		$o = NULL;
		$form->addElement("button", "change", _("Modify"), array("onClick"=>"javascript:window.location.href='?p=".$p."&o=c&pool_id=".$poolObj->getValue()."'"));
		$form->freeze();
		$valid = true;
	}
	$action = $form->getSubmitValue("action");
	if ($valid && $action["action"]["action"])
		require_once($path."listSlot.php");
	else	{
		/*
		 * Apply a template definition
		 */
		$renderer =& new HTML_QuickForm_Renderer_ArraySmarty($tpl);
		$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
		$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
		$form->accept($renderer);
		$tpl->assign('form', $renderer->toArray());
		$tpl->assign('o', $o);
		$tpl->display("formSlot.ihtml");
	}
?>