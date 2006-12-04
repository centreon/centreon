<?
/**
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/gpl.txt
Developped by : Cedrick Facon

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/

	if (!isset($oreon))
		exit;
		
	$tt = 0;
	$start_date_select = 0;
	$end_date_select = 0;

	$path = "./include/reporting/dashboard";

	# Smarty template Init
	$tpl = new Smarty();
	$tpl = initSmartyTpl($path, $tpl, "");
	$tpl->assign('o', $o);
	require_once './class/other.class.php';
	require_once './include/common/common-Func.php';
	require_once('simple-func.php');
	require_once('reporting-func.php');
	include("./include/monitoring/log/choose_log_file.php");

	# LCA 
	$lcaHostByName = getLcaHostByName($pearDB);
	$lcaHostByID = getLcaHostByID($pearDB);
	$lcaHoststr = getLCAHostStr($lcaHostByID["LcaHost"]);
	$lcaHostGroupstr = getLCAHGStr($lcaHostByID["LcaHostGroup"]);
	isset ($_GET["host"]) ? $mhost = $_GET["host"] : $mhost = NULL;
	isset ($_POST["host"]) ? $mhost = $_POST["host"] : $mhost = $mhost;


	#
	## Selection de l'host
	#
	$formHost = new HTML_QuickForm('formHost', 'post', "?p=".$p);

	#
	## period selection
	#
	$type_period = (isset($_GET["type_period"])) ? $_GET["type_period"] : "predefined";
	$type_period = (isset($_POST["type_period"])) ? $_POST["type_period"] : $type_period;
	$period1 = "today";
	if($mhost)	{
		$end_date_select = 0;
		$start_date_select= 0;
		$period1 = 0;
		if($type_period == "customized") {
			$end = (isset($_POST["end"])) ? $_POST["end"] : NULL;
			$end = (isset($_GET["end"])) ? $_GET["end"] : $end;
			$start = (isset($_POST["start"])) ? $_POST["start"] : NULL;
			$start = (isset($_GET["start"])) ? $_GET["start"] : $start;		
			getDateSelect_customized($end_date_select, $start_date_select, $start,$end);
			$formHost->addElement('hidden', 'end', $end);
			$formHost->addElement('hidden', 'start', $start);
			$period1 = "NULL";
		}
		else {
			$period1 = (isset($_POST["period"])) ? $_POST["period"] : NULL; 
			$period1 = (isset($_GET["period"])) ? $_GET["period"] : $period1;
			getDateSelect_predefined($end_date_select, $start_date_select, $period1);
			$formHost->addElement('hidden', 'period', $period1);
			$period1 = is_null($period1) ? "today" : $period1;
		}
		$host_id = getMyHostID($mhost);
		$sd = $start_date_select;
		$ed = $end_date_select;

		#
		## recupere les log host en base
		#
		$Tup = NULL;
		$Tdown = NULL;
		$Tunreach = NULL;
		$Tnone = NULL;
		getLogInDbForHost($Tup, $Tdown, $Tunreach, $Tnone, $pearDB, $host_id, $start_date_select, $end_date_select);
		$tab_svc_bdd = array();
		getLogInDbForSVC($tab_svc_bdd, $pearDB, $host_id, $start_date_select, $end_date_select);
	}

	#
	## Selection de l'host/service (suite)
	#
	$res =& $pearDB->query("SELECT host_name FROM host where host_activate = '1' AND host_id IN (".$lcaHoststr.") and host_register = '1' ORDER BY host_name");
	while ($res->fetchInto($h))
		if (IsHostReadable($lcaHostByName, $h["host_name"]))
			$host[$h["host_name"]] = $h["host_name"];

	$selHost =& $formHost->addElement('select', 'host', $lang["h"], $host, array("onChange" =>"this.form.submit();"));

	if (isset($_POST["host"])){
		$formHost->setDefaults(array('host' => $_POST["host"]));
	}else if (isset($_GET["host"])){
		$formHost->setDefaults(array('host' => $_GET["host"]));
	}


	#
	## fourchette de temps
	#
	$period = array();
	$period[""] = "";
	$period["today"] = $lang["today"];
	$period["yesterday"] = $lang["yesterday"];
	$period["thisweek"] = $lang["thisweek"];
	$period["last7days"] = $lang["last7days"];
	$period["thismonth"] = $lang["thismonth"];
	$period["last30days"] = $lang["last30days"];
	$period["lastmonth"] = $lang["lastmonth"];
	$period["thisyear"] = $lang["thisyear"];
	$period["lastyear"] = $lang["lastyear"];
	$formPeriod1 = new HTML_QuickForm('FormPeriod1', 'post', "?p=".$p."&type_period=predefined");
	isset($mhost) ? $formPeriod1->addElement('hidden', 'host', $mhost) : NULL;
	$formPeriod1->addElement('hidden', 'timeline', "1");
	$formPeriod1->addElement('header', 'title', $lang["m_predefinedPeriod"]);
	$selHost =& $formPeriod1->addElement('select', 'period', $lang["m_predefinedPeriod"], $period, array("onChange" =>"this.form.submit();"));
	$formPeriod2 = new HTML_QuickForm('FormPeriod2', 'post', "?p=".$p."&type_period=customized");
	$formPeriod2->addElement('hidden', 'timeline', "1");
	isset($mhost) ? $formPeriod2->addElement('hidden', 'host', $mhost) : NULL;
	$formPeriod2->addElement('header', 'title', $lang["m_customizedPeriod"]);
	$formPeriod2->addElement('text', 'start', $lang["m_start"]);
	$formPeriod2->addElement('button', "startD", $lang['modify'], array("onclick"=>"displayDatePicker('start')"));
	$formPeriod2->addElement('text', 'end', $lang["m_end"]);
	$formPeriod2->addElement('button', "endD", $lang['modify'], array("onclick"=>"displayDatePicker('end')"));
	$sub =& $formPeriod2->addElement('submit', 'submit', $lang["m_view"]);
	$res =& $formPeriod2->addElement('reset', 'reset', $lang["reset"]);

	if($mhost){
	#
	## if today is include in the time period
	#
	$tab_log = array();
	$day = date("d",time());
	$year = date("Y",time());
	$month = date("m",time());
	$startTimeOfThisDay = mktime(0, 0, 0, $month, $day, $year);

	if($startTimeOfThisDay  < ($end_date_select)){
		$tmp = $oreon->Nagioscfg["log_file"];



		$tab = parseFile($tmp,time(), $startTimeOfThisDay, $mhost, NULL);
		$tab_log = $tab["tab_log"];


		if (isset($tab[$mhost]))
		{
			#
			## last host alert for today
			#
			if(!strncmp($tab[$mhost]["current_state"], "UP", 2))
				$tab[$mhost]["timeUP"] += ($end_date_select-$tab[$mhost]["current_time"]);
			elseif(!strncmp($tab[$mhost]["current_state"], "DOWN", 4))
				$tab[$mhost]["timeDOWN"] += ($end_date_select-$tab[$mhost]["current_time"]);
			elseif(!strncmp($tab[$mhost]["current_state"], "UNREACHABLE", 11))
				$tab[$mhost]["timeUNREACHABLE"] += ($end_date_select-$tab[$mhost]["current_time"]);
			else
				$tab[$mhost]["timeNONE"] += ($end_date_select-$tab[$mhost]["current_time"]);



			#
			## add log day
			#
			$Tup += $tab[$mhost]["timeUP"];
			$Tdown += $tab[$mhost]["timeDOWN"];
		 	$Tunreach += $tab[$mhost]["timeUNREACHABLE"];
			$Tnone += (($end_date_select - $start_date_select) - ($Tup + $Tdown + $Tunreach));
			$tab_svc =array();
			$i = 0;

			while (list($key, $value) = each($tab[$mhost]["tab_svc_log"])) {
				$tab_tmp = $value;
				$tab_tmp["svcName"] = $key;
				if(!strncmp($tab_tmp["current_state"], "OK", 2))
					$tab_tmp["timeOK"] += (time()-$tab_tmp["current_time"]);
				elseif(!strncmp($tab_tmp["current_state"], "WARNING", 7))
					$tab_tmp["timeWARNING"] += (time()-$tab_tmp["current_time"]);
				elseif(!strncmp($tab_tmp["current_state"], "UNKNOWN", 7))
					$tab_tmp["timeUNKNOWN"] += (time()-$tab_tmp["current_time"]);
				elseif(!strncmp($tab_tmp["current_state"], "CRITICAL", 8))
					$tab_tmp["timeCRITICAL"] += (time()-$tab_tmp["current_time"]);
				else
					$tab_tmp["timeNONE"] += (time()-$tab_tmp["current_time"]);
				$tt = $end_date_select - $start_date_select;
				$svc_id = $tab_tmp["service_id"];

				$archive_svc_ok =  isset($tab_svc_bdd[$svc_id]["Tok"]) ? $tab_svc_bdd[$svc_id]["Tok"] : 0;
				$archive_svc_warn = isset($tab_svc_bdd[$svc_id]["Twarn"]) ? $tab_svc_bdd[$svc_id]["Twarn"] : 0;
				$archive_svc_unknown = isset($tab_svc_bdd[$svc_id]["Tunknown"]) ? $tab_svc_bdd[$svc_id]["Tunknown"] : 0;
				$archive_svc_cri = isset($tab_svc_bdd[$svc_id]["Tcri"]) ? $tab_svc_bdd[$svc_id]["Tcri"] : 0;

				$tab_tmp["PtimeOK"] = round(($archive_svc_ok +$tab_tmp["timeOK"]) / $tt *100,3);
				$tab_tmp["PtimeWARNING"] = round(($archive_svc_warn+$tab_tmp["timeWARNING"]) / $tt *100,3);
				$tab_tmp["PtimeUNKNOWN"] = round(($archive_svc_unknown+$tab_tmp["timeUNKNOWN"]) / $tt *100,3);
				$tab_tmp["PtimeCRITICAL"] = round(($archive_svc_cri+$tab_tmp["timeCRITICAL"]) / $tt *100,3);
				$tab_tmp["PtimeNONE"] = round( ( $tt - (($archive_svc_ok+$tab_tmp["timeOK"])
													 + ($archive_svc_warn+$tab_tmp["timeWARNING"])
													 + ($archive_svc_unknown+$tab_tmp["timeUNKNOWN"])
													 + ($archive_svc_cri+$tab_tmp["timeCRITICAL"])))  / $tt *100,3);

				/* les lignes suivante ne servent qu'a corriger un bug mineur correspondant a un decalage d'une seconde... */
				$tab_tmp["PtimeOK"] = number_format($tab_tmp["PtimeOK"], 2, '.', '');
				$tab_tmp["PtimeWARNING"] = number_format($tab_tmp["PtimeWARNING"], 2, '.', '');
				$tab_tmp["PtimeUNKNOWN"] = number_format($tab_tmp["PtimeUNKNOWN"], 2, '.', '');
				$tab_tmp["PtimeCRITICAL"] = number_format($tab_tmp["PtimeCRITICAL"], 2, '.', '');
				$tab_tmp["PtimeNONE"] = number_format($tab_tmp["PtimeNONE"], 2, '.', '');

				$tab_tmp["PtimeNONE"] = ($tab_tmp["PtimeNONE"] < 0.1) ? 0.00 : $tab_tmp["PtimeNONE"];
				/*end*/
				$tab_svc[$i++] = $tab_tmp;
			}
		}
	}
	else // today is not in the period
	{
		$i=0;
		foreach($tab_svc_bdd as $svc_id => $tab)
		{
			$tab_tmp = array();
			$tab_tmp["svcName"] = getMyServiceName($svc_id);
			$tab_tmp["service_id"] = $svc_id;
			$tt = $end_date_select - $start_date_select;
			$tab_tmp["PtimeOK"] = round($tab["Tok"] / $tt *100,3);
			$tab_tmp["PtimeWARNING"] = round( $tab["Twarn"]/ $tt *100,3);
			$tab_tmp["PtimeUNKNOWN"] = round( $tab["Tunknown"]/ $tt *100,3);
			$tab_tmp["PtimeCRITICAL"] = round( $tab["Tcri"]/ $tt *100,3);
			$tab_tmp["PtimeNONE"] = round( ( $tt - ($tab["Tok"] + $tab["Twarn"] + $tab["Tunknown"] + $tab["Tcri"])
												 )  / $tt *100,3);

			/* les lignes suivante ne servent qu'a corriger un bug mineur correspondant a un decalage d'une seconde... */
			$tab_tmp["PtimeOK"] = number_format($tab_tmp["PtimeOK"], 2, '.', '');
			$tab_tmp["PtimeWARNING"] = number_format($tab_tmp["PtimeWARNING"], 2, '.', '');
			$tab_tmp["PtimeUNKNOWN"] = number_format($tab_tmp["PtimeUNKNOWN"], 2, '.', '');
			$tab_tmp["PtimeCRITICAL"] = number_format($tab_tmp["PtimeCRITICAL"], 2, '.', '');
			$tab_tmp["PtimeNONE"] = number_format($tab_tmp["PtimeNONE"], 2, '.', '');

			$tab_tmp["PtimeNONE"] = ($tab_tmp["PtimeNONE"] < 0.1) ? 0.00 : $tab_tmp["PtimeNONE"];
			/*end*/

			$tab_svc[$i++] = $tab_tmp;
		}
	}
	#
	## calculate host %
	#
	$tab_resume = array();
	$tab = array();
	$timeTOTAL = $end_date_select - $start_date_select;
	$Tnone = $timeTOTAL - ($Tup + $Tdown + $Tunreach);
	if($Tnone < 0)
	$Tnone = 0;


	$tab["state"] = $lang["m_UpTitle"];
	$tab["time"] = Duration::toString($Tup);
	$tab["pourcentTime"] = round($Tup/$timeTOTAL*100,2);
	$tab["pourcentkTime"] = round($Tup/$timeTOTAL*100,2);
	$tab["style"] = "class='ListColCenter' style='background:" . $oreon->optGen["color_up"]."'";
	$tab_resume[0] = $tab;

	$tab["state"] = $lang["m_DownTitle"];
	$tab["time"] = Duration::toString($Tdown);
	$tab["pourcentTime"] = round($Tdown/$timeTOTAL*100,2);
	$tab["pourcentkTime"] = round($Tdown/$timeTOTAL*100,2);
	$tab["style"] = "class='ListColCenter' style='background:" . $oreon->optGen["color_down"]."'";
	$tab_resume[1] = $tab;

	$tab["state"] = $lang["m_UnreachableTitle"];
	$tab["time"] = Duration::toString($Tunreach);
	$tab["pourcentTime"] = round($Tunreach/$timeTOTAL*100,2);
	$tab["pourcentkTime"] = round($Tunreach/$timeTOTAL*100,2);
	$tab["style"] = "class='ListColCenter' style='background:" . $oreon->optGen["color_unreachable"]."'";
	$tab_resume[2] = $tab;


	$tab["state"] = $lang["m_PendingTitle"];
	$tab["time"] = Duration::toString($Tnone);
	$tab["pourcentTime"] = round($Tnone/$timeTOTAL*100,2);
	$tab["pourcentkTime"] = round($Tnone/$timeTOTAL*100,2);
	$tab["style"] = "class='ListColCenter' style='background:" . $oreon->optGen["color_unknown"]."'";
	$tab_resume[3] = $tab;

	$tpl->assign('infosTitle', $lang["m_duration"] . Duration::toString($end_date_select - $start_date_select));

	$start_date_select = date("d/m/Y ", $start_date_select);
	$end_date_select =  date("d/m/Y ", $end_date_select);

	$tpl->assign('host_name', $mhost);
	$status = "";
	foreach ($tab_resume  as $tb)
		if($tb["pourcentTime"] > 0)
			$status .= "&value[".$tb["state"]."]=".$tb["pourcentTime"];

	$tpl->assign('status', $status);
	$tpl->assign("tab_resume", $tab_resume);
	if(isset($tab_svc))
	$tpl->assign("tab_svc", $tab_svc);
	$tpl->assign("tab_log", $tab_log);
	$tpl->assign('infosTitle', $lang["m_duration"] . Duration::toString($tt));
	}## end of period requirement

	$tpl->assign('actualTitle', $lang["actual"]);

	$tpl->assign('date_start_select', $start_date_select);
	$tpl->assign('date_end_select', $end_date_select);
	$tpl->assign('to', $lang["m_to"]);
	$tpl->assign('period_name', $lang["m_period"]);


	$tpl->assign('style_ok', "class='ListColCenter' style='background:" . $oreon->optGen["color_ok"]."'");
	$tpl->assign('style_warning' , "class='ListColCenter' style='background:" . $oreon->optGen["color_warning"]."'");
	$tpl->assign('style_critical' , "class='ListColCenter' style='background:" . $oreon->optGen["color_critical"]."'");
	$tpl->assign('style_unknown' , "class='ListColCenter' style='background:" . $oreon->optGen["color_unknown"]."'");
	$tpl->assign('style_pending' , "class='ListColCenter' style='background:" . $oreon->optGen["color_pending"]."'");

	$tpl->assign('serviceTilte', $lang["m_serviceTilte"]);
	$tpl->assign('OKTitle', $lang["m_OKTitle"]);
	$tpl->assign('WarningTitle', $lang["m_WarningTitle"]);
	$tpl->assign('UnknownTitle', $lang["m_UnknownTitle"]);
	$tpl->assign('CriticalTitle', $lang["m_CriticalTitle"]);
	$tpl->assign('PendingTitle', $lang["m_PendingTitle"]);

	$tpl->assign('StateTitle', $lang["m_StateTitle"]);
	$tpl->assign('TimeTitle', $lang["m_TimeTitle"]);
	$tpl->assign('TimeTotalTitle', $lang["m_TimeTotalTitle"]);
	$tpl->assign('KnownTimeTitle', $lang["m_KnownTimeTitle"]);

	$tpl->assign('DateTitle', $lang["m_DateTitle"]);
	$tpl->assign('EventTitle', $lang["m_EventTitle"]);
	$tpl->assign('HostTitle', $lang["m_HostTitle"]);
	$tpl->assign('InformationsTitle', $lang["m_InformationsTitle"]);

	$tpl->assign('periodTitle', $lang["m_selectPeriodTitle"]);
	$tpl->assign('resumeTitle', $lang["m_hostResumeTitle"]);
	$tpl->assign('logTitle', $lang["m_hostLogTitle"]);
	$tpl->assign('svcTitle', $lang["m_hostSvcAssocied"]);

	$period1 = (!$period1) ? "today": $period1;
	$formPeriod1->setDefaults(array('period' => $period1));
	$tpl->assign('period', "&period=".$period1);

	$tpl->assign('hostID', getMyHostID($mhost));
	$color = array();
	$color["UNKNOWN"] =  substr($oreon->optGen["color_unknown"], 1);
	$color["UP"] =  substr($oreon->optGen["color_up"], 1);
	$color["DOWN"] =  substr($oreon->optGen["color_down"], 1);
	$color["UNREACHABLE"] =  substr($oreon->optGen["color_unreachable"], 1);
	$tpl->assign('color', $color);

	$renderer1 = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
	$formPeriod1->accept($renderer1);
	$tpl->assign('formPeriod1', $renderer1->toArray());
	$renderer2 = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
	$formPeriod2->accept($renderer2);
	$tpl->assign('formPeriod2', $renderer2->toArray());


	#Apply a template definition
	$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
	$formHost->accept($renderer);
	$tpl->assign('formHost', $renderer->toArray());


	$tpl->assign('lang', $lang);
	$tpl->assign("p", $p);

	include('ajaxReporting_js.php');

	$tpl->display("template/viewHostLog.ihtml");

?>