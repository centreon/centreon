<?php

/*
 * Copyright 2005-2019 Centreon
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

$search = \HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['searchSG'] ?? $_GET['searchSG'] ?? null
);

if (isset($_POST['searchSG']) || isset($_GET['searchSG'])) {
    //saving filters values
    $centreon->historySearch[$url] = [];
    $centreon->historySearch[$url]["search"] = $search;
} else {
    //restoring saved values
    $search = $centreon->historySearch[$url]["search"] ?? null;
}
$conditionStr = "";
$sgStrParams = [];
if (!$acl->admin && $sgString) {
    $sgStrList = explode(',', $sgString);
    foreach ($sgStrList as $index => $sgId) {
        $sgStrParams[':sg_' . $index] = (int) str_replace("'", "", $sgId);
    }
    $queryParams = implode(',', array_keys($sgStrParams));

    $conditionStr = $search !== '' ? "AND sg_id IN (" . $queryParams . ")" : "WHERE sg_id IN (" . $queryParams . ")";
}

if ($search !== '') {
    $statement = $pearDB->prepare("SELECT SQL_CALC_FOUND_ROWS sg_id, sg_name, sg_alias, sg_activate" .
        " FROM servicegroup WHERE (sg_name LIKE :search  OR sg_alias LIKE :search) " . $conditionStr .
        " ORDER BY sg_name LIMIT :offset, :limit");

    $statement->bindValue(':search', '%' . $search . '%', \PDO::PARAM_STR);
} else {
    $statement = $pearDB->prepare("SELECT SQL_CALC_FOUND_ROWS sg_id, sg_name, sg_alias, sg_activate" .
        " FROM servicegroup " . $conditionStr . " ORDER BY sg_name LIMIT :offset, :limit");
}
foreach ($sgStrParams as $key => $sgId) {
    $statement->bindValue($key, $sgId, \PDO::PARAM_INT);
}
$statement->bindValue(':offset', (int) $num * (int) $limit, \PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
$statement->execute();

$rows = $pearDB->query("SELECT FOUND_ROWS()")->fetchColumn();

include "./include/common/checkPagination.php";

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$tpl->assign("headerMenu_name", _("Name"));
$tpl->assign("headerMenu_desc", _("Description"));
$tpl->assign("headerMenu_status", _("Status"));
$tpl->assign("headerMenu_options", _("Options"));

$search = tidySearchKey($search, $advanced_search);

$form = new HTML_QuickFormCustom('select_form', 'POST', "?p=" . $p);
// Different style between each lines
$style = "one";

$attrBtnSuccess = ["class" => "btc bt_success", "onClick" => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _("Search"), $attrBtnSuccess);

// Fill a tab with a multidimensional Array we put in $tpl
$elemArr = [];
$centreonToken = createCSRFToken();

for ($i = 0; $sg = $statement->fetch(\PDO::FETCH_ASSOC); $i++) {
    $selectedElements = $form->addElement('checkbox', "select[" . $sg['sg_id'] . "]");
    $moptions = "";
    if ($sg["sg_activate"]) {
        $moptions .= "<a href='main.php?p=" . $p . "&sg_id=" . $sg['sg_id'] . "&o=u&limit=" . $limit .
            "&num=" . $num . "&search=" . $search . "&centreon_token=" . $centreonToken .
            "'><img src='img/icons/disabled.png' class='ico-14 margin_right' " .
            "border='0' alt='" . _("Disabled") . "'></a>";
    } else {
        $moptions .= "<a href='main.php?p=" . $p . "&sg_id=" . $sg['sg_id'] . "&o=s&limit=" . $limit .
            "&num=" . $num . "&search=" . $search . "&centreon_token=" . $centreonToken .
            "'><img src='img/icons/enabled.png' class='ico-14 margin_right' " .
            "border='0' alt='" . _("Enabled") . "'></a>";
    }
    $moptions .= "&nbsp;<input onKeypress=\"if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) " .
        "event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;\" " .
        "maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr[" . $sg['sg_id'] . "]' />";
    $elemArr[$i] = ["MenuClass" => "list_" . $style, "RowMenu_select" => $selectedElements->toHtml(), "RowMenu_name" => CentreonUtils::escapeSecure($sg["sg_name"]), "RowMenu_link" => "main.php?p=" . $p . "&o=c&sg_id=" . $sg['sg_id'], "RowMenu_desc" => CentreonUtils::escapeSecure($sg["sg_alias"]), "RowMenu_status" => $sg["sg_activate"] ? _("Enabled") : _("Disabled"), "RowMenu_badge" => $sg["sg_activate"] ? "service_ok" : "service_critical", "RowMenu_options" => $moptions];

    $style = $style != "two" ? "two" : "one";
}
$tpl->assign("elemArr", $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    ["addL" => "main.php?p=" . $p . "&o=a", "addT" => _("Add"), "delConfirm" => _("Do you confirm the deletion ?")]
);

// Toolbar select
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php
$attrs1 = ['onchange' => "javascript: " .
    " var bChecked = isChecked(); " .
    " if (this.form.elements['o1'].selectedIndex != 0 && !bChecked) {" .
    " alert('" . _("Please select one or more items") . "'); return false;} " .
    "if (this.form.elements['o1'].selectedIndex == 1 && confirm('" .
    _("Do you confirm the duplication ?") . "')) {" .
    " 	setO(this.form.elements['o1'].value); submit();} " .
    "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('" .
    _("Do you confirm the deletion ?") . "')) {" .
    " 	setO(this.form.elements['o1'].value); submit();} " .
    "else if (this.form.elements['o1'].selectedIndex == 3) {" .
    " 	setO(this.form.elements['o1'].value); submit();} " .
    ""];
$form->addElement(
    'select',
    'o1',
    null,
    [null => _("More actions..."), "m" => _("Duplicate"), "d" => _("Delete")],
    $attrs1
);
$o1 = $form->getElement('o1');
$o1->setValue(null);

$attrs = ['onchange' => "javascript: " .
    " var bChecked = isChecked(); " .
    " if (this.form.elements['o2'].selectedIndex != 0 && !bChecked) {" .
    " alert('" . _("Please select one or more items") . "'); return false;} " .
    "if (this.form.elements['o2'].selectedIndex == 1 && confirm('" .
    _("Do you confirm the duplication ?") . "')) {" .
    " 	setO(this.form.elements['o2'].value); submit();} " .
    "else if (this.form.elements['o2'].selectedIndex == 2 && confirm('" .
    _("Do you confirm the deletion ?") . "')) {" .
    " 	setO(this.form.elements['o2'].value); submit();} " .
    "else if (this.form.elements['o2'].selectedIndex == 3) {" .
    " 	setO(this.form.elements['o2'].value); submit();} " .
    ""];
$form->addElement(
    'select',
    'o2',
    null,
    [null => _("More actions..."), "m" => _("Duplicate"), "d" => _("Delete")],
    $attrs
);
$o2 = $form->getElement('o2');
$o2->setValue(null);

$tpl->assign('limit', $limit);
$tpl->assign('searchSG', $search);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display("listServiceGroup.ihtml");
