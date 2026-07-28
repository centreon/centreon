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

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Description'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('sgPage', $p);

// Restore search from history
$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchSG', $search);

// Default limit from DB
$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

// Form for bulk actions (duplicate, delete)
$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Messages
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion ?')]
);

// Toolbar select - bulk actions
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
$o1 = $form->getElement('o1');
$o1->setValue(null);

$attrs = ['onchange' => 'javascript: '
    . ' var bChecked = isChecked(); '
    . " if (this.form.elements['o2'].selectedIndex != 0 && !bChecked) {"
    . " alert('" . _('Please select one or more items') . "'); return false;} "
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
$o2 = $form->getElement('o2');
$o2->setValue(null);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listServiceGroup.ihtml');
