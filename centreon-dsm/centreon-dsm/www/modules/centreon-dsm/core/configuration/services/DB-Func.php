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

	function enableContactInDB ($pool_id = null, $pool_arr = array())	{
		global $pearDB;

		if (!$pool_id && !count($pool_arr)) 
			return;

		if ($pool_id)
			$pool_arr = array($pool_id => "1");
		
		foreach ($pool_arr as $key => $value) {
			$DBRESULT =& $pearDB->query("UPDATE mod_dsm_pool SET pool_activate = '1' WHERE pool_id = '".$key."'");
		}
	}

	function disableContactInDB ($pool_id = null, $pool_arr = array())	{
		global $pearDB;

		if (!$pool_id && !count($pool_arr)) 
			return;

		if ($pool_id)
			$pool_arr = array($pool_id => "1");
		
		foreach ($pool_arr as $key => $value) {
			$DBRESULT =& $pearDB->query("UPDATE mod_dsm_pool SET pool_activate = '0' WHERE pool_id = '".$key."'");
		}
	}

	function deleteContactInDB ($pools = array())	{
		global $pearDB;

		foreach ($pools as $key => $value) {
			$DBRESULT =& $pearDB->query("DELETE FROM mod_dsm_pool WHERE pool_id = '".$key."'");
		}
	}


	function updatePoolInDB($pool_id = NULL) {
		global $form;
		
		if (!$pool_id) 
			return;
			
		$ret = $form->getSubmitValues();

		/*
		 * Global function to use
		 */
		updatePool($pool_id);
	}
	
	function insertPoolInDB ($ret = array())	{
		$pool_id = insertPool($ret);
		return ($pool_id);
	}
	
	function generateServices($prefix, $number, $host_id, $template, $cmd, $args, $oldPrefix) {
		global $pearDB;
		
		if (!isset($oldPrefix))
			$oldPrefix = "213343434334343434343";
		
		$DBRESULT =& $pearDB->query(	"SELECT service_id, service_description " .
										"FROM service s, host_service_relation hsr " .
										"WHERE hsr.host_host_id = '$host_id' " .
											"AND service_id = service_service_id " .
											"AND service_description LIKE '$oldPrefix%' ORDER BY service_description ASC");
		$currentNumber = $DBRESULT->numRows();
		if ($currentNumber == 0) {
			for ($i = 1 ; $i <= $number ; $i++) {
				$suffix = "";
				for ($t = $i; $t < 1000 ; $t*=10) {
					$suffix .= "0";
				}
				$suffix .= $i;   
				$request = "INSERT INTO service " .
							"(service_description, service_template_model_stm_id, command_command_id, command_command_id_arg, service_activate, service_register, service_active_checks_enabled, service_passive_checks_enabled, service_parallelize_check, service_obsess_over_service, service_check_freshness, service_event_handler_enabled, service_process_perf_data, service_retain_status_information, service_notifications_enabled, service_is_volatile) " .
							"VALUES ('".$prefix.$suffix."', '".$template."', ".($cmd ? "'$cmd'" : "NULL").", ".($args ? "'$args'" : "NULL").", '1', '1', '0', '1', '2', '2', '2', '2', '2', '2', '2', '2')";
				$pearDB->query($request);
				
				$request = "SELECT MAX(service_id) FROM service WHERE service_description = '".$prefix.$suffix."' AND service_activate = '1' AND service_register = '1'";
				$DBRESULT =& $pearDB->query($request);
				$service = $DBRESULT->fetchRow();
				$service_id = $service["MAX(service_id)"];
			
				if ($service_id != 0) {
					$request = "INSERT INTO host_service_relation (service_service_id, host_host_id) VALUES ('$service_id', '".$host_id."')";
					$pearDB->query($request);
					
					$request = "INSERT INTO extended_service_information (service_service_id) VALUE ('$service_id')";
					$pearDB->query($request);
				}	
			}
		} else if ($currentNumber <= $number) {
			for ($i = 1; $data =& $DBRESULT->fetchRow() ; $i++) {
				$suffix = "";
				for ($t = $i; $t < 1000 ; $t*=10) {
					$suffix .= "0";
				}
				$suffix .= $i;
				$request = "UPDATE service SET service_template_model_stm_id = '".$template."', service_description = '$prefix$suffix' WHERE service_id = '".$data["service_id"]."'";
				$pearDB->query($request);
				$pearDB->query("DELETE FROM host_service_relation WHERE service_service_id = '".$data["service_id"]."'");
				$request = "INSERT INTO host_service_relation (service_service_id, host_host_id) VALUES ('".$data["service_id"]."', '".$host_id."')";
				$pearDB->query($request);
			}
			while ($i <= $number) {
				$suffix = "";
				for ($t = $i; $t < 1000 ; $t*=10) {
					$suffix .= "0";
				}
				$suffix .= $i;   
				$request = "INSERT INTO service " .
							"(service_description, service_template_model_stm_id, command_command_id, command_command_id_arg, service_activate, service_register, service_active_checks_enabled, service_passive_checks_enabled, service_parallelize_check, service_obsess_over_service, service_check_freshness, service_event_handler_enabled, service_process_perf_data, service_retain_status_information, service_notifications_enabled, service_is_volatile) " .
							"VALUES ('".$prefix.$suffix."', '".$template."', ".($cmd ? "'$cmd'" : "NULL").", ".($args ? "'$args'" : "NULL").", '1', '1', '0', '1', '2', '2', '2', '2', '2', '2', '2', '2')";
				$pearDB->query($request);
				
				$request = "SELECT MAX(service_id) FROM service WHERE service_description = '".$prefix.$suffix."' AND service_activate = '1' AND service_register = '1'";
				$DBRESULT =& $pearDB->query($request);
				$service = $DBRESULT->fetchRow();
				$service_id = $service["MAX(service_id)"];
			
				if ($service_id != 0) {
					$request = "INSERT INTO host_service_relation (service_service_id, host_host_id) VALUES ('$service_id', '".$host_id."')";
					$pearDB->query($request);
					
					$request = "INSERT INTO extended_service_information (service_service_id) VALUE ('$service_id')";
					$pearDB->query($request);
				}
				$i++;
			}
		} else if ($currentNumber > $number) {
			for ($i = 1; $data =& $DBRESULT->fetchRow() ; $i++) {
				if ($i > $number) {
					$pearDB->query("DELETE FROM service WHERE service_id = '".$data["service_id"]."'");
				}
			}
		}
	}

	function insertPool($ret = array())	{
		global $form, $pearDB;
	
		if (!count($ret))
			$ret = $form->getSubmitValues();
		
		$rq = "INSERT INTO `mod_dsm_pool` ( " .
				"`pool_id`,`pool_name`,`pool_host_id`,`pool_description`,`pool_number`,`pool_prefix`,`pool_cmd_id`,`pool_args`,".
				"`pool_activate`,`pool_service_template_id`) " .
				"VALUES ( ";
		$rq .= "NULL, ";
		isset($ret["pool_name"]) && $ret["pool_name"] != NULL ? $rq .= "'".$ret["pool_name"]."', ": $rq .= "NULL, ";
		isset($ret["pool_host_id"]) && $ret["pool_host_id"] != NULL ? $rq .= "'".$ret["pool_host_id"]."', ": $rq .= "NULL, ";
		isset($ret["pool_description"]) && $ret["pool_description"] != NULL ? $rq .= "'".htmlentities($ret["pool_description"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["pool_number"]) && $ret["pool_number"] != NULL ? $rq .= "'".htmlentities($ret["pool_number"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["pool_prefix"]) && $ret["pool_prefix"] != NULL ? $rq .= "'".htmlentities($ret["pool_prefix"], ENT_QUOTES)."', ": $rq .= "NULL, ";			
		isset($ret["pool_cmd_id"]) && $ret["pool_cmd_id"] != NULL ? $rq .= "'".htmlentities($ret["pool_cmd_id"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["pool_args"]) && $ret["pool_args"] != NULL ? $rq .= "'".htmlentities($ret["pool_args"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["pool_activate"]["pool_activate"]) && $ret["pool_activate"]["pool_activate"] != NULL ? $rq .= "'".$ret["pool_activate"]["pool_activate"]."', ": $rq .= "NULL, ";
		isset($ret["pool_service_template_id"]) && $ret["pool_service_template_id"] != NULL ? $rq .= "'".$ret["pool_service_template_id"]."' ": $rq .= "NULL ";
		$rq .= ")";

		generateServices($ret["pool_prefix"], $ret["pool_number"], $ret["pool_host_id"], $ret["pool_service_template_id"], $ret["pool_cmd_id"], $ret["pool_args"], "kjqsddlqkjdqslkjdqsldkj");
		
		$DBRESULT =& $pearDB->query($rq);
		$DBRESULT =& $pearDB->query("SELECT MAX(pool_id) FROM mod_dsm_pool");
		$pool_id = $DBRESULT->fetchRow();
		
		return ($pool_id["MAX(pool_id)"]);
	}

	/*
	 * Update Pool informations
	 */
	function updatePool($pool_id = null) {
		global $form, $pearDB;

		if (!$pool_id) 
			return;
			
		/*
		 * Get Old Prefix
		 */
		$DBRESULT =& $pearDB->query("SELECT pool_prefix FROM mod_dsm_pool WHERE pool_id = '$pool_id'");
		$data = $DBRESULT->fetchRow();
		$oldPrefix = $data["pool_prefix"];
		
		$ret = array();
		$ret = $form->getSubmitValues();
		
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
		$rq .=	"pool_activate = ";
		isset($ret["pool_activate"]["pool_activate"]) && $ret["pool_activate"]["pool_activate"] != NULL ? $rq .= "'".htmlentities($ret["pool_activate"]["pool_activate"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_service_template_id = ";
		isset($ret["pool_service_template_id"]) && $ret["pool_service_template_id"] != NULL ? $rq .= "'".htmlentities($ret["pool_service_template_id"], ENT_QUOTES)."' ": $rq .= "NULL ";
		$rq .= "WHERE pool_id = '".$pool_id."'";
		$DBRESULT =& $pearDB->query($rq);
		
		generateServices($ret["pool_prefix"], $ret["pool_number"], $ret["pool_host_id"], $ret["pool_service_template_id"], $ret["pool_cmd_id"], $ret["pool_args"], $oldPrefix);
	}

	function updatePoolContactGroup($pool_id = null, $ret = array())	{
		global $form, $pearDB;

		if (!$pool_id) 
			return;
		
		$rq = "DELETE FROM mod_dsm_cg_relation WHERE pool_id = '".$pool_id."'";
		$DBRESULT =& $pearDB->query($rq);
		
		(isset($ret["pool_cg"])) ? $ret = $ret["pool_cg"] : $ret = $form->getSubmitValue("pool_cg");
		
		for ($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO mod_dsm_cg_relation ";
			$rq .= "(pool_id, cg_cg_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$pool_id."', '".$ret[$i]."')";
			$DBRESULT =& $pearDB->query($rq);
		}
	}

	function updatePoolContact($pool_id = null, $ret = array())	{
		global $form, $pearDB;

		if (!$pool_id) 
			return;
		
		$rq = "DELETE FROM mod_dsm_cct_relation WHERE pool_id = '".$pool_id."'";
		$DBRESULT =& $pearDB->query($rq);
		
		(isset($ret["pool_cct"])) ? $ret = $ret["pool_cct"] : $ret = $form->getSubmitValue("pool_cct");
		
		for ($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO mod_dsm_cct_relation ";
			$rq .= "(pool_id, cct_cct_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$pool_id."', '".$ret[$i]."')";
			$DBRESULT =& $pearDB->query($rq);
		}
	}

?>