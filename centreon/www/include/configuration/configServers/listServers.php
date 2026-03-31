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

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level and permissions
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$can_generate = $centreon->user->access->checkAction('generate_cfg');
$can_create_edit = $centreon->user->access->checkAction('create_edit_poller_cfg');
$can_delete = $centreon->user->access->checkAction('delete_poller_cfg');

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_ip_address', _('Address'));
$tpl->assign('headerMenu_type', _('Server type'));
$tpl->assign('headerMenu_is_running', _('Is running ?'));
$tpl->assign('headerMenu_hasChanged', _('Conf Changed'));
$tpl->assign('headerMenu_lastUpdateTime', _('Last Update'));
$tpl->assign('headerMenu_default', _('Default'));
$tpl->assign('headerMenu_status', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('pollerPage', $p);

// Restore search from history
$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchP', $search);

// Default limit
$dbResult = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'maxViewConfiguration'");
$gopt = $dbResult->fetch();
$defaultLimit = (int) ($gopt['value'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$tpl->assign('can_generate', $can_generate);
$tpl->assign('can_create_edit', $can_create_edit);
$tpl->assign('can_delete', $can_delete);
$tpl->assign('is_admin', $is_admin);
$tpl->assign('isRemote', $isRemote);

$tpl->assign(
    'notice',
    _('Only services, servicegroups, hosts and hostgroups are taken in '
        . 'account in order to calculate this status. If you modify a '
        . "template, it won't tell you the configuration had changed.")
);

// Action buttons
if (! $isRemote) {
    $tpl->assign('wizardAddBtn', [
        'link' => './poller-wizard/1',
        'text' => _('Add'),
        'class' => 'btc bt-poller-action bt_success',
        'icon' => returnSvg('www/img/icons/add.svg', 'var(--button-icons-fill-color)', 16, 16),
    ]);
    $tpl->assign('addBtn', [
        'link' => 'main.php?p=' . $p . '&o=a',
        'text' => _('Add (advanced)'),
        'class' => 'btc bt-poller-action bt_success',
        'icon' => returnSvg('www/img/icons/add.svg', 'var(--button-icons-fill-color)', 16, 16),
    ]);
    $tpl->assign('duplicateBtn', [
        'text' => _('Duplicate'),
        'class' => 'btc bt-poller-action bt_success',
        'name' => 'duplicate_action',
        'icon' => returnSvg('www/img/icons/duplicate.svg', 'var(--button-icons-fill-color)', 16, 14),
        'onClickAction' => 'javascript: '
            . ' var bChecked = isChecked(); '
            . " if (!bChecked) { alert('" . _('Please select one or more items') . "'); return false;} "
            . " if (confirm('" . _('Do you confirm the duplication ?') . "')) { setO('m'); submit();} ",
    ]);
    $tpl->assign('deleteBtn', [
        'text' => _('Delete'),
        'class' => 'btc bt-poller-action bt_danger',
        'name' => 'delete_action',
        'icon' => returnSvg('www/img/icons/trash.svg', 'var(--button-icons-fill-color)', 16, 16),
        'onClickAction' => 'javascript: '
            . ' var bChecked = isChecked(); '
            . " if (!bChecked) { alert('" . _('Please select one or more items') . "'); return false;} "
            . " if (confirm('" . _('You are about to delete one or more pollers.\\nThis action is IRREVERSIBLE.\\nDo you confirm the deletion ?')
            . "')) { setO('d'); submit();} ",
    ]);
    $tpl->assign('exportBtn', [
        'link' => 'DYNAMIC_LINK',
        'text' => _('Export configuration'),
        'class' => 'btc bt-poller-action bt_info',
        'icon' => returnSvg('www/img/icons/export.svg', 'var(--button-icons-fill-color)', 14, 14),
    ]);
}

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);
$tpl->assign('limit', $limit);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

if (! $isRemote) {
    $attrs = ['onchange' => 'javascript: '
        . ' var bChecked = isChecked(); '
        . " if (this.form.elements['o1'].selectedIndex != 0 && !bChecked) {"
        . " alert('" . _('Please select one or more items') . "'); return false;} "
        . "if (this.form.elements['o1'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . " 	setO(this.form.elements['o1'].value); submit();} "
        . "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('"
        . _('You are about to delete one or more pollers.\\nThis action is IRREVERSIBLE.\\nDo you confirm the deletion ?')
        . "')) { setO(this.form.elements['o1'].value); submit();} "
        . "this.form.elements['o1'].selectedIndex = 0"];
    $form->addElement('select', 'o1', null, [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')], $attrs);
    $o1 = $form->getElement('o1');
    $o1->setValue(null);
}

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listServers.ihtml');
