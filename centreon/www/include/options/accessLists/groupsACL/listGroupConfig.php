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

$searchStr = '';
$search = null;

if (isset($_POST['searchACLG'])) {
    $search = HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchACLG']);
    $centreon->historySearch[$url] = $search;
} elseif (isset($_GET['searchACLG'])) {
    $search = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['searchACLG']);
    $centreon->historySearch[$url] = $search;
} elseif (isset($centreon->historySearch[$url])) {
    $search = $centreon->historySearch[$url];
}

if ($search) {
    $searchStr .= 'AND (acl_group_name LIKE :search OR acl_group_alias LIKE :search)';
}

$rq = "
    SELECT SQL_CALC_FOUND_ROWS acl_group_id, acl_group_name, acl_group_alias, acl_group_activate
    FROM acl_groups
    WHERE cloud_specific = 0 {$searchStr}
    ORDER BY acl_group_name LIMIT :num, :limit
";
$statement = $pearDB->prepare($rq);
if ($search) {
    $statement->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
}
$statement->bindValue(':num', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$search = tidySearchKey($search, $advanced_search);
$rows = $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

include './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// start header menu
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Description'));
$tpl->assign('headerMenu_contacts', _('Contacts'));
$tpl->assign('headerMenu_contactgroups', _('Contact Groups'));
$tpl->assign('headerMenu_status', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

// Fill a tab with a mutlidimensionnal Array we put in $tpl
$elemArr = [];
$centreonToken = createCSRFToken();

for ($i = 0; $group = $statement->fetchRow(); $i++) {
    $selectedElements = $form->addElement('checkbox', 'select[' . $group['acl_group_id'] . ']');
    if ($group['acl_group_activate']) {
        $moptions = "<a href='main.php?p=" . $p . '&acl_group_id=' . $group['acl_group_id'] . '&o=u&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/disabled.png' class='ico-14 margin_right' "
            . "border='0' alt='" . _('Disabled') . "'></a>&nbsp;&nbsp;";
    } else {
        $moptions = "<a href='main.php?p=" . $p . '&acl_group_id=' . $group['acl_group_id'] . '&o=s&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/enabled.png' class='ico-14 margin_right' "
            . "border='0' alt='" . _('Enabled') . "'></a>&nbsp;&nbsp;";
    }

    $moptions .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    $moptions .= '<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
        . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) '
        . "return false;\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr["
        . $group['acl_group_id'] . "]' />";

    // Contacts
    $ctNbr = [];
    $rq2 = 'SELECT COUNT(*) AS nbr FROM acl_group_contacts_relations WHERE acl_group_id = :aclGroupId ';
    $dbResult2 = $pearDB->prepare($rq2);
    $dbResult2->bindValue(':aclGroupId', $group['acl_group_id'], PDO::PARAM_INT);
    $dbResult2->execute();
    $ctNbr = $dbResult2->fetchRow();
    $dbResult2->closeCursor();

    $cgNbr = [];
    $rq3 = 'SELECT COUNT(*) AS nbr FROM acl_group_contactgroups_relations WHERE acl_group_id = :aclGroupId ';
    $dbResult3 = $pearDB->prepare($rq3);
    $dbResult3->bindValue('aclGroupId', $group['acl_group_id'], PDO::PARAM_INT);
    $dbResult3->execute();
    $cgNbr = $dbResult3->fetchRow();
    $dbResult3->closeCursor();

    $elemArr[$i] = ['MenuClass' => 'list_' . $style, 'RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_name' => CentreonUtils::escapeAll(
        $group['acl_group_name']
    ), 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&acl_group_id=' . $group['acl_group_id'], 'RowMenu_desc' => CentreonUtils::escapeAll(
        $group['acl_group_alias']
    ), 'RowMenu_contacts' => $ctNbr['nbr'], 'RowMenu_contactgroups' => $cgNbr['nbr'], 'RowMenu_status' => $group['acl_group_activate'] ? _('Enabled') : _('Disabled'), 'RowMenu_badge' => $group['acl_group_activate'] ? 'service_ok' : 'service_critical', 'RowMenu_options' => $moptions];

    $style = $style != 'two' ? 'two' : 'one';
}
$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion ?')]
);

// Toolbar select lgd_more_actions
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

foreach (['o1', 'o2'] as $option) {
    $attrs1 = ['onchange' => 'javascript: '
        . "if (this.form.elements['{$option}'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . "setO(this.form.elements['{$option}'].value); submit();} "
        . "else if (this.form.elements['{$option}'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . "setO(this.form.elements['{$option}'].value); submit();} "
        . "else if (this.form.elements['{$option}'].selectedIndex == 3 || "
        . "this.form.elements['{$option}'].selectedIndex == 4) {"
        . "setO(this.form.elements['{$option}'].value); submit();}"];
    $form->addElement('select', $option, null, [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')], $attrs1);
    $o1 = $form->getElement($option);
    $o1->setValue(null);
}

$tpl->assign('limit', $limit);
$tpl->assign('searchACLG', $search);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());

$tpl->display('listGroupConfig.ihtml');

?>
