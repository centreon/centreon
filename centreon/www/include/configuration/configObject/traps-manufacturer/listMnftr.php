<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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
    exit();
}

include_once './class/centreonUtils.class.php';

include './include/common/autoNumLimit.php';
$mnftr_id = null;

$search = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['searchTM'] ?? $_GET['searchTM'] ?? null
);

if (isset($_POST['searchTM']) || isset($_GET['searchTM'])) {
    // saving filters values
    $centreon->historySearch[$url] = [];
    $centreon->historySearch[$url]['search'] = $search;
} else {
    // restoring saved values
    $search = $centreon->historySearch[$url]['search'] ?? null;
}

$searchTool = '';
if ($search) {
    $searchTool .= ' WHERE (alias LIKE :search) OR (name LIKE :search) ';
}

// List of elements - Depends on different criteria
$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS * FROM traps_vendor ' . $searchTool
    . 'ORDER BY name, alias LIMIT ' . $num * $limit . ', ' . $limit
);

if (! empty($searchTool)) {
    $statement->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
}
$statement->execute();

$rows = $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();
include './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate(__DIR__);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// start header menu
$tpl->assign('headerMenu_name', _('Vendor Name'));
$tpl->assign('headerMenu_alias', _('Alias'));
$tpl->assign('headerMenu_options', _('Options'));

$form = new HTML_QuickFormCustom('form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Fill a tab with a multidimensional Array we put in $tpl
$elemArr = [];
for ($i = 0; $mnftr = $statement->fetch(); $i++) {
    $moptions = '';
    $selectedElements = $form->addElement('checkbox', 'select[' . $mnftr['id'] . ']');
    $moptions = '&nbsp;<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
        . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;" '
        . "maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr[" . $mnftr['id'] . "]' />";
    $elemArr[$i] = ['MenuClass' => 'list_' . $style, 'RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_name' => CentreonUtils::escapeSecure(myDecode($mnftr['name'])), 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&id=' . $mnftr['id'], 'RowMenu_alias' => CentreonUtils::escapeSecure(myDecode($mnftr['alias'])), 'RowMenu_options' => $moptions];
    $style = $style != 'two' ? 'two' : 'one';
}
$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion?')]
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
    . ' var bChecked = isChecked(); '
    . " if (this.form.elements['o1'].selectedIndex != 0 && !bChecked) {"
    . " alert('" . _('Please select one or more items') . "'); return false;} "
    . "if (this.form.elements['o1'].selectedIndex == 1 && confirm('"
    . _('Do you confirm the duplication?') . "')) {"
    . " 	setO(this.form.elements['o1'].value); submit();} "
    . "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('"
    . _('Do you confirm the deletion?') . "')) {"
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

$attrs2 = ['onchange' => 'javascript: '
    . ' var bChecked = isChecked(); '
    . " if (this.form.elements['o2'].selectedIndex != 0 && !bChecked) {"
    . " alert('" . _('Please select one or more items') . "'); return false;} "
    . "if (this.form.elements['o2'].selectedIndex == 1 && confirm('"
    . _('Do you confirm the duplication?') . "')) {"
    . " 	setO(this.form.elements['o2'].value); submit();} "
    . "else if (this.form.elements['o2'].selectedIndex == 2 && confirm('"
    . _('Do you confirm the deletion?') . "')) {"
    . " 	setO(this.form.elements['o2'].value); submit();} "
    . "else if (this.form.elements['o2'].selectedIndex == 3) {"
    . " 	setO(this.form.elements['o2'].value); submit();} "
    . ''];
$form->addElement(
    'select',
    'o2',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs2
);
$form->setDefaults(['o2' => null]);

$o1 = $form->getElement('o1');
$o1->setValue(null);
$o1->setSelected(null);

$o2 = $form->getElement('o2');
$o2->setValue(null);
$o2->setSelected(null);

$tpl->assign('limit', $limit);
$tpl->assign('searchTM', $search);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listMnftr.ihtml');
