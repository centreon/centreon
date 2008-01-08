<?php
/**
Centreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
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
		
	include("./include/common/autoNumLimit.php");
	
	if (isset($search))
		$DBRESULT = & $pearDB->query("SELECT COUNT(*) FROM service sv WHERE (sv.service_description LIKE '%".htmlentities($search, ENT_QUOTES)."%' OR sv.service_alias LIKE '%".htmlentities($search, ENT_QUOTES)."%') AND sv.service_register = '0'");
	else
		$DBRESULT = & $pearDB->query("SELECT COUNT(*) FROM service sv WHERE service_register = '0'");
	if (PEAR::isError($DBRESULT))
		print "DB Error : ".$DBRESULT->getDebugInfo()."<br>";

	$tmp = & $DBRESULT->fetchRow();
	$rows = $tmp["COUNT(*)"];
	
	/*
	 * start quickSearch form
	 */
	$advanced_search = 1;
	include_once("./include/common/quickSearch.php");

	include("./include/common/checkPagination.php");

	/*
	 * Smarty template Init
	 */
	$tpl = new Smarty();
	$tpl = initSmartyTpl($path, $tpl);

	/*
	 * start header menu
	 */
	$tpl->assign("headerMenu_icone", "<img src='./img/icones/16x16/pin_red.gif'>");
	$tpl->assign("headerMenu_desc", $lang['stm']);
	$tpl->assign("headerMenu_alias", $lang['sv_alias']);
	$tpl->assign("headerMenu_parent", $lang['stm_parent']);
	$tpl->assign("headerMenu_status", $lang['status']);
	$tpl->assign("headerMenu_options", $lang['options']);
	
	/*
	 * Service Template Model list
	 */
	if ($search)
		$rq = "SELECT sv.service_id, sv.service_description, sv.service_alias, sv.service_activate, sv.service_template_model_stm_id FROM service sv WHERE (sv.service_description LIKE '%".htmlentities($search, ENT_QUOTES)."%' OR sv.service_alias LIKE '%".htmlentities($search, ENT_QUOTES)."%') AND sv.service_register = '0' ORDER BY service_description LIMIT ".$num * $limit.", ".$limit;
	else
		$rq = "SELECT sv.service_id, sv.service_description, sv.service_alias, sv.service_activate, sv.service_template_model_stm_id FROM service sv WHERE sv.service_register = '0' ORDER BY service_description LIMIT ".$num * $limit.", ".$limit;
	$DBRESULT = & $pearDB->query($rq);
	if (PEAR::isError($DBRESULT))
		print "DB Error : ".$DBRESULT->getDebugInfo()."<br>";

	$search = tidySearchKey($search, $advanced_search);

	$form = new HTML_QuickForm('select_form', 'POST', "?p=".$p);
	/*
	 * Different style between each lines
	 */
	$style = "one";
	
	/*
	 * Fill a tab with a mutlidimensionnal Array we put in $tpl
	 */
	$elemArr = array();
	for ($i = 0; $DBRESULT->fetchInto($service); $i++) {
		$moptions = "";
		$selectedElements =& $form->addElement('checkbox', "select[".$service['service_id']."]");	
		if ($service["service_activate"])
			$moptions .= "<a href='oreon.php?p=".$p."&service_id=".$service['service_id']."&o=u&limit=".$limit."&num=".$num."&search=".$search."'><img src='img/icones/16x16/element_previous.gif' border='0' alt='".$lang['disable']."'></a>&nbsp;&nbsp;";
		else
			$moptions .= "<a href='oreon.php?p=".$p."&service_id=".$service['service_id']."&o=s&limit=".$limit."&num=".$num."&search=".$search."'><img src='img/icones/16x16/element_next.gif' border='0' alt='".$lang['enable']."'></a>&nbsp;&nbsp;";
		$moptions .= "&nbsp;";
		$moptions .= "<input onKeypress=\"if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr[".$service['service_id']."]'></input>";
		
		/*
		 * If the description of our Service Model is in the Template definition, we have to catch it, whatever the level of it :-)
		 */
		if (!$service["service_description"])
			$service["service_description"] = getMyServiceName($service['service_template_model_stm_id']);
		
		/* 
		 * TPL List 
		 */
		$tplArr = array();
		$tplStr = "";
		$tplArr = getMyServiceTemplateModels($service["service_template_model_stm_id"]);
		if (count($tplArr))
			foreach($tplArr as $key =>$value){
				$value = str_replace('#S#', "/", $value);
				$value = str_replace('#BS#', "\\", $value);
				$tplStr .= "&nbsp;->&nbsp;<a href='oreon.php?p=60206&o=c&service_id=".$key."'>".$value."</a>";
			}
			$service["service_description"] = str_replace("#BR#", "\n", $service["service_description"]);
			$service["service_description"] = str_replace("#T#", "\t", $service["service_description"]);
			$service["service_description"] = str_replace("#R#", "\r", $service["service_description"]);
			$service["service_description"] = str_replace("#S#", '/', $service["service_description"]);
			$service["service_description"] = str_replace("#BS#", '\\', $service["service_description"]);			

			$service["service_alias"] = str_replace("#BR#", "\n", $service["service_alias"]);
			$service["service_alias"] = str_replace("#T#", "\t", $service["service_alias"]);
			$service["service_alias"] = str_replace("#R#", "\r", $service["service_alias"]);
			$service["service_alias"] = str_replace("#S#", '/', $service["service_alias"]);
			$service["service_alias"] = str_replace("#BS#", '\\', $service["service_alias"]);			

			$elemArr[$i] = array("MenuClass"=>"list_".$style, 
						"RowMenu_select"=>$selectedElements->toHtml(),
						"RowMenu_desc"=>$service["service_description"],
						"RowMenu_alias"=>$service["service_alias"],
						"RowMenu_parent"=>$tplStr,
						"RowMenu_link"=>"?p=".$p."&o=c&service_id=".$service['service_id'],
						"RowMenu_status"=>$service["service_activate"] ? $lang['enable'] : $lang['disable'],
						"RowMenu_options"=>$moptions);
		$style != "two" ? $style = "two" : $style = "one";
	}
	
	/*
	 * Header title for same name - Ajust pattern lenght with (0, 4) param
	 */
	$pattern = NULL;
	for ($i = 0; $i < count($elemArr); $i++)	{
		
		/*
		 * Searching for a pattern wich n+1 elem
		 */
		if (isset($elemArr[$i+1]["RowMenu_desc"]) && strstr($elemArr[$i+1]["RowMenu_desc"], substr($elemArr[$i]["RowMenu_desc"], 0, 4)) && !$pattern)	{
			for ($j = 0; isset($elemArr[$i]["RowMenu_desc"][$j]); $j++)
				if (isset($elemArr[$i+1]["RowMenu_desc"][$j]) && $elemArr[$i+1]["RowMenu_desc"][$j] == $elemArr[$i]["RowMenu_desc"][$j])
					;
				else
					break;
			$pattern = substr($elemArr[$i]["RowMenu_desc"], 0, $j);
		}
		if (strstr($elemArr[$i]["RowMenu_desc"], $pattern))
			$elemArr[$i]["pattern"] = $pattern;
		else	{
			$elemArr[$i]["pattern"] = NULL;
			$pattern = NULL;			
			if (isset($elemArr[$i+1]["RowMenu_desc"]) && strstr($elemArr[$i+1]["RowMenu_desc"], substr($elemArr[$i]["RowMenu_desc"], 0, 4)) && !$pattern)	{
				for ($j = 0; isset($elemArr[$i]["RowMenu_desc"][$j]); $j++)	{
					if (isset($elemArr[$i+1]["RowMenu_desc"][$j]) && $elemArr[$i+1]["RowMenu_desc"][$j] == $elemArr[$i]["RowMenu_desc"][$j])
						;
					else
						break;
				}
				$pattern = substr($elemArr[$i]["RowMenu_desc"], 0, $j);
				$elemArr[$i]["pattern"] = $pattern;
			}
		}
	}
	$tpl->assign("elemArr", $elemArr);
	
	/*
	 * Different messages we put in the template
	 */
	$tpl->assign('msg', array ("addL"=>"?p=".$p."&o=a", "addT"=>$lang['add'], "delConfirm"=>$lang['confirm_removing']));
	
	/*
	 * Toolbar select $lang["lgd_more_actions"]
	 */
	?>
	<SCRIPT LANGUAGE="JavaScript">
	function setO(_i) {
		document.forms['form'].elements['o'].value = _i;
	}
	</SCRIPT>
	<?php
	$attrs1 = array(
		'onchange'=>"javascript: " .
				"if (this.form.elements['o1'].selectedIndex == 1 && confirm('".$lang['confirm_duplication']."')) {" .
				" 	setO(this.form.elements['o1'].value); submit();} " .
				"else if (this.form.elements['o1'].selectedIndex == 2 && confirm('".$lang['confirm_removing']."')) {" .
				" 	setO(this.form.elements['o1'].value); submit();} " .
				"else if (this.form.elements['o1'].selectedIndex == 3 || this.form.elements['o1'].selectedIndex == 4 ||this.form.elements['o1'].selectedIndex == 5){" .
				" 	setO(this.form.elements['o1'].value); submit();} " .
				"this.form.elements['o1'].selectedIndex = 0");
	$form->addElement('select', 'o1', NULL, array(NULL=>$lang["lgd_more_actions"], "m"=>$lang['dup'], "d"=>$lang['delete'], "mc"=>$lang['mchange'], "ms"=>$lang['m_mon_enable'], "mu"=>$lang['m_mon_disable']), $attrs1);
	$form->setDefaults(array('o1' => NULL));
		
	$attrs2 = array(
		'onchange'=>"javascript: " .
				"if (this.form.elements['o2'].selectedIndex == 1 && confirm('".$lang['confirm_duplication']."')) {" .
				" 	setO(this.form.elements['o2'].value); submit();} " .
				"else if (this.form.elements['o2'].selectedIndex == 2 && confirm('".$lang['confirm_removing']."')) {" .
				" 	setO(this.form.elements['o2'].value); submit();} " .
				"else if (this.form.elements['o2'].selectedIndex == 3 || this.form.elements['o2'].selectedIndex == 4 ||this.form.elements['o2'].selectedIndex == 5){" .
				" 	setO(this.form.elements['o2'].value); submit();} " .
				"this.form.elements['o1'].selectedIndex = 0");
    $form->addElement('select', 'o2', NULL, array(NULL=>$lang["lgd_more_actions"], "m"=>$lang['dup'], "d"=>$lang['delete'], "mc"=>$lang['mchange'], "ms"=>$lang['m_mon_enable'], "mu"=>$lang['m_mon_disable']), $attrs2);
	$form->setDefaults(array('o2' => NULL));

	$o1 =& $form->getElement('o1');
	$o1->setValue(NULL);
	$o1->setSelected(NULL);

	$o2 =& $form->getElement('o2');
	$o2->setValue(NULL);
	$o2->setSelected(NULL);
	
	$tpl->assign('limit', $limit);

	/*
	 * Apply a template definition
	 */	
	$renderer =& new HTML_QuickForm_Renderer_ArraySmarty($tpl);
	$form->accept($renderer);	
	$tpl->assign('form', $renderer->toArray());
	$tpl->display("listServiceTemplateModel.ihtml");
?>