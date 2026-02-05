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

include './include/common/autoNumLimit.php';

$search = $_POST['searchN'] ?? $_GET['searchN'] ?? null;

if (! is_null($search)) {
    $search = HtmlSanitizer::createFromString($search)->sanitize()->getString();
    // saving filters values
    $centreon->historySearch[$url] = [];
    $centreon->historySearch[$url]['search'] = $search;
} else {
    // restoring saved values
    $search = $centreon->historySearch[$url]['search'] ?? null;
}

$SearchTool = '';
if (! is_null($search)) {
    $SearchTool .= " WHERE nagios_name LIKE '%{$search}%' ";
}

$aclCond = '';
if (! $centreon->user->admin && count($allowedMainConf)) {
    $aclCond = isset($search) && $search ? ' AND ' : ' WHERE ';
    $aclCond .= 'nagios_id IN (' . implode(',', array_keys($allowedMainConf)) . ') ';
}

// nagios servers comes from DB
$nagios_servers = [null => ''];
$dbResult = $pearDB->query('SELECT * FROM nagios_server ORDER BY name');
while ($nagios_server = $dbResult->fetch()) {
    $nagios_servers[$nagios_server['id']] = HtmlSanitizer::createFromString($nagios_server['name'])->sanitize()->getString();
}
$dbResult->closeCursor();

$dbResult = $pearDB->query(
    'SELECT SQL_CALC_FOUND_ROWS nagios_id, nagios_name, nagios_comment, nagios_activate, nagios_server_id '
    . 'FROM cfg_nagios ' . $SearchTool . $aclCond . ' ORDER BY nagios_name LIMIT ' . $num * $limit . ', ' . $limit
);

$rows = $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

include './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate(__DIR__);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// start header menu
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_instance', _('Satellites'));
$tpl->assign('headerMenu_desc', _('Description'));
$tpl->assign('headerMenu_status', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));

// Nagios list
$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

// Fill a tab with a multidimensional Array we put in $tpl
$elemArr = [];
$centreonToken = createCSRFToken();

for ($i = 0; $nagios = $dbResult->fetch(); $i++) {
    $moptions = '';
    $selectedElements = $form->addElement('checkbox', 'select[' . $nagios['nagios_id'] . ']');
    if ($nagios['nagios_activate']) {
        $moptions .= "<a href='main.php?p=" . $p . '&nagios_id=' . $nagios['nagios_id'] . '&o=u&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/disabled.png' class='ico-14' border='0' "
            . "alt='" . _('Disabled') . "'></a>&nbsp;&nbsp;";
    } else {
        $moptions .= "<a href='main.php?p=" . $p . '&nagios_id=' . $nagios['nagios_id'] . '&o=s&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/enabled.png' "
            . "class='ico-14' border='0' alt='" . _('Enabled') . "'></a>&nbsp;&nbsp;";
    }
    $moptions .= '&nbsp;<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
        . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) '
        . "return false;\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr["
        . $nagios['nagios_id'] . "]' />";
    $elemArr[$i] = ['MenuClass' => 'list_' . $style, 'RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_name' => $nagios['nagios_name'], 'RowMenu_instance' => $nagios_servers[$nagios['nagios_server_id']], 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&nagios_id=' . $nagios['nagios_id'], 'RowMenu_desc' => substr($nagios['nagios_comment'], 0, 40), 'RowMenu_status' => $nagios['nagios_activate'] ? _('Enabled') : _('Disabled'), 'RowMenu_badge' => $nagios['nagios_activate'] ? 'service_ok' : 'service_critical', 'RowMenu_options' => $moptions];
    $style = $style != 'two' ? 'two' : 'one';
}

$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion ?')]
);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

foreach (['o1', 'o2'] as $option) {
    $attrs = ['onchange' => 'javascript: '
        . "if (this.form.elements['" . $option . "'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option . "'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option . "'].selectedIndex == 3) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . ''];
    $form->addElement(
        'select',
        $option,
        null,
        [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
        $attrs
    );
    $form->setDefaults([$option => null]);
    $o1 = $form->getElement($option);
    $o1->setValue(null);
}

$tpl->assign('limit', $limit);
$tpl->assign('searchN', $search);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listNagios.ihtml');
