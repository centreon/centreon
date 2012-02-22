<?php
/*
 * Copyright 2005-2010 MERETHIS
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
 * SVN : $URL:$
 * SVN : $Id:$
 *
 */

	if (!isset ($oreon)) {
		exit ();
	}

	/**
	 * Get the list of services id for a pool
	 *
	 * @param int $poolId The pool id
	 * @return array
	 */
	function getListServiceForPool($poolId)
	{
	    global $pearDB;

		/*
		 * Get pool informations
		 */
		$queryGetInfo = 'SELECT pool_host_id, pool_prefix FROM mod_dsm_pool WHERE pool_id = ' . $poolId;
		$res = $pearDB->query($queryGetInfo);
		if (PEAR::isError($res)) {
		    return array();
		}
		$row = $res->fetchRow();
		$res->free();

		if (is_null($row['pool_host_id']) || $row['pool_host_id'] == '') {
		    return array();
		}

		$poolPrefix = $row['pool_prefix'];

		$queryListService = 'SELECT service_id, service_description
			FROM service s, host_service_relation hsr
			WHERE hsr.host_host_id = ' . $row['pool_host_id'] . '
				AND service_id = service_service_id
				AND service_description LIKE "' . $poolPrefix . '%"';
		$res = $pearDB->query($queryListService);
		if (PEAR::isError($res)) {
		    return array();
		}
		$listServices = array();
		while ($row = $res->fetchRow()) {
		    if (preg_match('/^' . $poolPrefix . '(\d{4})$/', $row['service_description'])) {
		        $listServices[] = $row['service_id'];
		    }
		}
		$res->free();
		return $listServices;
	}

	/**
	 * Return if a host is already use in DSM
	 *
	 * @param int $hostId The host id
	 * @param int $poolId The pool id or null if not poll id
	 * @return bool
	 */
	function hostUsed($hostId, $poolId = null)
	{
	    global $pearDB;

	    $query = 'SELECT COUNT(pool_id) as nb FROM mod_dsm_pool WHERE pool_host_id = ' . $hostId;
	    if (!is_null($poolId)) {
	        $query .= ' AND pool_id != ' . $poolId;
	    }
	    $res = $pearDB->query($query);
	    if (PEAR::isError($res)) {
	        /*
	         * For integrity
	         */
	        return true;
	    }
	    $row = $res->fetchRow();
	    if ($row['nb'] > 0) {
	        return true;
	    }
	    return false;
	}

	/**
	 *
	 * Enable a slot pool system
	 * @param $pool_id
	 * @param $pool_arr
	 */
	function enablePoolInDB ($pool_id = null, $pool_arr = array())	{
		global $pearDB;

		if (!$pool_id && !count($pool_arr)) {
			return;
		}

		if ($pool_id) {
			$pool_arr = array($pool_id => "1");
		}

		/*
		 * Update services in Centreon configuration
		 */
		foreach ($pool_arr as $id => $values) {
			$DBRESULT = $pearDB->query("UPDATE mod_dsm_pool SET pool_activate = '1' WHERE pool_id = '".$id."'");
			$listServices = getListServiceForPool($id);
			if (count($listServices) > 0) {
			    $queryEnableServices = "UPDATE service SET service_activate = '1' WHERE service_id IN (" . join(', ', $listServices) . ")";
			    $pearDB->query($queryEnableServices);
			}
		}
	}

	/**
	 *
	 * Disable a slot pool system
	 * @param $pool_id
	 * @param $pool_arr
	 */
	function disablePoolInDB ($pool_id = null, $pool_arr = array())	{
		global $pearDB;

		if (!$pool_id && !count($pool_arr)) {
			return;
		}

		if ($pool_id) {
			$pool_arr = array($pool_id => "1");
		}

		foreach ($pool_arr as $id => $values) {
			$DBRESULT = $pearDB->query("UPDATE mod_dsm_pool SET pool_activate = '0' WHERE pool_id = '".$id."'");

			/*
			 * Update services in Centreon configuration
			 */
		    $listServices = getListServiceForPool($id);
			if (count($listServices) > 0) {
			    $queryDisableServices = "UPDATE service SET service_activate = '0' WHERE service_id IN (" . join(', ', $listServices) . ")";
			    $pearDB->query($queryDisableServices);
			}
		}
	}

	/**
	 *
	 * Delete a slot pool system
	 * @param $pools
	 */
	function deletePoolInDB ($pools = array())	{
		global $pearDB;

		foreach ($pools as $key => $value) {
            /*
             * Delete services in Centreon configuration
             */
		    $listServices = getListServiceForPool($key);
		    if (count($listServices) > 0) {
		        $queryDeleteServices = 'DELETE FROM service WHERE service_id IN (' . join(', ', $listServices) . ')';
		        $res = $pearDB->query($queryDeleteServices);
		        if (PEAR::isError($res)) {
		            return;
		        }
		    }

			$DBRESULT = $pearDB->query("DELETE FROM mod_dsm_pool WHERE pool_id = '".$key."'");
		}
	}

    /**
     *
     * Update a slot pool in DB
     * @param $pool_id
     * @return bool
     */
	function updatePoolInDB($pool_id = NULL) {
		global $form;

		if (!$pool_id) {
			return false;
		}

		$ret = $form->getSubmitValues();

		/*
		 * Global function to use
		 */
		return updatePool($pool_id);
	}

	/**
     * Insert a slot pool in DB
     *
     * @param array The values
     * @return int $pool_id The pool id, return -1 if error
     */
	function insertPoolInDB ($ret = array())	{
		$pool_id = insertPool($ret);
		return ($pool_id);
	}

	/**
	 *
	 * Check Pool Existance
	 * @param $pool_name
	 */
	function testPoolExistence($pool_name) {
         global $pearDB;

	    $DBRESULT = $pearDB->query("SELECT * FROM `mod_dsm_pool` WHERE `pool_name` = '".$pool_name."'");
        if ($DBRESULT->numRows() == 0) {
            return 0;
        } else {
            return 1;
        }
	}

	/**
	 *
	 * Duplicate Pool
	 * @param $select
	 * @param $nbrDup
	 */
	function multiplePoolInDB($pool = array(), $nbrDup = array()) {
        global $pearDB;

	    foreach ($pool as $key => $value) {
            $DBRESULT = $pearDB->query("SELECT * FROM `mod_dsm_pool` WHERE `pool_id` = '".$key."' LIMIT 1");

            $row = $DBRESULT->fetchRow();
			$row["pool_id"] = '';

            for ($i = 1; $i <= $nbrDup[$key]; $i++)	{
				$val = null;

				foreach ($row as $key2 => $value2) {
					$key2 == "pool_name" ? ($pool_name = $value2 = $value2."_".$i) : null;
				    if ($key2 == 'pool_host_id') {
					    $value2 = null;
					} elseif ($key2 == 'pool_activate') {
					    $value2 = '0';
				    }
					$val ? $val .= ($value2 != NULL?(", '".$pearDB->escape($value2)."'"):", NULL") : $val .= ($value2 != NULL?("'".$pearDB->escape($value2)."'"):"NULL");
					if ($key2 != "pool_id") {
						$fields[$key2] = $pearDB->escape($value2);
					}
					if (isset($pool_name)) {
					    $fields["pool_name"] = $pool_name."_$i";
					}
				}

				if (isset($pool_name) && !testPoolExistence($pool_name)) {
					$val ? $rq = "INSERT INTO `mod_dsm_pool` VALUES (".$val.")" : $rq = null;
					$DBRESULT = $pearDB->query($rq);
					$DBRESULT = $pearDB->query("SELECT MAX(pool_id) FROM `mod_dsm_pool`");
					$cmd_id = $DBRESULT->fetchRow();
				}
			}
        }
	}

    /**
     *
     * Generate Slot services for pool
     * @param $prefix
     * @param $number
     * @param $host_id
     * @param $template
     * @param $cmd
     * @param $args
     * @param $oldPrefix
     */
	function generateServices($prefix, $number, $host_id, $template, $cmd, $args, $oldPrefix) {
		global $pearDB;

		if (!isset($oldPrefix)) {
			$oldPrefix = "213343434334343434343";
		}

		$DBRESULT = $pearDB->query(	"SELECT service_id, service_description " .
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

	/**
	 * Insert Pool
	 *
	 * @param array $ret The values for new pool
	 * @return int The pool id
	 */
	function insertPool($ret = array())	{
		global $form, $pearDB;

		if (!count($ret)) {
			$ret = $form->getSubmitValues();
		}

		if (hostUsed($ret['pool_host_id'])) {
		    throw new Exception(_('Hosts is already use by another pool'));
		}

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

		/*
		 * Generate all services
		 */
		generateServices($ret["pool_prefix"], $ret["pool_number"], $ret["pool_host_id"], $ret["pool_service_template_id"], $ret["pool_cmd_id"], $ret["pool_args"], "kjqsddlqkjdqslkjdqsldkj");

		$DBRESULT =& $pearDB->query($rq);
		$DBRESULT =& $pearDB->query("SELECT MAX(pool_id) FROM mod_dsm_pool");
		$pool_id = $DBRESULT->fetchRow();

		if ($ret["pool_activate"]["pool_activate"] == 1) {
		    enablePoolInDB($pool_id["MAX(pool_id)"]);
		} else {
		    disablePoolInDB($pool_id["MAX(pool_id)"]);
		}

		return ($pool_id["MAX(pool_id)"]);
	}

	/**
	 * Update Pool
	 *
	 * @param int $pool_id The pool ID
	 * @return bool
	 */
	function updatePool($pool_id = null) {
		global $form, $pearDB;

		if (!$pool_id) {
			return false;
		}

		/*
		 * Get Old Prefix
		 */
		$DBRESULT =& $pearDB->query("SELECT pool_prefix FROM mod_dsm_pool WHERE pool_id = '$pool_id'");
		$data = $DBRESULT->fetchRow();
		$oldPrefix = $data["pool_prefix"];

		$ret = array();
		$ret = $form->getSubmitValues();

		/*
		 * Validate if host is not already use
		 */
		if (hostUsed($ret['pool_host_id'], $pool_id)) {
		    throw new Exception(_('Hosts is already use by another pool'));
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
		$rq .=	"pool_activate = ";
		isset($ret["pool_activate"]["pool_activate"]) && $ret["pool_activate"]["pool_activate"] != NULL ? $rq .= "'".htmlentities($ret["pool_activate"]["pool_activate"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .=	"pool_service_template_id = ";
		isset($ret["pool_service_template_id"]) && $ret["pool_service_template_id"] != NULL ? $rq .= "'".htmlentities($ret["pool_service_template_id"], ENT_QUOTES)."' ": $rq .= "NULL ";
		$rq .= "WHERE pool_id = '".$pool_id."'";
		$DBRESULT =& $pearDB->query($rq);

		generateServices($ret["pool_prefix"], $ret["pool_number"], $ret["pool_host_id"], $ret["pool_service_template_id"], $ret["pool_cmd_id"], $ret["pool_args"], $oldPrefix);

	    if ($ret["pool_activate"]["pool_activate"] == 1) {
		    enablePoolInDB($pool_id);
		} else {
		    disablePoolInDB($pool_id);
		}

		return true;
	}

	/**
	 *
	 * Update Pool ContactGroups
	 * @param $ret
	 */
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

	/**
	 *
	 * Update Pool Contacts
	 * @param $ret
	 */
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