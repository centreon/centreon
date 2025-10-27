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

// initializing filters values
$search = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['searchDT'] ?? $_GET['searchDT'] ?? ''
);

if (isset($_POST['Search'])) {
    // saving chosen filters values
    $centreon->historySearch[$url] = [];
    $centreon->historySearch[$url]['search'] = $search;
} else {
    // restoring saved values
    $search = $centreon->historySearch[$url]['search'] ?? '';
}

$downtime->setSearch($search);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// start header menu
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Alias'));
$tpl->assign('headerMenu_status', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));

// Nagios list
$rows = $downtime->getNbRows();

include './include/common/checkPagination.php';

$listDowntime = $downtime->getList($num, $limit, $type);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Fill a tab with a mutlidimensionnal Array we put in $tpl
$elemArr = [];
$centreonToken = createCSRFToken();

foreach ($listDowntime as $dt) {
    $moptions = '';
    $selectedElements = $form->addElement('checkbox', 'select[' . $dt['dt_id'] . ']');
    if ($dt['dt_activate']) {
        $moptions .= "<a href='main.php?p=" . $p . '&dt_id=' . $dt['dt_id'] . '&o=u&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/disabled.png' "
            . "class='ico-14 margin_right' border='0' alt='" . _('Disabled') . "'></a>";
    } else {
        $moptions .= "<a href='main.php?p=" . $p . '&dt_id=' . $dt['dt_id'] . '&o=e&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/enabled.png' "
            . "class='ico-14 margin_right' border='0' alt='" . _('Enabled') . "'></a>";
    }
    $moptions .= '<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57))'
        . ' event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57))'
        . " return false;\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" "
        . "name='dupNbr[" . $dt['dt_id'] . "]'></input>";
    $elemArr[] = ['MenuClass' => 'list_' . $style, 'RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_name' => $dt['dt_name'], 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&dt_id=' . $dt['dt_id'], 'RowMenu_desc' => $dt['dt_description'], 'RowMenu_status' => $dt['dt_activate'] ? _('Enabled') : _('Disabled'), 'RowMenu_options' => $moptions];
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
foreach (['o1', 'o2'] as $option) {
    $attrs1 = ['onchange' => 'javascript: '
        . ' var bChecked = isChecked(); '
        . " if (this.form.elements['" . $option . "'].selectedIndex != 0 && !bChecked) {"
        . " alert('" . _('Please select one or more items') . "'); return false;} "
        . "if (this.form.elements['" . $option
        . "'].selectedIndex == 1 && confirm('" . _('Do you confirm the duplication ?') . "')) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option
        . "'].selectedIndex == 2 && confirm('" . _('Do you confirm the deletion ?') . "')) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option
        . "'].selectedIndex == 3 || this.form.elements['" . $option . "'].selectedIndex == 4) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . ''];
    $form->addElement(
        'select',
        $option,
        null,
        [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')],
        $attrs1
    );
    $form->setDefaults([$option => null]);
    $o1 = $form->getElement($option);
    $o1->setValue(null);
    $o1->setSelected(null);
}

$tpl->assign('limit', $limit);
$tpl->assign('searchDT', $search);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listDowntime.html');
