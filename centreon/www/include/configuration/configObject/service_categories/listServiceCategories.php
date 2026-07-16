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

if (! isset($oreon)) {
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
$tpl->assign('headerMenu_linked_svc', _('Linked services'));
$tpl->assign('headerMenu_sc_type', _('Type'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('scPage', $p);

// Restore search from history
$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchSC', $search);

// Default limit from DB
$dbResult = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'maxViewConfiguration'");
$gopt = $dbResult->fetch();
$defaultLimit = (int) ($gopt['value'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

// Form for bulk actions
$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

$tpl->assign('msg', ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php
    // Styled confirmation modals (clMoreAction in listing.js) replace the
    // native confirm()/alert(); messages passed as data-* attributes.
    $attrs1 = [
        'onchange' => 'clMoreAction(this);',
        'data-msg-select' => _('Please select one or more items'),
        'data-title-delete' => _('Delete service category'),
        'data-msg-delete' => _('You are about to delete the selected service category(s). This action cannot be undone. Do you want to delete?'),
        'data-label-delete' => _('Delete'),
        'data-title-duplicate' => _('Duplicate service category'),
        'data-msg-duplicate' => _('Do you want to duplicate the selected service category(s)?'),
        'data-label-duplicate' => _('Duplicate'),
        'data-label-cancel' => _('Cancel'),
    ];
$form->addElement(
    'select',
    'o1',
    null,
    [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')],
    $attrs1
);
$o1 = $form->getElement('o1');
$o1->setValue(null);

    // Styled confirmation modals (clMoreAction in listing.js) replace the
    // native confirm()/alert(); messages passed as data-* attributes.
    $attrs2 = [
        'onchange' => 'clMoreAction(this);',
        'data-msg-select' => _('Please select one or more items'),
        'data-title-delete' => _('Delete service category'),
        'data-msg-delete' => _('You are about to delete the selected service category(s). This action cannot be undone. Do you want to delete?'),
        'data-label-delete' => _('Delete'),
        'data-title-duplicate' => _('Duplicate service category'),
        'data-msg-duplicate' => _('Do you want to duplicate the selected service category(s)?'),
        'data-label-duplicate' => _('Duplicate'),
        'data-label-cancel' => _('Cancel'),
    ];
$form->addElement(
    'select',
    'o2',
    null,
    [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')],
    $attrs2
);
$o2 = $form->getElement('o2');
$o2->setValue(null);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
// Welcome / empty-state labels (JS-safe)
$welcomeJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$tpl->assign('welcomeTitleJs', json_encode(_('Welcome to the service categories page'), $welcomeJsonFlags));
$tpl->assign('welcomeDescJs', json_encode(_('Group services into categories to organize and filter your configuration.'), $welcomeJsonFlags));
$tpl->assign('welcomeCtaJs', json_encode(_('Add service category'), $welcomeJsonFlags));

$tpl->display('listServiceCategories.ihtml');
