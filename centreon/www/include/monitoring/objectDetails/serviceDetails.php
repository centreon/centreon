<?php
/*
 * Centreon is developped with GPL Licence 2.0 :
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
 * Developped by : Julien Mathis - Romain Le Merlus - Cedrick Facon 
 * 
 * The Software is provided to you AS IS and WITH ALL FAULTS.
 * Centreon makes no representation and gives no warranty whatsoever,
 * whether express or implied, and without limitation, with regard to the quality,
 * any particular or intended purpose of the Software found on the Centreon web site.
 * In no event will Centreon be liable for any direct, indirect, punitive, special,
 * incidental or consequential damages however they may arise and even if Centreon has
 * been previously advised of the possibility of such damages.
 * 
 * For information : contact@centreon.com
 */

	if (!isset($oreon))
		exit();

	include_once("./class/centreonDB.class.php");
	
	$pearDBndo = new CentreonDB("ndo");

	/*
	 * ACL Actions
	 */
	$GroupListofUser = array();
	$GroupListofUser =  $oreon->user->access->getAccessGroups();
	
	$allActions = false;
	/*
	 * Get list of actions allowed for user
	 */
	if (count($GroupListofUser) > 0 && $is_admin == 0) {
		$authorized_actions = array();
		$authorized_actions = $oreon->user->access->getActions();
		if (count($authorized_actions) == 0) 
			$allActions = true;
	} else {
	 	/*
	 	 * if user is admin, or without ACL, 
	 	 * he cans perform all actions
	 	 */
		$allActions = true;
	}

	$ndo_base_prefix = getNDOPrefix();

	if (isset($_GET["host_name"]) && $_GET["host_name"] != "" && isset($_GET["service_description"]) && $_GET["service_description"] != ""){
		$host_name = $_GET["host_name"];
		$svc_description = $_GET["service_description"];
	} else {
		foreach ($_GET["select"] as $key => $value )
			$tab_data = split(";", $key);
		$host_name = $tab_data[0];
		$svc_description = $tab_data[1];
	}

	/*
	 * Host Group List
	 */
	$host_id = getMyHostID($host_name);
	$lcaHost["LcaHost"] = $oreon->user->access->getHostServicesName($pearDBndo);

	if (!$is_admin && !isset($lcaHost["LcaHost"][$host_name])){
		include_once("alt_error.php");
	} else {

		$DBRESULT =& $pearDB->query("SELECT DISTINCT hostgroup_hg_id FROM hostgroup_relation WHERE host_host_id = '".$host_id."' " .
					$oreon->user->access->queryBuilder("AND", "host_host_id", $oreon->user->access->getHostsString("ID", $pearDBndo)));
		for ($i = 0; $hg = $DBRESULT->fetchRow(); $i++)
			$hostGroups[] = getMyHostGroupName($hg["hostgroup_hg_id"]);
		$DBRESULT->free();
	
		$service_id = getMyServiceID($_GET["service_description"], $host_id);
	
		if (isset($service_id) && $service_id) {
			$proc_warning =  getMyServiceMacro($service_id, "PROC_WARNING");
			$proc_critical =  getMyServiceMacro($service_id, "PROC_CRITICAL");
		}
	
		/*
		 * Get service category
		 */
		
		$tab_sc = getMyServiceCategories($service_id);
        foreach ($tab_sc as $sc_id) {
          	$serviceCategories[] = getMyCategorieName($sc_id);
        }

		$tab_status = array();
			
	
		/* start ndo service info */
		$rq =	"SELECT " .
				" nss.current_state," .
				" nss.output as plugin_output," .
				" nss.current_check_attempt as current_attempt," .
				" nss.status_update_time as status_update_time," .
				" unix_timestamp(nss.last_state_change) as last_state_change," .
				" unix_timestamp(nss.last_check) as last_check," .
				" nss.notifications_enabled," .
				" unix_timestamp(nss.next_check) as next_check," .
				" nss.problem_has_been_acknowledged," .
				" nss.passive_checks_enabled," .
				" nss.active_checks_enabled," .
				" nss.event_handler_enabled," .
				" nss.perfdata as performance_data," .
				" nss.is_flapping," .
				" nss.scheduled_downtime_depth," .
				" nss.percent_state_change," .
				" nss.current_notification_number," .
				" nss.obsess_over_service," .
				" nss.check_type," .
				" nss.state_type," .
				" nss.latency as check_latency," .
				" nss.execution_time as check_execution_time," .
				" nss.flap_detection_enabled," .
				" unix_timestamp(nss.last_notification) as last_notification," .
				" no.name1 as host_name," .
				" no.name2 as service_description" .
				" FROM ".$ndo_base_prefix."servicestatus nss, ".$ndo_base_prefix."objects no" .
				" WHERE no.object_id = nss.service_object_id AND no.name1 like '".$host_name."' ";
	
		$DBRESULT_NDO =& $pearDBndo->query($rq);		
	
		$tab_status_service = array(0 => "OK", 1 => "WARNING", 2 => "CRITICAL", "3" => "UNKNOWN", "4" => "PENDING");
	
		while ($ndo =& $DBRESULT_NDO->fetchRow()){
			if($ndo["service_description"] == $svc_description)
				$service_status[$host_name."_".$svc_description]= $ndo;
	
			if (!isset($tab_status[$ndo["current_state"]]))
				$tab_status[$tab_status_service[$ndo["current_state"]]] = 0;
			$tab_status[$tab_status_service[$ndo["current_state"]]]++;
		}
	
		$service_status[$host_name."_".$svc_description]["current_state"] = $tab_status_service[$service_status[$host_name."_".$svc_description]["current_state"]];
	
		/* 
		 * start ndo host detail
		 */
		$tab_host_status[0] = "UP";
		$tab_host_status[1] = "DOWN";
		$tab_host_status[2] = "UNREACHABLE";
	
		$rq2 =	"SELECT nhs.current_state" .
				" FROM ".$ndo_base_prefix."hoststatus nhs, ".$ndo_base_prefix."objects no" .
				" WHERE no.object_id = nhs.host_object_id AND no.name1 like '".$host_name."'";
		$DBRESULT_NDO =& $pearDBndo->query($rq2);
		$ndo2 =& $DBRESULT_NDO->fetchRow();
		$host_status[$host_name] = $tab_host_status[$ndo2["current_state"]];
		/* end ndo host detail */
	
	
		if (!isset($_GET["service_description"]))
			$_GET["service_description"] = $svc_description;
			
		$res =& $pearDB->query("SELECT * FROM host WHERE host_name = '".$host_name."'");
		$host =& $res->fetchrow();
		$host_id = getMyHostID($host["host_name"]);
		$service_id = getMyServiceID($_GET["service_description"], $host_id);
		$total_current_attempts = getMyServiceField($service_id, "service_max_check_attempts");

		$path = "./include/monitoring/objectDetails/";

		/*
		 * Smarty template Init
		 */
		$tpl = new Smarty();
		$tpl = initSmartyTpl($path, $tpl, "./template/");

		$en = array("0" => _("No"), "1" => _("Yes"));
		
		/*
		 * Get comments for service
		 */
		$tabCommentServices = array();
		$rq2 =	" SELECT DISTINCT cmt.comment_time as entry_time, cmt.comment_id, cmt.author_name, cmt.comment_data, cmt.is_persistent, obj.name1 host_name, obj.name2 service_description " .
				" FROM ".$ndo_base_prefix."comments cmt, ".$ndo_base_prefix."objects obj " .
				" WHERE obj.name1 = '".$host_name."' AND obj.name2 = '".$svc_description."' AND obj.object_id = cmt.object_id AND cmt.expires = 0 ORDER BY cmt.comment_time";
		$DBRESULT_NDO =& $pearDBndo->query($rq2);
		for ($i = 0; $data =& $DBRESULT_NDO->fetchRow(); $i++){
			$tabCommentServices[$i] = $data;
			$tabCommentServices[$i]["is_persistent"] = $en[$tabCommentServices[$i]["is_persistent"]];
		}
		unset($data);
		
		$en_acknowledge_text= array("1" => _("Delete this Acknowledgement"), "0" => _("Acknowledge this service"));
		$en_acknowledge 	= array("1" => "0", "0" => "1");
		$en_disable 		= array("1" => _("Enabled"), "0" => _("Disabled"));
		$en_inv	 			= array("1" => "0", "0" => "1");
		$en_inv_text 		= array("1" => _("Disable"), "0" => _("Enable"));
		$color_onoff 		= array("1" => "#00ff00", "0" => "#ff0000");
		$color_onoff_inv 	= array("0" => "#00ff00", "1" => "#ff0000");
		$img_en 			= array("0" => "<img src='./img/icones/16x16/element_next.gif' border='0'>", "1" => "<img src='./img/icones/16x16/element_previous.gif' border='0'>");

		/*
		 * Ajust data for beeing displayed in template
		 */
		 
		 $service_status[$host_name."_".$svc_description]["status_color"] = $oreon->optGen["color_".strtolower($service_status[$host_name."_".$svc_description]["current_state"])];
		 $service_status[$host_name."_".$svc_description]["last_check"] = $oreon->CentreonGMT->getDate(_("Y/m/d - H:i:s"), $service_status[$host_name."_".$svc_description]["last_check"], $oreon->user->getMyGMT());
		 $service_status[$host_name."_".$svc_description]["next_check"] = $oreon->CentreonGMT->getDate(_("Y/m/d - H:i:s"), $service_status[$host_name."_".$svc_description]["next_check"], $oreon->user->getMyGMT());
		!$service_status[$host_name."_".$svc_description]["check_latency"] ? $service_status[$host_name."_".$svc_description]["check_latency"] = "< 1 second" : $service_status[$host_name."_".$svc_description]["check_latency"] = $service_status[$host_name."_".$svc_description]["check_latency"] . " seconds";
		!$service_status[$host_name."_".$svc_description]["check_execution_time"] ? $service_status[$host_name."_".$svc_description]["check_execution_time"] = "< 1 second" : $service_status[$host_name."_".$svc_description]["check_execution_time"] = $service_status[$host_name."_".$svc_description]["check_execution_time"] . " seconds";
		
		!$service_status[$host_name."_".$svc_description]["last_notification"] ? $service_status[$host_name."_".$svc_description]["notification"] = "": $service_status[$host_name."_".$svc_description]["last_notification"] = $oreon->CentreonGMT->getDate(_("Y/m/d - H:i:s"), $service_status[$host_name."_".$svc_description]["last_notification"], $oreon->user->getMyGMT());
		
		if (isset($service_status[$host_name."_".$svc_description]["next_notification"]) && !$service_status[$host_name."_".$svc_description]["next_notification"]) 
			$service_status[$host_name."_".$svc_description]["next_notification"] = "";
		else if (!isset($service_status[$host_name."_".$svc_description]["next_notification"]))
			$service_status[$host_name."_".$svc_description]["next_notification"] = "N/A";
		else
			$service_status[$host_name."_".$svc_description]["next_notification"] = $oreon->CentreonGMT->getDate(_("Y/m/d - H:i:s"), $service_status[$host_name."_".$svc_description]["next_notification"], $oreon->user->getMyGMT());
		

		$service_status[$host_name."_".$svc_description]["plugin_output"] = str_replace("<b>", "", $service_status[$host_name."_".$svc_description]["plugin_output"]);
		$service_status[$host_name.'_'.$svc_description]["plugin_output"] = str_replace("</b>", "", $service_status[$host_name."_".$svc_description]["plugin_output"]);
		$service_status[$host_name."_".$svc_description]["plugin_output"] = str_replace("<br>", "", $service_status[$host_name."_".$svc_description]["plugin_output"]);

		$service_status[$host_name."_".$svc_description]["plugin_output"] = utf8_encode($service_status[$host_name."_".$svc_description]["plugin_output"]);

		$service_status[$host_name.'_'.$svc_description]["plugin_output"] = str_replace("'", "", $service_status[$host_name.'_'.$svc_description]["plugin_output"]);
	
		$service_status[$host_name.'_'.$svc_description]["plugin_output"] = str_replace("\"", "", $service_status[$host_name.'_'.$svc_description]['plugin_output']);

		!$service_status[$host_name."_".$svc_description]["last_state_change"] ? $service_status[$host_name."_".$svc_description]["duration"] = Duration::toString($service_status[$host_name."_".$svc_description]["last_time_".strtolower($service_status[$host_name."_".$svc_description]["current_state"])]) : $service_status[$host_name."_".$svc_description]["duration"] = Duration::toString(time() - $service_status[$host_name."_".$svc_description]["last_state_change"]);
		!$service_status[$host_name."_".$svc_description]["last_state_change"] ? $service_status[$host_name."_".$svc_description]["last_state_change"] = "": $service_status[$host_name."_".$svc_description]["last_state_change"] = $oreon->CentreonGMT->getDate(_("Y/m/d - H:i:s"),$service_status[$host_name."_".$svc_description]["last_state_change"], $oreon->user->getMyGMT());
		 $service_status[$host_name."_".$svc_description]["last_update"] = $oreon->CentreonGMT->getDate(_("Y/m/d - H:i:s"), time(), $oreon->user->getMyGMT());
		!$service_status[$host_name."_".$svc_description]["is_flapping"] ? $service_status[$host_name."_".$svc_description]["is_flapping"] = $en[$service_status[$host_name."_".$svc_description]["is_flapping"]] : $service_status[$host_name."_".$svc_description]["is_flapping"] = $oreon->CentreonGMT->getDate(_("Y/m/d - H:i:s"), $service_status[$host_name."_".$svc_description]["is_flapping"], $oreon->user->getMyGMT());


		if (isset($ndo) && $ndo) {
			foreach ($tab_host_service[$host_name] as $key_name => $s){
				if (!isset($tab_status[$service_status[$host_name."_".$key_name]["current_state"]]))
					$tab_status[$service_status[$host_name."_".$key_name]["current_state"]] = 0;
				$tab_status[$service_status[$host_name."_".$key_name]["current_state"]]++;
			}
		}
		
		$status = NULL;
		foreach ($tab_status as $key => $value)
			$status .= "&value[".$key."]=".$value;

		$optionsURL = "session_id=".session_id()."&host_name=".$host_name."&service_description=".$svc_description;

		/*
		 * Assign translations
		 */
		$tpl->assign("m_mon_services", _("Services"));
		$tpl->assign("m_mon_on_host", _("on host"));
		$tpl->assign("m_mon_services_status", _("Services Status"));
		$tpl->assign("m_mon_host_status_info", _("Status Information"));
		$tpl->assign("m_mon_performance_data", _("Performance Data"));
		$tpl->assign("m_mon_services_attempt", _("Current Attempt"));
		$tpl->assign("m_mon_services_state", _("State Type"));
		$tpl->assign("m_mon_last_check_type", _("Last Check Type"));
		$tpl->assign("m_mon_host_last_check", _("Last Check"));
		$tpl->assign("m_mon_services_active_check", _("Next Scheduled Active Check"));
		$tpl->assign("m_mon_services_latency", _("Latency"));
		$tpl->assign("m_mon_services_duration", _("Check Duration"));
		$tpl->assign("m_mon_last_change", _("Last State Change"));
		$tpl->assign("m_mon_current_state_duration", _("Current State Duration"));
		$tpl->assign("m_mon_last_notification_serv", _("Last Service Notification"));
		$tpl->assign("m_mon_notification_nb", _("Current Notification Number"));
		$tpl->assign("m_mon_services_flapping", _("Is This Service Flapping?"));
		$tpl->assign("m_mon_percent_state_change", _("Percent State Change"));
		$tpl->assign("m_mon_downtime_sc", _("In Scheduled Downtime?"));
		$tpl->assign("m_mon_last_update", _("Last Update"));
		$tpl->assign("m_mon_tips", _("Tips"));
		$tpl->assign("m_mon_tools", _("Tools"));
		$tpl->assign("m_mon_service_command", _("Service Commands"));
		$tpl->assign("m_mon_check_this_service", _("Checks for this service"));
		$tpl->assign("m_mon_schedule", _("Re-schedule the next check for this service"));
		$tpl->assign("m_mon_schedule_force", _("Re-schedule the next check for this service (forced)"));
		$tpl->assign("m_mon_submit_passive", _("Submit result for this service"));
		$tpl->assign("m_mon_accept_passive", _("Accepting passive checks for this service"));
		$tpl->assign("m_mon_notification_service", _("Notifications for this service"));
		$tpl->assign("m_mon_schedule_downtime", _("Schedule downtime for this service"));
		$tpl->assign("m_mon_schedule_comment", _("Add a comment for this service"));
		$tpl->assign("m_mon_event_handler", _("Event Handler"));
		$tpl->assign("m_mon_flap_detection", _("Flap Detection"));
		$tpl->assign("m_mon_services_en_check_active", _("Active Check Enabled :"));
		$tpl->assign("m_mon_services_en_check_passif", _("Passive Check Enabled :"));
		$tpl->assign("m_mon_services_en_notification", _("Notification Enabled :"));
		$tpl->assign("m_mon_services_en_flap", _("Flap Detection Enabled :"));
		$tpl->assign("m_mon_obsessing", _("Obsess"));
		$tpl->assign("m_comment_for_service", _("All Comments of this service"));
		$tpl->assign("cmt_host_name", _("Host Name"));
		$tpl->assign("cmt_service_descr", _("Services"));
		$tpl->assign("cmt_entry_time", _("Entry Time"));
		$tpl->assign("cmt_author", _("Author"));
		$tpl->assign("cmt_comment", _("Comments"));
		$tpl->assign("cmt_persistent", _("Persistent"));
		$tpl->assign("secondes", _("secondes"));
		$tpl->assign("m_mon_ticket", "Open Ticket");
		$tpl->assign("links", _("Links"));
		
		/*
		 * if user is admin, allActions is true, 
		 * else we introduce all actions allowed for user
		 */
		$tpl->assign("acl_allActions", $allActions);
		if (isset($authorized_actions))
			$tpl->assign("aclAct", $authorized_actions);
		
		$tpl->assign("p", $p);
		$tpl->assign("o", $o);
		$tpl->assign("en", $en);
		$tpl->assign("en_inv", $en_inv);
		$tpl->assign("en_inv_text", $en_inv_text);
		$tpl->assign("img_en", $img_en);
		$tpl->assign("color_onoff", $color_onoff);
		$tpl->assign("color_onoff_inv", $color_onoff_inv);
		$tpl->assign("en_disable", $en_disable);
		$tpl->assign("total_current_attempt", $total_current_attempts);
		$tpl->assign("en_acknowledge_text", $en_acknowledge_text);
		$tpl->assign("en_acknowledge", $en_acknowledge);
		$tpl->assign("actpass", array("0"=>_("Active"), "1"=>_("Passive")));
		$tpl->assign("harsof", array("0"=>_("Soft"), "1"=>_("Hard")));
		$tpl->assign("status", $status);
		$tpl->assign("h", $host);
		$tpl->assign("lcaTopo", $oreon->user->lcaTopo);
		$tpl->assign("count_comments_svc", count($tabCommentServices));
		$tpl->assign("tab_comments_svc", $tabCommentServices);
		$tpl->assign("flag_graph", service_has_graph($host["host_id"], getMyServiceID($svc_description, $host["host_id"])));
		$tpl->assign("service_id", getMyServiceID($svc_description, $host["host_id"]));
		$tpl->assign("host_data", $host_status[$host_name]);
		$tpl->assign("service_data", $service_status[$host_name."_".$svc_description]);
		$tpl->assign("svc_description", $svc_description);

		/*
		 * Hostgroups Display
		 */
		$tpl->assign("hostgroups_label", _("Hosts Groups"));
		if (isset($hostGroups))
			$tpl->assign("hostgroups", $hostGroups);
	
		/*
		 * Service Categories
		 */
		$tpl->assign("sg_label", _("Service Categories"));
		if (isset($serviceCategories))
			$tpl->assign("service_categories", $serviceCategories);
					
		/*
		 * Macros
		 */
		if (isset($proc_warning) && $proc_warning)
			$tpl->assign("proc_warning", $proc_warning);
		if (isset($proc_critical) && $proc_critical)
			$tpl->assign("proc_critical", $proc_critical);

		/*
		 * Ext informations
		 */
		$tpl->assign("sv_ext_notes", getMyServiceExtendedInfoField($service_id, "esi_notes"));
		$tpl->assign("sv_ext_notes_url", getMyServiceExtendedInfoField($service_id, "esi_notes_url"));
		$tpl->assign("sv_ext_action_url_lang", _("Action URL"));
		$tpl->assign("sv_ext_action_url", getMyServiceExtendedInfoField($service_id, "esi_action_url"));
		$tpl->assign("sv_ext_icon_image_alt", getMyServiceExtendedInfoField($service_id, "esi_icon_image_alt"));
		$tpl->assign("options", $optionsURL);
		
		$tpl->display("serviceDetails.ihtml");
	}
?>
