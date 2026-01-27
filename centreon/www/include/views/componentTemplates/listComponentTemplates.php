<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

if (! isset($centreon)) {
    exit;
}

include './include/common/autoNumLimit.php';

$SearchTool = null;
$queryValues = [];
$search = null;
if (isset($_POST['searchCurve'])) {
    $search = $_POST['searchCurve'];
    $centreon->historySearch[$url] = $search;
} elseif (isset($_GET['search'])) {
    $search = $_GET['search'];
    $centreon->historySearch[$url] = $search;
} elseif (isset($centreon->historySearch[$url])) {
    $search = $centreon->historySearch[$url];
}

if ($search != null) {
    $SearchTool = ' WHERE name LIKE :search';
    $queryValues['search'] = '%' . $search . '%';
    $ClWh1 = 'AND host_id IS NULL';
    $ClWh2 = 'AND gct.host_id = h.host_id';
} else {
    $ClWh1 = 'WHERE host_id IS NULL';
    $ClWh2 = 'WHERE gct.host_id = h.host_id';
}
$query = '( SELECT SQL_CALC_FOUND_ROWS compo_id, NULL as host_name, host_id, service_id, name, ds_stack, ds_order, '
    . 'ds_name, ds_color_line, ds_color_area, ds_filled, ds_legend, default_tpl1, ds_tickness, ds_transparency '
    . "FROM giv_components_template {$SearchTool} {$ClWh1} ) UNION ( SELECT compo_id, host_name, gct.host_id, "
    . 'gct.service_id, name, ds_stack, ds_order, ds_name, ds_color_line, ds_color_area, ds_filled, ds_legend, '
    . "default_tpl1, ds_tickness, ds_transparency FROM giv_components_template AS gct, host AS h {$SearchTool} {$ClWh2} ) "
    . 'ORDER BY host_name, name LIMIT ' . $num * $limit . ', ' . $limit;
$stmt = $pearDB->prepare($query);

foreach ($queryValues as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->execute();
$rows = $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

include './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// start header menu
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Data Source Name'));
$tpl->assign('headerMenu_legend', _('Legend'));
$tpl->assign('headerMenu_stack', _('Stacked'));
$tpl->assign('headerMenu_order', _('Order'));
$tpl->assign('headerMenu_Transp', _('Transparency'));
$tpl->assign('headerMenu_tickness', _('Thickness'));
$tpl->assign('headerMenu_fill', _('Filling'));
$tpl->assign('headerMenu_options', _('Options'));

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Fill a tab with a multidimensionnal Array we put in $tpl
$yesOrNo = [null => _('No'), 0 => _('No'), 1 => _('Yes')];
$elemArr = [];
for ($i = 0; $compo = $stmt->fetch(); $i++) {
    $selectedElements = $form->addElement('checkbox', 'select[' . $compo['compo_id'] . ']');
    $moptions = '&nbsp;<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
        . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) '
        . "return false;\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr["
        . $compo['compo_id'] . "]' />";

    $query = "SELECT h.host_name FROM giv_components_template AS gct, host AS h WHERE gct.host_id = '"
        . $compo['host_id'] . "' AND gct.host_id = h.host_id";
    $titles = $pearDB->query($query);
    $title = $titles->rowCount() ? $titles->fetchRow() : ['host_name' => 'Global'];
    $titles->closeCursor();
    $elemArr[$i] = ['MenuClass' => 'list_' . $style, 'title' => $title['host_name'], 'RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_name' => $compo['name'], 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&compo_id=' . $compo['compo_id'], 'RowMenu_desc' => $compo['ds_name'], 'RowMenu_legend' => $compo['ds_legend'], 'RowMenu_stack' => $yesOrNo[$compo['ds_stack']], 'RowMenu_order' => $compo['ds_order'], 'RowMenu_transp' => $compo['ds_transparency'], 'RowMenu_clrLine' => $compo['ds_color_line'], 'RowMenu_clrArea' => $compo['ds_color_area'], 'RowMenu_fill' => $yesOrNo[$compo['ds_filled']], 'RowMenu_tickness' => $compo['ds_tickness'], 'RowMenu_options' => $moptions];
    $style = $style != 'two' ? 'two' : 'one';
}
$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion ?')]
);

// Toolbar select
?>
    <script type="text/javascript">
        function setO(_i) {
            document.forms['form'].elements['o'].value = _i;
        }
    </script>
<?php
$attrs1 = ['onchange' => 'javascript: '
    . "if (this.form.elements['o1'].selectedIndex === 1 && confirm('"
    . _('Do you confirm the duplication ?') . "')) {"
    . " 	setO(this.form.elements['o1'].value); submit();} "
    . "else if (this.form.elements['o1'].selectedIndex === 2 && confirm('"
    . _('Do you confirm the deletion ?') . "')) {"
    . " 	setO(this.form.elements['o1'].value); submit();} "
    . "this.form.elements['o1'].selectedIndex = 0;"
    . ''];
$form->addElement(
    'select',
    'o1',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs1
);
$o1 = $form->getElement('o1');
$o1->setValue(null);

$attrs = ['onchange' => 'javascript: '
    . "if (this.form.elements['o2'].selectedIndex === 1 && confirm('"
    . _('Do you confirm the duplication ?') . "')) {"
    . " 	setO(this.form.elements['o2'].value); submit();} "
    . "else if (this.form.elements['o2'].selectedIndex === 2 && confirm('"
    . _('Do you confirm the deletion ?') . "')) {"
    . " 	setO(this.form.elements['o2'].value); submit();} "
    . "this.form.elements['o2'].selectedIndex = 0;"
    . ''];
$form->addElement(
    'select',
    'o2',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs
);
$o2 = $form->getElement('o2');
$o2->setValue(null);

$tpl->assign('limit', $limit);
$tpl->assign('searchCurve', htmlentities($search));

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listComponentTemplates.ihtml');
