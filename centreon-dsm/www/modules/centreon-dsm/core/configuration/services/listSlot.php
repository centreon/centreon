<?php

/*
 * Copyright 2005-2021 Centreon
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

if (!isset($oreon)) {
    exit();
}

require "./include/common/autoNumLimit.php";

/*
 * create TP cache
 */
$tpCache = array();
$dbResult = $pearDB->query("SELECT tp_name, tp_id FROM timeperiod");
while ($data = $dbResult->fetch()) {
    $tpCache[$data["tp_id"]] = $data["tp_name"];
}
$dbResult->closeCursor();

if (isset($search)) {
    $dbResult = $pearDB->query(
        "SELECT COUNT(*) FROM mod_dsm_pool WHERE (pool_name LIKE '%" .
        htmlentities($search, ENT_QUOTES) . "%' OR pool_description LIKE '%" .
        htmlentities($search, ENT_QUOTES) . "%')"
    );
} else {
    $dbResult = $pearDB->query("SELECT COUNT(*) FROM mod_dsm_pool");
}

$tmp = $dbResult->fetch();
$rows = $tmp["COUNT(*)"];

require "./include/common/checkPagination.php";

/*
 * Smarty template Init
 */
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

/*
 * start header menu
 */
$tpl->assign("headerMenu_icone", "<img src='./img/icones/16x16/pin_red.gif'>");
$tpl->assign("headerMenu_desc", _("Description"));
$tpl->assign("headerMenu_name", _("Slot Name"));
$tpl->assign("headerMenu_email", _("Email"));
$tpl->assign("headerMenu_prefix", _("Prefix"));
$tpl->assign("headerMenu_number", _("Number"));
$tpl->assign("headerMenu_status", _("Status"));
$tpl->assign("headerMenu_options", _("Options"));
$tpl->assign('searchLabel', _('Search'));
$tpl->assign('search', $search);
$tpl->assign('p', $p);

if ($search) {
    $rq = "SELECT
            pool_id,
            pool_prefix,
            pool_name,
            pool_description,
            pool_number,
            pool_activate
        FROM mod_dsm_pool
        WHERE (
            pool_name LIKE '%" . htmlentities($search, ENT_QUOTES) . "%'
        OR pool_description LIKE '%" . htmlentities($search, ENT_QUOTES) . "%')
        ORDER BY pool_name LIMIT " . $num * $limit . ", " . $limit;
} else {
    $rq = "SELECT
            pool_id,
            pool_prefix,
            pool_name,
            pool_description,
            pool_number,
            pool_activate
        FROM mod_dsm_pool
        ORDER BY pool_name
        LIMIT " . $num * $limit . ", " . $limit;
}
$dbResult = $pearDB->query($rq);

$search = tidySearchKey($search, $advanced_search);

$form = new HTML_QuickFormCustom('select_form', 'POST', "?p=" . $p);

/*
 * Different style between each lines
 */
$style = "one";

/*
 * Fill a tab with a mutlidimensionnal Array we put in $tpl
 */
$elemArr = array();
for ($i = 0; $contact = $dbResult->fetch(); $i++) {
    $selectedElements = $form->addElement('checkbox', "select[" . $contact['pool_id'] . "]");
    if ($contact["pool_activate"]) {
        $moptions = "<a href='main.php?p=" . $p . "&pool_id=" . $contact['pool_id'] . "&o=u&limit=" . $limit .
            "&num=" . $num . "&search=" . $search . "'><img src='img/icones/16x16/element_previous.gif' " .
            "border='0' alt='" . _("Disabled") . "'></a>&nbsp;&nbsp;";
    } else {
        $moptions = "<a href='main.php?p=" . $p . "&pool_id=" . $contact['pool_id'] . "&o=s&limit=" . $limit .
            "&num=" . $num . "&search=" . $search . "'><img src='img/icones/16x16/element_next.gif' " .
            "border='0' alt='" . _("Enabled") . "'></a>&nbsp;&nbsp;";
    }
    $moptions .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
    $moptions .= "<input onKeypress=\"if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) " .
        "event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;\" " .
        "maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr[" .
        $contact['pool_id'] . "]' />";
    $elemArr[$i] = array(
        "MenuClass" => "list_" . $style,
        "RowMenu_select" => $selectedElements->toHtml(),
        "RowMenu_name" => html_entity_decode($contact["pool_name"]),
        "RowMenu_link" => "?p=" . $p . "&o=c&pool_id=" . $contact['pool_id'],
        "RowMenu_desc" => html_entity_decode($contact["pool_description"]),
        "RowMenu_number" => html_entity_decode($contact["pool_number"], ENT_QUOTES),
        "RowMenu_prefix" => html_entity_decode($contact["pool_prefix"], ENT_QUOTES),
        "RowMenu_status" => $contact["pool_activate"] ? _("Enabled") : _("Disabled"),
        "RowMenu_options" => $moptions
    );
    $style != "two" ? $style = "two" : $style = "one";
}
$tpl->assign("elemArr", $elemArr);

/*
 * Different messages we put in the template
 */
$tpl->assign(
    'msg',
    array(
        "addL" => "?p=" . $p . "&o=a",
        "addT" => _("Add")
    )
);

/*
 * Toolbar select
 */
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</SCRIPT>
<?php
$attrs1 = array(
    'onchange' => "javascript: " .
        "if (this.form.elements['o1'].selectedIndex == 1 && confirm('" .
        _("Do you confirm the duplication ?") . "')) {" .
        "    setO(this.form.elements['o1'].value); submit();} " .
        "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('" .
        _("Do you confirm the deletion ?") . "')) {" .
        "    setO(this.form.elements['o1'].value); submit();} " .
        "else if (this.form.elements['o1'].selectedIndex == 3 || this.form.elements['o1'].selectedIndex == 4 || " .
        "this.form.elements['o1'].selectedIndex == 5){" .
        "    setO(this.form.elements['o1'].value); submit();} " .
        "this.form.elements['o1'].selectedIndex = 0"
);
$form->addElement(
    'select',
    'o1',
    null,
    array(null => _("More actions..."), "m" => _("Duplicate"), "d" => _("Delete")),
    $attrs1
);
$form->setDefaults(array('o1' => null));

$attrs2 = array(
    'onchange' => "javascript: " .
        "if (this.form.elements['o2'].selectedIndex == 1 && confirm('" .
        _("Do you confirm the duplication ?") . "')) {" .
        "    setO(this.form.elements['o2'].value); submit();} " .
        "else if (this.form.elements['o2'].selectedIndex == 2 && confirm('" .
        _("Do you confirm the deletion ?") . "')) {" .
        "    setO(this.form.elements['o2'].value); submit();} " .
        "else if (this.form.elements['o2'].selectedIndex == 3 || this.form.elements['o2'].selectedIndex == 4 || " .
        "this.form.elements['o2'].selectedIndex == 5){" .
        "    setO(this.form.elements['o2'].value); submit();} " .
        "this.form.elements['o1'].selectedIndex = 0"
);
$form->addElement(
    'select',
    'o2',
    null,
    array(null => _("More actions..."), "m" => _("Duplicate"), "d" => _("Delete")),
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

/*
 * Fill a tab with a mutlidimensionnal Array we put in $tpl
 */
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display("listSlot.ihtml");
?>
