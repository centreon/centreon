<?php
/*
 * Centreon is developped with GPL Licence 2.0 :
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
 * Developped by : Julien Mathis - Romain Le Merlus 
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

	if (stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml")) { 	
		header("Content-type: application/xhtml+xml"); 
	} else {
		header("Content-type: text/xml"); 
	} 
		
	include_once("@CENTREON_ETC@/centreon.conf.php");
	include_once $centreon_path . "www/class/centreonXML.class.php";
	include_once $centreon_path . "www/class/centreonDB.class.php";
	include_once $centreon_path . "www/class/centreonACL.class.php";
	
	$pearDB = new CentreonDB();
	$pearDBndo = new CentreonDB("ndo");
	$pearDBO = new CentreonDB("centstorage");
	
	/*
	 * Start document root
	 */
	$buffer = new CentreonXML();
	$buffer->startElement("root");	
	
	/*
	 * if debug == 0 => Normal, 
	 * debug == 1 => get use, 
	 * debug == 2 => log in file (log.xml)
	 */
	$debugXML = 0;
	//$buffer = '';

	/*
	 * PHP functions
	 */	
	include_once($centreon_path . "www/include/common/common-Func.php");
	
	/*
	 * Lang file
	 */
	function getMyHostIDService($svc_id = NULL)	{
		global $pearDB;
		
		if (!$svc_id) 
			return;
		
		$DBRESULT =& $pearDB->query("SELECT host_id FROM host h, host_service_relation hs WHERE h.host_id = hs.host_host_id AND hs.service_service_id = '".$svc_id."'");
		if ($DBRESULT->numRows())	{
			$row =& $DBRESULT->fetchRow();
			return $row["host_id"];
		}
		return NULL;
	}
	
	$sid = $_GET['sid'];
	
	$contact_id = check_session($sid, $pearDB);
	$access = new CentreonAcl($contact_id, $is_admin);
	$is_admin = isUserAdmin($sid);
		
	
	$lca = $access->getHostServices($pearDBndo);		

	(isset($_GET["sid"]) 			&& !check_injection($_GET["sid"])) ? $sid = htmlentities($_GET["sid"]) : $sid = "-1";
	(isset($_GET["template_id"]) 	&& !check_injection($_GET["template_id"])) ? $template_id = htmlentities($_GET["template_id"]) : $template_id = "1";
	(isset($_GET["split"]) 			&& !check_injection($_GET["split"])) ? $split = htmlentities($_GET["split"]) : $split = "0";
	(isset($_GET["status"]) 		&& !check_injection($_GET["status"])) ? $status = htmlentities($_GET["status"]) : $status = "0";
	(isset($_GET["warning"]) 		&& !check_injection($_GET["warning"])) ? $warning = htmlentities($_GET["warning"]) : $warning = "0";
	(isset($_GET["critical"]) 		&& !check_injection($_GET["critical"])) ? $critical = htmlentities($_GET["critical"]) : $critical = "0";
	(isset($_GET["StartDate"])		&& !check_injection($_GET["StartDate"])) ? $StartDate = htmlentities($_GET["StartDate"]) : $StartDate = "";
	(isset($_GET["EndDate"]) 		&& !check_injection($_GET["EndDate"])) ? $EndDate = htmlentities($_GET["EndDate"]) : $EndDate = "";
	(isset($_GET["StartTime"]) 		&& !check_injection($_GET["StartTime"])) ? $StartTime = htmlentities($_GET["StartTime"]) : $StartTime = "";
	(isset($_GET["EndTime"]) 		&& !check_injection($_GET["EndTime"])) ? $EndTime = htmlentities($_GET["EndTime"]) :$EndTime = "";
	(isset($_GET["multi"]) 			&& !check_injection($_GET["multi"])) ? $multi = htmlentities($_GET["multi"]) : $multi = "-1";
	(isset($_GET["id"])) ? 			$openid = htmlentities($_GET["id"]) : $openid = "-1";
	(isset($_GET["period"]) 		&& !check_injection($_GET["period"])) ? $auto_period = htmlentities($_GET["period"]) : $auto_period = "-1";
	
	/*
	 * Check if period is a period by duration or a time range.
	 */
	if (isset($_GET["period"]) && $_GET["period"]) {
		$flag_period = 1;
	} else
		$flag_period = 0;
	
	if (!strncmp($openid, "SS_HS", 5)){
		$tab = split("_", $openid);
		$openid = "SS_".$tab[2]."_".$tab[3];
		unset($tab);
	}

	if ($StartDate !=  "" && $StartTime != ""){
		preg_match("/^([0-9]*)\/([0-9]*)\/([0-9]*)/", $StartDate, $matchesD);
		preg_match("/^([0-9]*):([0-9]*)/", $StartTime, $matchesT);
		$start = mktime($matchesT[1], $matchesT[2], "0", $matchesD[1], $matchesD[2], $matchesD[3], -1);
	}
	
	if ($EndDate !=  "" && $EndTime != ""){
		preg_match("/^([0-9]*)\/([0-9]*)\/([0-9]*)/", $EndDate, $matchesD);
		preg_match("/^([0-9]*):([0-9]*)/", $EndTime, $matchesT);
		$end = mktime($matchesT[1], $matchesT[2], "0", $matchesD[1], $matchesD[2], $matchesD[3], -1);
	}
	
	/*
	 * Defined Default period
	 */
	$period = 86400;
	
	/*
	 * Adjust default date picker.
	 */
	if ($auto_period > 0){
		$period = $auto_period;
		$start = time() - ($period);
		$end = time();
	}			

	/*
 	 * set graph template list
 	 */
 
	$graphTs = array( NULL => NULL );
	$DBRESULT =& $pearDB->query("SELECT `graph_id`, `name` FROM `giv_graphs_template` ORDER BY `name`");
	while ($graphT =& $DBRESULT->fetchRow())
		$graphTs[$graphT["graph_id"]] = $graphT["name"];
	$DBRESULT->free();

	$i = 0;
	$tab_id = array();
	
	if ($multi == 0){
		$tab_tmp = split("_", $openid);
		$type = $tab_tmp[0];
		$id = "";
		for ($i = 1; $i <= (count($tab_tmp) - 1); $i++)
			$id .= "_".$tab_tmp[$i];
		array_push($tab_id, $type.$id);
	} else {
		$buffer->writeElement("opid", $openid);		
		$tab_tmp = split(",", $openid);
	
		foreach ($tab_tmp as $openid) {
			$tab_tmp = split("_", $openid);
			if (isset($tab_tmp[1]))
				$id = $tab_tmp[1];
			$type = $tab_tmp[0];
			
			if ($type == 'HG')	{
				
				$hosts = getMyHostGroupHosts($id);
				foreach ($hosts as $host)	{
					if (host_has_one_or_more_GraphService($host) && (($is_admin) || (!$is_admin && isset($lca["LcaHost"][getMyHostName($host)])))){
						$services = getMyHostServices($host);
						foreach($services as $svc_id => $svc_name)	{
							if (service_has_graph($host, $svc_id) 
								&& (($is_admin) 
								|| (!$is_admin 
									&& isset($lca["LcaHost"][getMyHostName($host)]) 
									&& isset($lca["LcaHost"][getMyHostName($host)]["svc"][$svc_name]))))	{
								$oid = "HS_".$svc_id."_".$host;
								array_push($tab_id, $oid);	
							}
						}
					}
				}
				
			} else if ($type == 'HH')	{
				
				$services = getMyHostServices($id);
				foreach ($services as $svc_id => $svc_name)	{
					if (service_has_graph($id, $svc_id) 
						&& (($is_admin) 
						|| (!$is_admin 
							&& isset($lca["LcaHost"][getMyHostName($id)]) 
							&& isset($lca["LcaHost"][getMyHostName($id)]["svc"][$svc_name]))))	{
						$oid = "HS_".$svc_id."_".$id;
						array_push($tab_id, $oid);	
					}
				}
				
			} else if ($type == 'ST')	{
				
				$services = getMyServiceGroupServices($id);
				foreach ($services as $svc_id => $svc_name)	{ 
					$tab_tmp = split("_", $svc_id);
					if (service_has_graph($tab_tmp[0], $tab_tmp[1]) && (($is_admin) || (!$is_admin && isset($lca["LcaHost"][getMyHostName($id)]) && isset($lca["LcaHost"][getMyHostName($id)]["svc"][$svc_name]))))	{
						$oid = "HS_".$tab_tmp[1]."_".$tab_tmp[0];
						array_push($tab_id, $oid);	
					}
				}
				
			} else if ($type == 'MS')	{
				array_push($tab_id, $openid);
			} else	{
				if (isset($tab_tmp[2]) && service_has_graph($tab_tmp[2], $tab_tmp[1]) 
					&& (($is_admin) || 
						(!$is_admin && isset($lca["LcaHost"][getMyHostName($tab_tmp[2])]) 
						&& isset($lca["LcaHost"][getMyHostName($tab_tmp[2])]["svc"][getMyServiceName($tab_tmp[1])])))){
					array_push($tab_id, $openid);
				}
			} 
		}
	}

	/*
	 * clean double in tab_id
	 */
	$tab_tmp = $tab_id;
	$tab_id = array();
	$tab_real_id = array();

	if (count($tab_tmp)){
		foreach ($tab_tmp as $openid)	{
			$tab = split("_", $openid);
			if (isset($tab[2]) && $tab[2])
				$tab_real_id[$tab[0] ."_". $tab[1]."_".$tab[2]] = $openid;
			else
				$tab_real_id[$tab[0] ."_". $tab[1]] = $openid;
		}
	}

	function returnType($type, $multi){
		if ($multi && $type == 'HS')
			$type = "SS";
		else if ($multi && $type == 'MS')
			$type = "SM";
		else if ($multi)
			$type = "NO";
		return $type;
	}
	
	$tab_class = array("1" => "list_one", "0" => "list_two");
	
	foreach ($tab_real_id as $key => $openid) {
		$bad_value = 0;	
		$tab_tmp = split("_", $openid);
		
		if (isset($tab_tmp[2]) && $tab_tmp[2])
			$id = $tab_tmp[1]."_".$tab_tmp[2];
		else
			$id = $tab_tmp[1];
			
		$type = $tab_tmp[0];
		
		$real_id = $tab_real_id[$key];
		$type = returnType($type, $multi);
		
		/*
		 * for one svc -> daily,weekly,monthly,yearly..
		 */
		if ($type == "HS" || $type == "MS"){
			$msg_error 		= 0;
			$elem 			= array();
		
			$graphTs = array(NULL => NULL);
			$DBRESULT =& $pearDB->query("SELECT graph_id, name FROM giv_graphs_template ORDER BY name");
			while ($graphT =& $DBRESULT->fetchRow())
				$graphTs[$graphT["graph_id"]] = $graphT["name"];
			$DBRESULT->free();

			if ($type == "HS"){
				$tab_tmp = split("_", $real_id);
				$DBRESULT2 =& $pearDBO->query("SELECT id, service_id, service_description, host_name, special FROM index_data WHERE `trashed` = '0' AND special = '0' AND host_id = '".$tab_tmp[2]."' AND service_id = '".$tab_tmp[1]."'");
				$svc_id =& $DBRESULT2->fetchRow();
				$template_id = getDefaultGraph($svc_id["service_id"], 1);
				$DBRESULT2 =& $pearDB->query("SELECT * FROM giv_graphs_template WHERE graph_id = '".$template_id."' LIMIT 1");
				$GraphTemplate =& $DBRESULT2->fetchRow();
				if ($GraphTemplate["split_component"] == 1)
					$split = 1;
			}	
			if ($type == "MS"){			
				$other_services = array();
				$DBRESULT2 =& $pearDBO->query("SELECT * FROM index_data WHERE `trashed` = '0' AND special = '1' AND service_description = 'meta_".$id."' ORDER BY service_description");
				if ($svc_id =& $DBRESULT2->fetchRow()){
					if (preg_match("/meta_([0-9]*)/", $svc_id["service_description"], $matches)){
						$DBRESULT_meta =& $pearDB->query("SELECT meta_name FROM meta_service WHERE `meta_id` = '".$matches[1]."'");
						$meta =& $DBRESULT_meta->fetchRow();
						$DBRESULT_meta->free();
						$svc_id["service_description"] = $meta["meta_name"];
					}	
					$svc_id["service_description"] = str_replace("#S#", "/", $svc_id["service_description"]);
					$svc_id["service_description"] = str_replace("#BS#", "\\", $svc_id["service_description"]);
					$svc_id[$svc_id["id"]] = $svc_id["service_description"];
				}
				$DBRESULT2->free();
			}
			
			$index = $svc_id["id"];
			$metrics = array();
			$name = $svc_id["service_description"];	
	
			$service_id = $svc_id["service_id"];
			$index_id = $svc_id["id"];
		
			$DBRESULT2 =& $pearDBO->query("SELECT * FROM metrics WHERE index_id = '".$index."' AND `hidden` = '0' ORDER BY `metric_name`");
			for ($counter = 0;$metrics_ret =& $DBRESULT2->fetchRow(); $counter++){
				$metrics[$metrics_ret["metric_id"]]["metric_name"] = str_replace('#S#', "/", $metrics_ret["metric_name"]);
				$metrics[$metrics_ret["metric_id"]]["metric_name"] = str_replace('#BS#', "\\", $metrics[$metrics_ret["metric_id"]]["metric_name"]);
				$metrics[$metrics_ret["metric_id"]]["metric_id"] = $metrics_ret["metric_id"];
				$metrics[$metrics_ret["metric_id"]]["class"] = $tab_class[$counter % 2];
			}
			$DBRESULT2->free();
			
			# verify if metrics in parameter is for this index
			$metrics_active =& $_GET["metric"];
			$pass = 0;
			if (isset($metrics_active))
				foreach ($metrics_active as $key => $value)
					if (isset($metrics[$key]))
						$pass = 1;
			
			if ($msg_error == 0)	{
				if (isset($_GET["metric"]) && $pass){
					$DBRESULT =& $pearDB->query("DELETE FROM `ods_view_details` WHERE index_id = '".$index."'");
					foreach ($metrics_active as $key => $metric){
						if (isset($metrics_active[$key])){
							$DBRESULT =& $pearDB->query("INSERT INTO `ods_view_details` (`metric_id`, `contact_id`, `all_user`, `index_id`) VALUES ('".$key."', '".$contact_id."', '0', '".$index."');");
						}
					}
				} else {
					$DBRESULT =& $pearDB->query("SELECT metric_id FROM `ods_view_details` WHERE index_id = '".$index."' AND `contact_id` = '".$contact_id."'");
					$metrics_active = array();
					if ($DBRESULT->numRows())
						while ($metric =& $DBRESULT->fetchRow())
							$metrics_active[$metric["metric_id"]] = 1;		
					else
						foreach ($metrics as $key => $value)
							$metrics_active[$key] = 1;	
				}
			}
		
			if ($svc_id["host_name"] == "_Module_Meta")
				$svc_id["host_name"] = "Meta Services";
				
			$svc_id["service_description"] = str_replace("#S#", "/", $svc_id["service_description"]);	
				
			if (preg_match("/meta_([0-9]*)/", $svc_id["service_description"], $matches)){
				$DBRESULT_meta =& $pearDB->query("SELECT meta_name FROM meta_service WHERE `meta_id` = '".$matches[1]."'");
				$meta =& $DBRESULT_meta->fetchRow();
				$svc_id["service_description"] = $meta["meta_name"];
			}	
			
			if ($split){
				$DBRESULT2 =& $pearDBO->query("SELECT * FROM metrics WHERE index_id = '".$index."' AND `hidden` = '0' ORDER BY `metric_name`");
				for ($counter = 1;$metrics_ret =& $DBRESULT2->fetchRow(); $counter++){
					if (isset($metrics_active[$metrics_ret["metric_id"]]) && $metrics_active[$metrics_ret["metric_id"]])
						$metrics_list[$metrics_ret["metric_id"]] = $counter;
				}
			}		
			$tab_period['Daily']	= (time() - (60 * 60 * 24));
			$tab_period['Weekly']	= (time() - 60 * 60 * 24 * 7);
			$tab_period['Monthly']	= (time() - 60 * 60 * 24 * 31);
			$tab_period['Yearly']	= (time() - 60 * 60 * 24 * 365);
		
			/*
			 * Create XML response
			 */
			$buffer->startElement("svc");
			$buffer->writeElement("name", $name);
			$buffer->writeElement("sid", $sid);			
	
			if ($type == "MS")
				$buffer->writeElement("zoom_type", "SM_");				
			if ($type == "HS")
				$buffer->writeElement("zoom_type", "SS_");				
	
			$buffer->writeElement("id", $id);
			$buffer->writeElement("index", $index);
			$buffer->writeElement("flagperiod", $flag_period);
			$buffer->writeElement("opid", $openid);
			$buffer->writeElement("split", $split);
			$buffer->writeElement("status", $status);
			$buffer->writeElement("warning", $warning);
			$buffer->writeElement("critical", $critical);			
			foreach ($tab_period as $name => $start){
				$buffer->startElement("period");
				$buffer->writeElement("name", $name);
				$buffer->writeElement("start", $start);
				$buffer->writeElement("end", time());				
		
				if ($split)
					foreach ($metrics as $metric_id => $metric)	{
						$buffer->startElement("metric");
						$buffer->writeElement("metric_id", $metric_id);
						$buffer->endElement();						
					}
				$buffer->endElement();				
			}
			$buffer->endElement();			
		}
	
		/*
		 * For service zoom or multi selected
		 */
		if ($type == "SS" || $type == "SM"){
				
			$graphTs = array(NULL=>NULL);
			$DBRESULT =& $pearDB->query("SELECT graph_id,name FROM giv_graphs_template ORDER BY name");
			while ($graphT =& $DBRESULT->fetchRow())
				$graphTs[$graphT["graph_id"]] = $graphT["name"];
			$DBRESULT->free();
			
			if (isset($_GET["period"]))
				$period =  $_GET["period"];
			if (isset($_POST["period"]))
				$period =  $_POST["period"];
			
			# Verify if template exists
			$DBRESULT =& $pearDB->query("SELECT * FROM `giv_graphs_template`");
			if (!$DBRESULT->numRows())
			
			$label = NULL;
			$elem = array();
		
			if ($type == "SS"){
				$tab_tmp = split("_", $openid);
				$DBRESULT2 =& $pearDBO->query("SELECT id, service_id, service_description, host_name, special FROM index_data WHERE `trashed` = '0' AND special = '0' AND host_id = '".$tab_tmp[2]."' AND service_id = '".$tab_tmp[1]."'");
				$svc_id =& $DBRESULT2->fetchRow();
				$DBRESULT2->free();
				
				$template_id = getDefaultGraph($svc_id["service_id"], 1);
				$DBRESULT2 =& $pearDB->query("SELECT * FROM giv_graphs_template WHERE graph_id = '".$template_id."' LIMIT 1");
				$GraphTemplate =& $DBRESULT2->fetchRow();
				$DBRESULT2->free();
				if ($GraphTemplate["split_component"] == 1 ) 
					$split = 1;
			}	
			if ($type == "SM"){
				$other_services = array();
				$DBRESULT2 =& $pearDBO->query("SELECT * FROM index_data WHERE `trashed` = '0' AND special = '1' AND service_description = 'meta_".$id."' ORDER BY service_description");
				if ($svc_id =& $DBRESULT2->fetchRow()){
					if (preg_match("/meta_([0-9]*)/", $svc_id["service_description"], $matches)){
						$DBRESULT_meta =& $pearDB->query("SELECT meta_name FROM meta_service WHERE `meta_id` = '".$matches[1]."'");
						$meta =& $DBRESULT_meta->fetchRow();
						$svc_id["service_description"] = $meta["meta_name"];
					}	
					$svc_id["service_description"] = str_replace("#S#", "/", $svc_id["service_description"]);
					$svc_id["service_description"] = str_replace("#BS#", "\\", $svc_id["service_description"]);
					$svc_id[$svc_id["id"]] = $svc_id["service_description"];
				}
				$DBRESULT2->free();
			}
			$index = null;
			$index = $svc_id["id"];
			$index_id = $svc_id["id"];
			$metrics = array();
			$name = $svc_id["service_description"];	
		
			if ($template_id ==  1 && isset($svc_id["service_id"]))
				$template_id = getDefaultGraph($svc_id["service_id"], 1);
	
			$DBRESULT2 =& $pearDB->query("SELECT * FROM giv_graphs_template WHERE graph_id = '".$template_id."' LIMIT 1");
			$GraphTemplate =& $DBRESULT2->fetchRow();
			if (($GraphTemplate["split_component"] == 1 && !isset($_GET["split"])) || (isset($_GET["split"]) && $_GET["split"]["split"] == 1))
				$split = 1;
		
			$DBRESULT2 =& $pearDBO->query("SELECT * FROM metrics WHERE index_id = '".$svc_id["id"]."' ORDER BY `metric_name`");
			$counter = 0;
			while ($metrics_ret =& $DBRESULT2->fetchRow()){			
				$metrics[$metrics_ret["metric_id"]]["metric_name"] = str_replace("#S#", "/", $metrics_ret["metric_name"]);
				$metrics[$metrics_ret["metric_id"]]["metric_name"] = str_replace("#BS#", "\\", $metrics[$metrics_ret["metric_id"]]["metric_name"]);
				$metrics[$metrics_ret["metric_id"]]["metric_id"] = $metrics_ret["metric_id"];
				$metrics[$metrics_ret["metric_id"]]["class"] = $tab_class[$counter % 2];
				$counter++;
			}
				
			/*
			 *  verify if metrics in parameter is for this index
			 */
			$metrics_active =& $_GET["metric"];
			$pass = 0;
			if (isset($metrics_active))
				foreach ($metrics_active as $key => $value)
					if (isset($metrics[$key]))
						$pass = 1;
			
			if (isset($_GET["metric"]) && $pass){
				$DBRESULT =& $pearDB->query("DELETE FROM `ods_view_details` WHERE index_id = '".$id."'");
				foreach ($metrics_active as $key => $metric){
					if (isset($metrics_active[$metric["metric_id"]])){
						$DBRESULT =& $pearDB->query("INSERT INTO `ods_view_details` (`metric_id`, `contact_id`, `all_user`, `index_id`) VALUES ('".$key."', '".$contact_id."', '0', '".$index_id."');");
					}
				}
			} else {
				$DBRESULT =& $pearDB->query("SELECT metric_id FROM `ods_view_details` WHERE index_id = '".$index_id."' AND `contact_id` = '".$contact_id."'");
				$metrics_active = array();
				if ($DBRESULT->numRows())
					while ($metric =& $DBRESULT->fetchRow())
						$metrics_active[$metric["metric_id"]] = 1;		
				else
					foreach ($metrics as $key => $value)
						$metrics_active[$key] = 1;	
			}
		
			if ($multi)
				$buffer->startElement("multi_svc");				
			else
				$buffer->startElement("svc_zoom");				
	
			$buffer->writeElement("sid", $sid);
			$buffer->writeElement("id", $id);
			$buffer->writeElement("flagperiod", $flag_period);
			$buffer->writeElement("opid", $openid);
			$buffer->writeElement("start", $start);
			$buffer->writeElement("end", $end);
			$buffer->writeElement("index", $index_id);
			$buffer->writeElement("split", $split);
			$buffer->writeElement("critical", $critical);
			$buffer->writeElement("warning", $warning);
			$buffer->writeElement("status", $status);
			$buffer->writeElement("tpl", $template_id);
			$buffer->writeElement("multi", $multi);
						
			if (!$multi){
				if ($split == 0){
					$buffer->startElement("metricsTab");					
					$flag = 0;
					foreach ($metrics as $id => $metric)	{
						if(isset($_GET["metric"]) && $_GET["metric"][$id] == 1){
							if ($flag)
								$buffer->text("&amp;");
							$flag = 1;
							$buffer->text("metric[".$id."]=1");							
						}
					}
					$buffer->endElement();					
				} else	{
					$buffer->writeElement("metricsTab", "..");					
				}			
				foreach ($metrics as $id => $metric){
					$buffer->startElement("metrics");
					$buffer->writeElement("metric_id", $id);					
					if (isset($_GET["metric"]) && $_GET["metric"][$id] == 0)
						$buffer->writeElement("select", "0");						
					else
						$buffer->writeElement("select", "1");						
					$buffer->writeElement("metric_name", $metric["metric_name"]);
					$buffer->endElement();					
				}
				
				foreach ($graphTs as $id => $tpl){
					if ($tpl && $id){
						$buffer->startElement("tpl");
						$buffer->writeElement("tpl_name", $tpl);
						$buffer->writeElement("tpl_id", $id);						
						$buffer->endElement();						
					}
				}
				$buffer->endElement();				
			} else {
				foreach ($metrics as $id => $metric){
					$buffer->startElement("metrics");
					$buffer->writeElement("metric_id", $id);
					$buffer->writeElement("select", "1");
					$buffer->writeElement("metric_name", $metric["metric_name"]);
					$buffer->endElement();					
				}
				$buffer->endElement();				
			}
		} 
		$metrics = array();	
	} 
	
	/*
	 * LANG
	 */
		
	$buffer->startElement("lang");
	$buffer->writeElement("giv_gg_tpl", _("Template"));
	$buffer->writeElement("advanced", _("Options"));
	$buffer->writeElement("giv_split_component", _("Split Components"));
	$buffer->writeElement("status", _("Display Status"));
	$buffer->writeElement("warning", _("Warning"));
	$buffer->writeElement("critical", _("Critical"));
	$buffer->endElement();		
	
	/*
	 * if you want debug img..
	 */
	$debug = 0;
	$buffer->writeElement("debug", $debug);
	$buffer->endElement();
	$buffer->output();
?>