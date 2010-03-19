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
 * For more information : contact@centreon.com
 * 
 * SVN : $URL: http://svn.centreon.com/branches/centreon-2.1/www/include/configuration/configObject/contact/DB-Func.php $
 * SVN : $Id: DB-Func.php 9503 2009-12-02 13:00:38Z jmathis $
 * 
 */

	if (!isset ($oreon))
		exit ();

	function testContactExistence ($name = NULL)	{
		global $pearDB, $form;
		$id = NULL;
		if (isset($form))
			$id = $form->getSubmitValue('contact_id');
		$DBRESULT =& $pearDB->query("SELECT contact_name, contact_id FROM contact WHERE contact_name = '".htmlentities($name, ENT_QUOTES)."'");
		$contact =& $DBRESULT->fetchRow();
		#Modif case
		if ($DBRESULT->numRows() >= 1 && $contact["contact_id"] == $id)
			return true;
		#Duplicate entry
		else if ($DBRESULT->numRows() >= 1 && $contact["contact_id"] != $id)
			return false;
		else
			return true;
	}

	function testAliasExistence ($alias = NULL)	{
		global $pearDB, $form;
		$id = NULL;
		if (isset($form))
			$id = $form->getSubmitValue('contact_id');
		$DBRESULT =& $pearDB->query("SELECT contact_alias, contact_id FROM contact WHERE contact_alias = '".htmlentities($alias, ENT_QUOTES)."'");
		$contact =& $DBRESULT->fetchRow();
		#Modif case
		if ($DBRESULT->numRows() >= 1 && $contact["contact_id"] == $id)
			return true;
		#Duplicate entry
		else if ($DBRESULT->numRows() >= 1 && $contact["contact_id"] != $id)
			return false;
		else
			return true;
	}

	function keepOneContactAtLeast()	{
		global $pearDB, $form;
		$DBRESULT =& $pearDB->query("SELECT COUNT(*) AS nbr_valid FROM contact WHERE contact_activate = '1' AND contact_oreon = '1'");
		if (isset($form))
			$cct_oreon = $form->getSubmitValue('contact_oreon');
		else
			$cct_oreon["contact_oreon"] = 0;
		if (isset($form))
			$cct_activate = $form->getSubmitValue('contact_activate');
		else
			$cct_activate["contact_activate"] = 0;
		$contact = $DBRESULT->fetchRow();
		if ($contact["nbr_valid"] == 1 && ($cct_oreon["contact_oreon"] == 0 || $cct_activate["contact_activate"] == 0))
			return false;
		return true;
	}

	function enableContactInDB ($contact_id = null, $contact_arr = array())	{
		global $pearDB, $oreon;
		
		if (!$contact_id && !count($contact_arr)) 
			return;
		if ($contact_id)
			$contact_arr = array($contact_id=>"1");
		foreach($contact_arr as $key=>$value)	{
			$DBRESULT =& $pearDB->query("UPDATE contact SET contact_activate = '1' WHERE contact_id = '".$key."'");
			$DBRESULT2 =& $pearDB->query("SELECT contact_name FROM `contact` WHERE `contact_id` = '".$key."' LIMIT 1");
			$row = $DBRESULT2->fetchRow();
			$oreon->CentreonLogAction->insertLog("contact", $key, $row['contact_name'], "enable");
		}
	}

	function disableContactInDB ($contact_id = null, $contact_arr = array())	{
		if (!$contact_id && !count($contact_arr)) return;
		global $pearDB, $oreon;
		if ($contact_id)
			$contact_arr = array($contact_id=>"1");
		foreach($contact_arr as $key=>$value)	{
			if (keepOneContactAtLeast())	{
				$DBRESULT =& $pearDB->query("UPDATE contact SET contact_activate = '0' WHERE contact_id = '".$key."'");
				$DBRESULT2 =& $pearDB->query("SELECT contact_name FROM `contact` WHERE `contact_id` = '".$key."' LIMIT 1");
				$row = $DBRESULT2->fetchRow();
				$oreon->CentreonLogAction->insertLog("contact", $key, $row['contact_name'], "disable");
			}
		}
	}

	function deleteContactInDB ($contacts = array())	{
		global $pearDB, $oreon;
		foreach($contacts as $key=>$value)	{
			$DBRESULT2 =& $pearDB->query("SELECT contact_name FROM `contact` WHERE `contact_id` = '".$key."' LIMIT 1");
			$row = $DBRESULT2->fetchRow();
			
			$DBRESULT =& $pearDB->query("DELETE FROM contact WHERE contact_id = '".$key."'");
			$oreon->CentreonLogAction->insertLog("contact", $key, $row['contact_name'], "d");
		}
	}


	function updatePoolInDB($pool_id = NULL) {
		global $form;
		
		if (!$pool_id) 
			return;
			
		$ret = $form->getSubmitValues();
		# Global function to use
		updateContact($pool_id);
	}
	
	function insertContactInDB ($ret = array())	{
		$contact_id = insertContact($ret);
		updateContactHostCommands($contact_id, $ret);
		updateContactServiceCommands($contact_id, $ret);
		updateContactContactGroup($contact_id, $ret);
		return ($contact_id);
	}

	function insertContact($ret = array())	{
		global $form, $pearDB, $oreon, $encryptType;
	
		if (!count($ret))
			$ret = $form->getSubmitValues();
		
		$rq = "INSERT INTO `contact` ( " .
				"`contact_id` , `timeperiod_tp_id` , `timeperiod_tp_id2` , `contact_name` , " .
				"`contact_alias` , `contact_passwd` , `contact_lang` , " .
				"`contact_host_notification_options` , `contact_service_notification_options` , " .
				"`contact_email` , `contact_pager` , `contact_comment` , `contact_oreon` , " .
				"`contact_admin` , `contact_type_msg`, `contact_activate`, `contact_auth_type`, " .
				"`contact_ldap_dn`, `contact_location` )" .
				"VALUES ( ";
		$rq .= "NULL, ";
		isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != NULL ? $rq .= "'".$ret["timeperiod_tp_id"]."', ": $rq .= "NULL, ";
		isset($ret["timeperiod_tp_id2"]) && $ret["timeperiod_tp_id2"] != NULL ? $rq .= "'".$ret["timeperiod_tp_id2"]."', ": $rq .= "NULL, ";
		isset($ret["contact_name"]) && $ret["contact_name"] != NULL ? $rq .= "'".htmlentities($ret["contact_name"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["contact_alias"]) && $ret["contact_alias"] != NULL ? $rq .= "'".htmlentities($ret["contact_alias"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		if ($encryptType == 1)
			isset($ret["contact_passwd"]) && $ret["contact_passwd"] != NULL ? $rq .= "'".md5($ret["contact_passwd"])."', ": $rq .= "NULL, ";
		else if ($encryptType == 2)
			isset($ret["contact_passwd"]) && $ret["contact_passwd"] != NULL ? $rq .= "'".sha1($ret["contact_passwd"])."', ": $rq .= "NULL, ";
		else
			isset($ret["contact_passwd"]) && $ret["contact_passwd"] != NULL ? $rq .= "'".md5($ret["contact_passwd"])."', ": $rq .= "NULL, ";
				
		isset($ret["contact_lang"]) && $ret["contact_lang"] != NULL ? $rq .= "'".htmlentities($ret["contact_lang"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["contact_hostNotifOpts"]) && $ret["contact_hostNotifOpts"] != NULL ? $rq .= "'".implode(",", array_keys($ret["contact_hostNotifOpts"]))."', ": $rq .= "NULL, ";
		isset($ret["contact_svNotifOpts"]) && $ret["contact_svNotifOpts"] != NULL ? $rq .= "'".implode(",", array_keys($ret["contact_svNotifOpts"]))."', ": $rq .= "NULL, ";
		isset($ret["contact_email"]) && $ret["contact_email"] != NULL ? $rq .= "'".htmlentities($ret["contact_email"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["contact_pager"]) && $ret["contact_pager"] != NULL ? $rq .= "'".htmlentities($ret["contact_pager"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["contact_comment"]) && $ret["contact_comment"] != NULL ? $rq .= "'".htmlentities($ret["contact_comment"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		if (isset($_POST["contact_select"]) && isset($_POST["contact_select"]["select"]))
			$rq .= "'1', ";
		else
			isset($ret["contact_oreon"]["contact_oreon"]) && $ret["contact_oreon"]["contact_oreon"] != NULL ? $rq .= "'".$ret["contact_oreon"]["contact_oreon"]."', ": $rq .= " '1', ";
		isset($ret["contact_admin"]["contact_admin"]) && $ret["contact_admin"]["contact_admin"] != NULL ? $rq .= "'".$ret["contact_admin"]["contact_admin"]."', ": $rq .= "NULL, ";
		isset($ret["contact_type_msg"]) && $ret["contact_type_msg"] != NULL ? $rq .= "'".$ret["contact_type_msg"]."', ": $rq .= "NULL, ";
		isset($ret["contact_activate"]["contact_activate"]) && $ret["contact_activate"]["contact_activate"] != NULL ? $rq .= "'".$ret["contact_activate"]["contact_activate"]."', ": $rq .= "NULL, ";
		isset($ret["contact_auth_type"]) && $ret["contact_auth_type"] != NULL ? $rq .= "'".$ret["contact_auth_type"]."', ": $rq .= "'local', ";
		isset($ret["contact_ldap_dn"]) && $ret["contact_ldap_dn"] != NULL ? $rq .= "'".htmlentities(str_replace("\\", "\\\\", $ret["contact_ldap_dn"]), ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["contact_location"]) && $ret["contact_location"] != NULL ? $rq .= "'".$ret["contact_location"]."' ": $rq .= "NULL ";
		$rq .= ")";
		
		$DBRESULT =& $pearDB->query($rq);
		$DBRESULT =& $pearDB->query("SELECT MAX(contact_id) FROM contact");
		$contact_id = $DBRESULT->fetchRow();
		return ($contact_id["MAX(contact_id)"]);
	}

	function updateContact($pool_id = null) {
		global $form, $pearDB, $oreon, $encryptType;

		if (!$pool_id) 
			return;

		$ret = array();
		$ret = $form->getSubmitValues();
print_r($ret);
		
		$notif_options = "";
		foreach ($ret["pool_notif_options"] as $type => $flag) {
			if ($flag == 1) {
				if (strlen($notif_options) != 0)
					$notif_options .= ",";
				$notif_options .= $type;
			}
		}

		$rq = "UPDATE mod_dsm_pool SET ";
		$rq .=	"pool_name = ";
		isset($ret["pool_name"]) && $ret["pool_name"] != NULL ? $rq .= "'".htmlentities($ret["pool_name"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_description = ";
		isset($ret["pool_description"]) && $ret["pool_description"] != NULL ? $rq .= "'".htmlentities($ret["pool_description"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_host_id = ";
		isset($ret["pool_host_id"]) && $ret["pool_host_id"] != NULL ? $rq .= "'".htmlentities($ret["pool_host_id"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_number = ";
		isset($ret["pool_number"]) && $ret["pool_number"] != NULL ? $rq .= "'".htmlentities($ret["pool_number"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_prefix = ";
		isset($ret["pool_prefix"]) && $ret["pool_prefix"] != NULL ? $rq .= "'".htmlentities($ret["pool_prefix"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_cmd_id = ";
		isset($ret["pool_cmd_id"]) && $ret["pool_cmd_id"] != NULL ? $rq .= "'".htmlentities($ret["pool_cmd_id"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_args = ";
		isset($ret["pool_args"]) && $ret["pool_args"] != NULL ? $rq .= "'".htmlentities($ret["pool_args"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_tp_id = ";
		isset($ret["pool_tp_id"]) && $ret["pool_tp_id"] != NULL ? $rq .= "'".htmlentities($ret["pool_tp_id"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_activate = ";
		isset($ret["pool_activate"]["pool_activate"]) && $ret["pool_activate"]["pool_activate"] != NULL ? $rq .= "'".htmlentities($ret["pool_activate"]["pool_activate"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_tp_id2 = ";
		isset($ret["pool_tp_id2"]) && $ret["pool_tp_id2"] != NULL ? $rq .= "'".htmlentities($ret["pool_tp_id2"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_notif_interval = ";
		isset($ret["pool_notif_interval"]) && $ret["pool_notif_interval"] != NULL ? $rq .= "'".htmlentities($ret["pool_notif_interval"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_service_template_id = ";
		isset($ret["pool_service_template_id"]) && $ret["pool_service_template_id"] != NULL ? $rq .= "'".htmlentities($ret["pool_service_template_id"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_notif_options = '$notif_options' ";

		$rq .= "WHERE pool_id = '".$pool_id."'";
		print $rq;
		$DBRESULT =& $pearDB->query($rq);
	}

	function updateContactContactGroup($contact_id = null, $ret = array())	{
		global $form, $pearDB;
		if (!$contact_id) 
			return;
		$rq = "DELETE FROM contactgroup_contact_relation ";
		$rq .= "WHERE contact_contact_id = '".$contact_id."'";
		$DBRESULT =& $pearDB->query($rq);
		if (isset($ret["contact_cgNotif"]))
			$ret = $ret["contact_cgNotif"];
		else
			$ret = $form->getSubmitValue("contact_cgNotif");
		for ($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO contactgroup_contact_relation ";
			$rq .= "(contact_contact_id, contactgroup_cg_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$contact_id."', '".$ret[$i]."')";
			$DBRESULT =& $pearDB->query($rq);
		}
	}
?>