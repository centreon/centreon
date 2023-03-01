<?php

/*
 * Copyright 2005-2022 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($centreon)) {
    exit();
}

include_once "./class/centreonUtils.class.php";
include "./include/common/autoNumLimit.php";


$list = $_GET["list"] ?? null;

$search = \HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['searchE'] ?? $_GET['searchE'] ?? null
);

if (isset($_POST['searchE']) || isset($_GET['searchE'])) {
    //saving filters values
    $centreon->historySearch[$url] = array();
    $centreon->historySearch[$url]['search'] = $search;
} else {
    //restoring saved values
    $search = $centreon->historySearch[$url]['search'] ?? null;
}

$aclFrom = "";
$aclCond = array(
    'h' => '',
    'sv' => '',
    'hg' => '',
    'sg' => '',
    'ms' => ''
);
if (!$centreon->user->admin) {
    $aclFrom = ", `$dbmon`.centreon_acl acl ";
    $aclCond['h'] = " AND ehr.host_host_id = acl.host_id
                      AND acl.group_id IN (" . $acl->getAccessGroupsString() . ") ";
    $aclCond['sv'] = " AND esr.host_host_id = acl.host_id
                       AND esr.service_service_id = acl.service_id
                       AND acl.group_id IN (" . $acl->getAccessGroupsString() . ") ";
    $aclCond['hg'] = $acl->queryBuilder('AND', 'hostgroup_hg_id', $hgString);
    $aclCond['sg'] = $acl->queryBuilder('AND', 'servicegroup_sg_id', $sgString);
    $aclCond['ms'] = $acl->queryBuilder('AND', 'meta_service_meta_id', $acl->getMetaServiceString());
}


$rq = "SELECT SQL_CALC_FOUND_ROWS esc_id, esc_name, esc_alias FROM escalation esc";
if ($list && $list == "h") {
    $rq .= " WHERE (SELECT DISTINCT COUNT(host_host_id) " .
        " FROM escalation_host_relation ehr " . $aclFrom .
        " WHERE ehr.escalation_esc_id = esc.esc_id " . $aclCond['h'] . ") > 0 ";
} elseif ($list && $list == "sv") {
    $rq .= " WHERE (SELECT DISTINCT COUNT(*) " .
        " FROM escalation_service_relation esr " . $aclFrom .
        "WHERE esr.escalation_esc_id = esc.esc_id " . $aclCond['sv'] . ") > 0 ";
} elseif ($list && $list == "hg") {
    $rq .= " WHERE (SELECT DISTINCT COUNT(*) " .
        "FROM escalation_hostgroup_relation ehgr " .
        "WHERE ehgr.escalation_esc_id = esc.esc_id " . $aclCond['hg'] . ") > 0 ";
} elseif ($list && $list == "sg") {
    $rq .= " WHERE (SELECT DISTINCT COUNT(*) " .
        " FROM escalation_servicegroup_relation esgr " .
        " WHERE esgr.escalation_esc_id = esc.esc_id " . $aclCond['sg'] . ") > 0 ";
} elseif ($list && $list == "ms") {
    $rq .= " WHERE (SELECT DISTINCT COUNT(*) " .
        " FROM escalation_meta_service_relation emsr " .
        " WHERE emsr.escalation_esc_id = esc.esc_id " . $aclCond['ms'] . ") > 0 ";
}

//Check if $search was init
if ($search && $list) {
    $rq .= " AND (esc.esc_name LIKE '%" . $search . "%' OR esc.esc_alias LIKE '%" . $search . "%')";
} elseif ($search) {
    $rq .= " WHERE (esc.esc_name LIKE '%" . $search . "%' OR esc.esc_alias LIKE '%" . $search . "%')";
}

// Set Order and limits
$rq .= " ORDER BY esc_name LIMIT " . $num * $limit . ", " . $limit;

$dbResult = $pearDB->query($rq);
$rows = $pearDB->query("SELECT FOUND_ROWS()")->fetchColumn();

include "./include/common/checkPagination.php";

//  Smarty template Init
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// start header menu

$tpl->assign("headerMenu_name", _("Name"));
$tpl->assign("headerMenu_alias", _("Alias"));
$tpl->assign("headerMenu_options", _("Options"));

/*
 * Escalation list
 */
$search = tidySearchKey($search, $advanced_search);

$form = new HTML_QuickFormCustom('select_form', 'POST', "?p=" . $p);

// Different style between each lines
$style = "one";

$attrBtnSuccess = array(
    "class" => "btc bt_success",
    "onClick" => "window.history.replaceState('', '', '?p=" . $p . "');"
);
$form->addElement('submit', 'Search', _("Search"), $attrBtnSuccess);

// Fill a tab with a multidimensional Array we put in $tpl
$elemArr = array();
for ($i = 0; $esc = $dbResult->fetch(); $i++) {
    $esc = array_map("myEncode", $esc);
    $moptions = "";
    $selectedElements = $form->addElement('checkbox', "select[" . $esc['esc_id'] . "]");
    $moptions .=
        "&nbsp;<input onKeypress=\"if(event.keyCode > 31 && " .
        "(event.keyCode < 45 || event.keyCode > 57)) event.returnValue = false; " .
        "if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;" .
        "\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" " .
        "name='dupNbr[" . $esc['esc_id'] . "]'></input>";
    $elemArr[$i] = array(
        "MenuClass" => "list_" . $style,
        "RowMenu_select" => $selectedElements->toHtml(),
        "RowMenu_name" => CentreonUtils::escapeSecure($esc["esc_name"]),
        "RowMenu_alias" => CentreonUtils::escapeSecure($esc["esc_alias"]),
        "RowMenu_link" => "main.php?p=" . $p . "&o=c&esc_id=" . $esc['esc_id'],
        "RowMenu_options" => $moptions
    );
    $style != "two" ? $style = "two" : $style = "one";
}
$tpl->assign("elemArr", $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    array(
        "addL" => "main.php?p=" . $p . "&o=a",
        "addT" => _("Add"),
        "delConfirm" => _("Do you confirm the deletion ?")
    )
);

// Toolbar select more_actions
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php
$attrs1 = array(
    'onchange' => "javascript: " .
        " var bChecked = isChecked(); " .
        " if (this.form.elements['o1'].selectedIndex != 0 && !bChecked) {" .
        " alert('" . _("Please select one or more items") . "'); return false;} " .
        "if (this.form.elements['o1'].selectedIndex == 1 && confirm('" .
        _("Do you confirm the duplication ?") . "')) {" .
        "  setO(this.form.elements['o1'].value); submit();} " .
        "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('" .
        _("Do you confirm the deletion ?") . "')) {" .
        "  setO(this.form.elements['o1'].value); submit();} " .
        "else if (this.form.elements['o1'].selectedIndex == 3) {" .
        "  setO(this.form.elements['o1'].value); submit();} " .
        ""
);
$form->addElement(
    'select',
    'o1',
    null,
    array(
        null => _("More actions..."),
        "m" => _("Duplicate"),
        "d" => _("Delete")
    ),
    $attrs1
);
$form->setDefaults(array('o1' => null));

$attrs2 = array(
    'onchange' => "javascript: " .
        " var bChecked = isChecked(); " .
        " if (this.form.elements['o2'].selectedIndex != 0 && !bChecked) {" .
        " alert('" . _("Please select one or more items") . "'); return false;} " .
        "if (this.form.elements['o2'].selectedIndex == 1 && confirm('" .
        _("Do you confirm the duplication ?") . "')) {" .
        "  setO(this.form.elements['o2'].value); submit();} " .
        "else if (this.form.elements['o2'].selectedIndex == 2 && confirm('" .
        _("Do you confirm the deletion ?") . "')) {" .
        "  setO(this.form.elements['o2'].value); submit();} " .
        "else if (this.form.elements['o2'].selectedIndex == 3) {" .
        "  setO(this.form.elements['o2'].value); submit();} " .
        ""
);
$form->addElement(
    'select',
    'o2',
    null,
    array(
        null => _("More actions..."),
        "m" => _("Duplicate"),
        "d" => _("Delete")
    ),
    $attrs2
);
$form->setDefaults(array('o2' => null));

$o1 = $form->getElement('o1');
$o1->setValue(null);
$o1->setSelected(null);

$o2 = $form->getElement('o2');
$o2->setValue(null);
$o2->setSelected(null);

$tpl->assign('limit', $limit);
$tpl->assign('searchE', $search);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display("listEscalation.ihtml");
