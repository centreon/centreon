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

// Centreon path for i18n includes
$tpl->assign('centreon_path', _CENTREON_PATH_);

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
$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);
$tpl->assign('stPage', $p);

// Search filters (for initial display)
$search = $_POST['searchST'] ?? $_GET['searchST'] ?? $centreon->historySearch[$url]['search'] ?? '';
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

$tpl->assign('searchST', htmlspecialchars($search));
$tpl->assign('displayLocked', $displayLocked);

// Messages
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]
);

// Bulk action dropdowns
$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Styled, secure confirmation modal (clMoreAction in listing.js) replaces the
// native confirm()/alert(); messages passed as data-* attributes so the handler
// stays locale-independent (keyed on the option value).
$attrs = [
    'onchange' => 'clMoreAction(this);',
    'data-msg-select' => _('Please select one or more items'),
    'data-title-delete-one' => _('Delete service template'),
    'data-title-delete-many' => _('Delete service templates'),
    'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> service template. This action cannot be undone. Do you want to delete it?'),
    'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} service templates.</strong> This action cannot be undone. Do you want to delete them?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate-one' => _('Duplicate service template'),
    'data-title-duplicate-many' => _('Duplicate service templates'),
    'data-msg-duplicate-one' => _('You are about to duplicate the <strong>{{ name }}</strong> service template. Do you want to duplicate it?'),
    'data-msg-duplicate-many' => _('You are about to duplicate <strong>{{ count }} service templates.</strong> Do you want to duplicate them?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];

foreach (['o1'] as $option) {
    $form->addElement(
        'select',
        $option,
        null,
        [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change')],
        $attrs
    );
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());

?>
<script type="text/javascript">
function setO(_i) { document.forms['form'].elements['o'].value = _i; }
</script>
<?php

$tpl->display('listServiceTemplateModel.ihtml');
