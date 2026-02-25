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

if (isset($_POST['searchGT'])) {
    $search = $_POST['searchGT'];
    $centreon->historySearch[$url] = $search;
} elseif (isset($_GET['searchGT'])) {
    $search = $_GET['searchGT'];
    $centreon->historySearch[$url] = $search;
} elseif (isset($centreon->historySearch[$url])) {
    $search = $centreon->historySearch[$url];
}

if ($search) {
    $SearchTool = ' WHERE name LIKE :search';
    $queryValues['search'] = '%' . $search . '%';
}

$rq = 'SELECT SQL_CALC_FOUND_ROWS graph_id, name, default_tpl1, vertical_label, base, split_component FROM '
    . 'giv_graphs_template gg ' . $SearchTool . ' ORDER BY name LIMIT ' . $num * $limit . ', ' . $limit;
$stmt = $pearDB->prepare($rq);
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
$tpl->assign('headerMenu_desc', _('Description'));
$tpl->assign('headerMenu_split_component', _('Split Components'));
$tpl->assign('headerMenu_base', _('Base'));
$tpl->assign('headerMenu_options', _('Options'));

// List
$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);
// Different style between each lines
$style = 'one';

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Fill a tab with a mutlidimensionnal Array we put in $tpl
$elemArr = [];
for ($i = 0; $graph = $stmt->fetch(); $i++) {
    $selectedElements = $form->addElement('checkbox', 'select[' . $graph['graph_id'] . ']');
    $moptions = '<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
        . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;'
        . "\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr["
        . $graph['graph_id'] . "]' />";
    $elemArr[$i] = ['MenuClass' => 'list_' . $style, 'RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_name' => $graph['name'], 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&graph_id=' . $graph['graph_id'], 'RowMenu_desc' => $graph['vertical_label'], 'RowMenu_base' => $graph['base'], 'RowMenu_split_component' => $graph['split_component'] ? _('Yes') : _('No'), 'RowMenu_options' => $moptions];
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
    </SCRIPT>
<?php
$attrs1 = ['onchange' => 'javascript: '
    . "if (this.form.elements['o1'].selectedIndex == 1 && confirm('"
    . _('Do you confirm the duplication ?') . "')) {"
    . " 	setO(this.form.elements['o1'].value); submit();} "
    . "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('"
    . _('Do you confirm the deletion ?') . "')) {"
    . " 	setO(this.form.elements['o1'].value); submit();} "
    . "else if (this.form.elements['o1'].selectedIndex == 3) {"
    . " 	setO(this.form.elements['o1'].value); submit();} "
    . ''];
$form->addElement(
    'select',
    'o1',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs1
);
$form->setDefaults(['o1' => null]);
$o1 = $form->getElement('o1');
$o1->setValue(null);

$attrs = ['onchange' => 'javascript: '
    . "if (this.form.elements['o2'].selectedIndex == 1 && confirm('"
    . _('Do you confirm the duplication ?') . "')) {"
    . " 	setO(this.form.elements['o2'].value); submit();} "
    . "else if (this.form.elements['o2'].selectedIndex == 2 && confirm('"
    . _('Do you confirm the deletion ?') . "')) {"
    . " 	setO(this.form.elements['o2'].value); submit();} "
    . "else if (this.form.elements['o2'].selectedIndex == 3) {"
    . " 	setO(this.form.elements['o2'].value); submit();} "
    . ''];
$form->addElement(
    'select',
    'o2',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs
);
$form->setDefaults(['o2' => null]);

$o2 = $form->getElement('o2');
$o2->setValue(null);

$tpl->assign('limit', $limit);
$tpl->assign('searchGT', htmlentities($search));

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listGraphTemplates.ihtml');
