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

include_once './include/common/autoNumLimit.php';

$SearchSTR = '';

$search = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['searchCG'] ?? $_GET['searchCG'] ?? null
);

if (isset($_POST['searchCG']) || isset($_GET['searchCG'])) {
    // saving filters values
    $centreon->historySearch[$url] = [];
    $centreon->historySearch[$url]['search'] = $search;
} else {
    // restoring saved values
    $search = $centreon->historySearch[$url]['search'] ?? null;
}

$clauses = [];
if ($search) {
    $clauses = ['cg_name' => ['LIKE', '%' . $search . '%'], 'cg_alias' => ['OR', 'LIKE', '%' . $search . '%']];
}

$aclOptions = ['fields' => ['cg_id', 'cg_name', 'cg_alias', 'cg_activate'], 'keys' => ['cg_id'], 'order' => ['cg_name'], 'conditions' => $clauses];
$cgs = $acl->getContactGroupAclConf($aclOptions);
$rows = count($cgs);

include_once './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Description'));
$tpl->assign('headerMenu_contacts', _('Contacts'));
$tpl->assign('headerMenu_status', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));

// Contactgroup list
$aclOptions['pages'] = $num * $limit . ', ' . $limit;
$cgs = $acl->getContactGroupAclConf($aclOptions);

$search = tidySearchKey($search, $advanced_search);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Fill a tab with a multidimensional Array we put in $tpl
$elemArr = [];
$centreonToken = createCSRFToken();

foreach ($cgs as $cg) {
    $selectedElements = $form->addElement('checkbox', 'select[' . $cg['cg_id'] . ']');
    if ($cg['cg_activate']) {
        $moptions = "<a href='main.php?p=" . $p . '&cg_id=' . $cg['cg_id'] . '&o=u&limit=' . $limit . '&num=' . $num
            . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/disabled.png' class='ico-14 margin_right' border='0' "
            . "alt='" . _('Disabled') . "'></a>&nbsp;&nbsp;";
    } else {
        $moptions = "<a href='main.php?p=" . $p . '&cg_id=' . $cg['cg_id'] . '&o=s&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/enabled.png' class='ico-14 margin_right'"
            . "border='0' alt='" . _('Enabled') . "'></a>&nbsp;&nbsp;";
    }
    $moptions .= '&nbsp;&nbsp;';
    $moptions .= '<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
        . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;'
        . "\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr[" . $cg['cg_id'] . "]' />";

    // Contacts
    $ctNbr = [];
    $rq = "SELECT COUNT(DISTINCT contact_contact_id) AS `nbr`
           FROM `contactgroup_contact_relation` `cgr`
           WHERE `cgr`.`contactgroup_cg_id` = '" . $cg['cg_id'] . "' "
        . $acl->queryBuilder('AND', 'contact_contact_id', $contactstring);
    $dbResult2 = $pearDB->query($rq);
    $ctNbr = $dbResult2->fetch();
    $elemArr[] = ['MenuClass' => 'list_' . $style, 'RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_name' => $cg['cg_name'], 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&cg_id=' . $cg['cg_id'], 'RowMenu_desc' => $cg['cg_alias'], 'RowMenu_contacts' => $ctNbr['nbr'], 'RowMenu_status' => $cg['cg_activate'] ? _('Enabled') : _('Disabled'), 'RowMenu_badge' => $cg['cg_activate'] ? 'service_ok' : 'service_critical', 'RowMenu_options' => $moptions];
    $style = $style != 'two' ? 'two' : 'one';
}
$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion ?'), 'view_notif' => _('View contact group notifications')]
);

foreach (['o1', 'o2'] as $option) {
    $attrs1 = ['onchange' => 'javascript: '
        . ' var bChecked = isChecked(); '
        . " if (this.form.elements['" . $option . "'].selectedIndex != 0 && !bChecked) {"
        . " alert('" . _('Please select one or more items') . "'); return false;} "
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
        $attrs1
    );
    $form->setDefaults([$option => null]);
    $o1 = $form->getElement($option);
    $o1->setValue(null);
    $o1->setSelected(null);
}
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('limit', $limit);
$tpl->assign('searchCG', $search);
$tpl->display('listContactGroup.ihtml');
