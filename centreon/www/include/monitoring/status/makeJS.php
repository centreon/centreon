<?
/**
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/gpl.txt
Developped by : Julien Mathis - Romain Le Merlus

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
	
	
?>

<SCRIPT LANGUAGE="JavaScript">

_time_reload = <?=$tM?>;
_time_live = <?=$tFM?>;

function monitoring_time()	{

	if(_time_live > 999 && _on)
	{
		document.getElementById('time_live').innerHTML = (_time_live / 1000);
		_time_live = _time_live - 1000;
		_timeoutID = setTimeout('monitoring_time()', 1000);	
	}
	else if(_time_live < 999)
	{
		document.getElementById('time_live').innerHTML = *;
		_time_live = 0;
		//_on = 0;
	}
	else
	{
		//_on = 0;
	}
}


function monitoring_refresh()	{	
_time_live = _time_reload;
_on = 1;
window.clearTimeout(_timeoutID);
//monitoring_time();
initM(<?=$tM?>,"<?=$sid?>");
}

function monitoring_play()	{
_on = 1;
initM(<?=$tM?>,"<?=$sid?>");
}

function monitoring_pause()	{
// document.images["image_pause"].src='./img/icones/16x16/media_pause2.png'; 
_on = 0;
window.clearTimeout(_timeoutID);
}


</SCRIPT>
	