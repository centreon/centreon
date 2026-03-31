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

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// Header labels
$tpl->assign('headerMenu_desc', _('Name'));
$tpl->assign('headerMenu_alias', _('Alias'));
$tpl->assign('headerMenu_retry', _('Scheduling'));
$tpl->assign('headerMenu_parent', _('Templates'));
$tpl->assign('headerMenu_options', _('Options'));

// Default limit from config
$defaultLimit = 30;
$dbResult = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'maxViewConfiguration'");
if ($gopt = $dbResult->fetch()) {
    $defaultLimit = (int) $gopt['value'] ?: 30;
}
$tpl->assign('defaultLimit', $defaultLimit);
$tpl->assign('stPage', $p);

// Search filters (for initial display)
$search = htmlspecialchars($_POST['searchST'] ?? $_GET['searchST'] ?? $centreon->historySearch[$url]['search'] ?? '');
$displayLocked = filter_var(
    $_POST['displayLocked'] ?? $_GET['displayLocked'] ?? 'off',
    FILTER_VALIDATE_BOOLEAN
);

// Keep checkbox state across pagination
if (
    isset($centreon->historyPage[$url])
    && ($centreon->historyPage[$url] > 0)
    && isset($centreon->historySearch[$url]['displayLocked'])
) {
    $displayLocked = $centreon->historySearch[$url]['displayLocked'];
}

$centreon->historySearch[$url] = [
    'search' => $search,
    'displayLocked' => $displayLocked,
];

$tpl->assign('searchST', $search);
$tpl->assign('displayLocked', $displayLocked);

// Messages
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]
);

// Bulk action dropdowns
$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$setO = "function setO(_i) { document.forms['form'].elements['o'].value = _i; }";

$attrs = ['onchange' => 'javascript: '
    . ' var bChecked = isChecked(); '
    . " if (this.form.elements[this.name].selectedIndex != 0 && !bChecked) {"
    . " alert('" . _('Please select one or more items') . "'); return false;} "
    . "if (this.form.elements[this.name].selectedIndex == 1 && confirm('"
    . _('Do you confirm the duplication ?') . "')) {"
    . " setO(this.form.elements[this.name].value); submit();} "
    . "else if (this.form.elements[this.name].selectedIndex == 2 && confirm('"
    . _('Do you confirm the deletion ?') . "')) {"
    . " setO(this.form.elements[this.name].value); submit();} "
    . "else if (this.form.elements[this.name].selectedIndex == 3){"
    . " setO(this.form.elements[this.name].value); submit();} "
    . "this.form.elements[this.name].selectedIndex = 0"];

$form->addElement(
    'select',
    'o1',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change')],
    $attrs
);
$form->addElement(
    'select',
    'o2',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change')],
    $attrs
);
$form->setDefaults(['o1' => null, 'o2' => null]);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());

?>
<script type="text/javascript">
function setO(_i) { document.forms['form'].elements['o'].value = _i; }
</script>
<?php

$tpl->display('listServiceTemplateModel.ihtml');
