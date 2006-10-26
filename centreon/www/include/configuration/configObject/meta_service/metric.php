<?php
/** 
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/gpl.txt
Developped by : Julien Mathis - Romain Le Merlus

This unit, called � Oreon Meta Service � is developped by Merethis company for Lafarge Group, 
under the direction of Jean Baptiste Sarrodie <jean-baptiste@sarrodie.org>

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


	#
	## Database retrieve information
	#
	require_once("./DBPerfparseConnect.php");
	
	$metric = array();
	if (($o == "cs" || $o == "ws") && $msr_id)	{	
		# Set base value
		$res =& $pearDB->query("SELECT * FROM meta_service_relation WHERE msr_id = '".$msr_id."'");
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
		# Set base value
		$metric1 = array_map("myDecode", $res->fetchRow());
		$res =& $pearDBpp->query("SELECT * FROM perfdata_service_metric WHERE metric_id = '".$metric1["metric_id"]."'");		
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
		$metric2 = array_map("myDecode", $res->fetchRow());
		$metric = array_merge($metric1, $metric2);
		$host_id = $metric1["host_id"];
		$metric["metric_sel"][0] = getMyServiceID($metric["service_description"], $metric["host_id"]);
		$metric["metric_sel"][1] = $metric["metric_id"];		
	}
	
	#
	## Database retrieve information for differents elements list we need on the page
	#

	# Host comes from DB -> Store in $hosts Array
	$hosts = array(NULL=>NULL);
	$res =& $pearDB->query("SELECT DISTINCT host_id, host_name FROM host WHERE host_register = '1' AND host_activate = '1' ORDER BY host_name");
	if (PEAR::isError($pearDB)) {
		print "Mysql Error : ".$pearDB->getMessage();
	}
	while($res->fetchInto($host))
		if ($oreon->user->admin || !$isRestreint || ($isRestreint && isset($lcaHostByName["LcaHost"][$host["host_name"]])))
			$hosts[$host["host_id"]] = $host["host_name"];
	$res->free();
	
	$services1 = array();
	$services2 = array();
	if ($host_id)	{
		$services = array(NULL=>NULL);
		$services = getMyHostServices($host_id);
		foreach ($services as $key=>$value)	{
			$res =& $pearDBpp->query("SELECT DISTINCT metric_id, metric, unit FROM perfdata_service_metric WHERE host_name = '".getMyHostName($host_id)."' AND service_description = '".$value."' ORDER BY metric, unit");
			while ($res->fetchInto($metricSV))	{
				$services1[$key] = $value;
				$services2[$key][$metricSV["metric_id"]] = $metricSV["metric"]."  (".$metricSV["unit"].")";
			}
		}
		$res->free();
	}
	
	$debug = 0;
	$attrsTextI		= array("size"=>"3");
	$attrsText 		= array("size"=>"30");
	$attrsTextarea 	= array("rows"=>"5", "cols"=>"40");
	
	#
	## Form begin
	#
	
	$form = new HTML_QuickForm('Form', 'post', "?p=".$p);
	if ($o == "as")
		$form->addElement('header', 'title', $lang["mss_add"]);
	else if ($o == "cs")
		$form->addElement('header', 'title', $lang["mss_change"]);
	else if ($o == "ws")
		$form->addElement('header', 'title', $lang["mss_view"]);
	#
	## Indicator basic information
	#
	
	$redirect =& $form->addElement('hidden', 'o');
	$redirect->setValue($o);
	$formMsrId =& $form->addElement('hidden', 'msr_id');
	$formMsrId->setValue($msr_id);
	$formMetaId =& $form->addElement('hidden', 'meta_id');
	$formMetaId->setValue($meta_id);
	$formMetricId =& $form->addElement('hidden', 'metric_id');
	$formMetricId->setValue($metric_id);
   
	$hn =& $form->addElement('select', 'host_id', $lang["h"], $hosts, array("onChange"=>"this.form.submit()"));
	$sel =& $form->addElement('hierselect', 'metric_sel', $lang["sv"]);
	$sel->setOptions(array($services1, $services2));
	
	$tab = array();
	$tab[] = &HTML_QuickForm::createElement('radio', 'activate', null, $lang["enable"], '1');
	$tab[] = &HTML_QuickForm::createElement('radio', 'activate', null, $lang["disable"], '0');
	$form->addGroup($tab, 'activate', $lang["status"], '&nbsp;');
	$form->setDefaults(array('activate' => '1'));
	$form->addElement('textarea', 'msr_comment', $lang["cmt_comment"], $attrsTextarea);
	
	$tab = array();
	$tab[] = &HTML_QuickForm::createElement('radio', 'action', null, $lang['actionList'], '1');
	$tab[] = &HTML_QuickForm::createElement('radio', 'action', null, $lang['actionForm'], '0');
	$form->addGroup($tab, 'action', $lang["action"], '&nbsp;');
	$form->setDefaults(array('action'=>'1'));
/*	
	if ($o == "as")	{
		$form->addRule('host_name', $lang['ErrRequired'], 'required');
		$form->addRule('metric_sel', $lang['ErrRequired'], 'required');
		$form->addRule('meta_id', $lang['ErrRequired'], 'required');
	}
*/		
	# Just watch
	if ($o == "ws")	{		
		$form->addElement("button", "change", $lang['modify'], array("onClick"=>"javascript:window.location.href='?p=".$p."&o=cs&msr_id=".$msr_id."'"));
	    $form->setDefaults($metric);
		$form->freeze();
	}
	# Modify
	else if ($o == "cs")	{
		$subC =& $form->addElement('submit', 'submitC', $lang["save"]);
		$res =& $form->addElement('reset', 'reset', $lang["reset"]);
	    $form->setDefaults($metric);
	    $hn->freeze();
	    $sel->freeze();
	}
	# Add
	else if ($o == "as")	{
		$subA =& $form->addElement('submit', 'submitA', $lang["save"]);
		$res =& $form->addElement('reset', 'reset', $lang["reset"]);
	}
  
  	$valid = false;
	if (((isset($_POST["submitA"]) && $_POST["submitA"]) || (isset($_POST["submitC"]) && $_POST["submitC"])) && $form->validate())	{
		$msrObj =& $form->getElement('msr_id');
		if ($form->getSubmitValue("submitA"))
			$msrObj->setValue(insertMetric($meta_id));
		else if ($form->getSubmitValue("submitC"))
			updateMetric($msrObj->getValue());
		$o = "ws";
		$form->addElement("button", "change", $lang['modify'], array("onClick"=>"javascript:window.location.href='?p=".$p."&o=cs&msr_id=".$msrObj->getValue()."'"));
		$form->freeze();
		$valid = true;
    }
	$action = $form->getSubmitValue("action");
	if ($valid && $action["action"]["action"])
		require_once($path."listMetric.php");
	else	{	
		# Smarty template Init
		$tpl = new Smarty();
		$tpl = initSmartyTpl($path, $tpl);
			
		#Apply a template definition	
		$renderer =& new HTML_QuickForm_Renderer_ArraySmarty($tpl);
		$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
		$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
		$form->accept($renderer);	
		
		$tpl->assign('form', $renderer->toArray());	
		$tpl->assign('o', $o);
		$tpl->assign('valid', $valid);
		$tpl->display("metric.ihtml");
    }
?>