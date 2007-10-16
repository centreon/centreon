<?
/**
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
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
		exit();

	$tS = $oreon->optGen["AjaxTimeReloadStatistic"] * 1000;
	$tM = $oreon->optGen["AjaxTimeReloadMonitoring"] * 1000;
	$oreon->optGen["AjaxFirstTimeReloadStatistic"] == 0 ? $tFS = 10 : $tFS = $oreon->optGen["AjaxFirstTimeReloadStatistic"] * 1000;
	$oreon->optGen["AjaxFirstTimeReloadMonitoring"] == 0 ? $tFM = 10 : $tFM = $oreon->optGen["AjaxFirstTimeReloadMonitoring"] * 1000;
	$sid = session_id();
	$time = time();
	
	if($num < 0)
		$num =0;
?>
<SCRIPT LANGUAGE="JavaScript">
var _debug = 0;

var _search = '<?=$search?>';
var _sid='<?=$sid?>';
var _search_type_host='<?=$search_type_host?>';
var _search_type_service='<?=$search_type_service?>';
var _num='<?=$num?>';
var _limit='<?=$limit?>';
var _sort_type='<?=$sort_type?>';
var _order='<?=$order?>';
var _date_time_format_status='<?=$lang["date_time_format_status"]?>';
var _o='<?=$o?>';
var _p='<?=$p?>';

var _addrXSL = "./include/monitoring/status/status-ndo/templates/serviceSchedule.xsl";
var _timeoutID = 0;
var _on = 1;
var _time_reload = <?=$tM?>;
var _time_live = <?=$tFM?>;
var _nb = 0;
var _oldInputFieldValue = '<?=$search?>';
var _currentInputFieldValue=""; // valeur actuelle du champ texte
var _resultCache=new Object();
var _first = 1;
var _lock = 0;
var _instance = 'ALL';
var _default_instance = '0';
var _nc = 0;
<?
include_once("makeJS_Common.php");
?>

function set_header_title(){
	var _img_asc = mk_img('./img/icones/7x7/sort_asc.gif', "asc");
	var _img_desc = mk_img('./img/icones/7x7/sort_desc.gif', "desc");

	if(document.getElementById('host_name')){

		var h = document.getElementById('host_name');
		h.innerHTML = '<?=$lang['m_mon_hosts']?>';
	  	h.indice = 'host_name';
	  	h.onclick=function(){change_type_order(this.indice)};
		
		var h = document.getElementById('service_description');
		h.innerHTML = '<?=$lang['m_mon_services']?>';
	  	h.indice = 'service_description';
	  	h.onclick=function(){change_type_order(this.indice)};
	
		var h = document.getElementById('current_state');
		h.innerHTML = '<?=$lang['mon_status']?>';
	  	h.indice = 'current_state';
	  	h.onclick=function(){change_type_order(this.indice)};
	
	
		var h = document.getElementById('last_state_change');
		h.innerHTML = '<?=$lang['mon_duration']?>';
	  	h.indice = 'last_state_change';
	  	h.onclick=function(){change_type_order(this.indice)};
	
		var h = document.getElementById('last_check');
		h.innerHTML = '<?=$lang['mon_last_check']?>';
	  	h.indice = 'last_check';
	  	h.onclick=function(){change_type_order(this.indice)};
	
		var h = document.getElementById('current_attempt');
		h.innerHTML = '<?=$lang['m_mon_try']?>';
	  	h.indice = 'current_attempt';
	  	h.onclick=function(){change_type_order(this.indice)};
	
		var h = document.getElementById('plugin_output');
		h.innerHTML = '<?=$lang['mon_status_information']?>';
	  	h.indice = 'plugin_output';
	  	h.onclick=function(){change_type_order(this.indice)};
	
	
		var h = document.getElementById(_sort_type);
		var _linkaction_asc = document.createElement("a");
		if(_order == 'ASC')
			_linkaction_asc.appendChild(_img_asc);
		else
			_linkaction_asc.appendChild(_img_desc);
		_linkaction_asc.href = '#' ;
		_linkaction_asc.onclick=function(){change_order()};
		h.appendChild(_linkaction_asc);

	}

}



function monitoring_refresh()	{
	_tmp_on = _on;
	_time_live = _time_reload;
	_on = 1;
	window.clearTimeout(_timeoutID);

	initM(<?=$tM?>,"<?=$sid?>","<?=$o?>");
	_on = _tmp_on;

	viewDebugInfo('refresh');
}

function monitoring_play()	{
	document.getElementById('JS_monitoring_play').style.display = 'none';
	document.getElementById('JS_monitoring_pause').style.display = 'block';	
	document.getElementById('JS_monitoring_pause_gray').style.display = 'none';
	document.getElementById('JS_monitoring_play_gray').style.display = 'block';
	_on = 1;
	initM(<?=$tM?>,"<?=$sid?>","<?=$o?>");
}

function monitoring_pause()	{
	document.getElementById('JS_monitoring_play').style.display = 'block';
	document.getElementById('JS_monitoring_pause_gray').style.display = 'block';
	document.getElementById('JS_monitoring_play_gray').style.display = 'none';
	document.getElementById('JS_monitoring_pause').style.display='none';
	_on = 0;
	window.clearTimeout(_timeoutID);
}

function initM(_time_reload,_sid,_o){
	construct_selecteList_ndo_instance('advanced_options');
	if(!document.getElementById('debug')){
		var _divdebug = document.createElement("div");
		_divdebug.id = 'debug';
		var _debugtable = document.createElement("table");
		_debugtable.id = 'debugtable';
		var _debugtr = document.createElement("tr");
		_debugtable.appendChild(_debugtr);
		_divdebug.appendChild(_debugtable);
		_header = document.getElementById('header');
		_header.appendChild(_divdebug);
	}

	if(_first){
		viewDebugInfo('--loop--');
	
		mainLoop();
		_first = 0;
	}
	_time=<?=$time?>;
	if(_on)
	goM(_time_reload,_sid,_o);
}

function goM(_time_reload,_sid,_o){

	_lock = 1;
	var proc = new Transformation();
	var _addrXML = "./include/monitoring/engine/MakeXML_Ndo_serviceSchedule.php?"+'&sid='+_sid+'&search='+_search+'&search_type_host='+_search_type_host+'&search_type_service='+_search_type_service+'&num='+_num+'&limit='+_limit+'&sort_type='+_sort_type+'&order='+_order+'&date_time_format_status='+_date_time_format_status+'&o='+_o+'&p='+_p+'&host_name=<?=$host_name?>'+'&instance='+_instance+'&nc='+_nc;
	proc.setXml(_addrXML);
	proc.setXslt(_addrXSL);
	proc.transform("forAjax");


	_lock = 0;	

	viewDebugInfo('--end--');


	_timeoutID = setTimeout('goM("'+ _time_reload +'","'+ _sid +'","'+_o+'")', _time_reload);
	_time_live = _time_reload;
	_on = 1;
	
	
	set_header_title();
}
</SCRIPT>