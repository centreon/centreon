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
$tpl->assign('headerMenu_type', _('Calculation Type'));
$tpl->assign('headerMenu_levelw', _('Warning Level'));
$tpl->assign('headerMenu_levelc', _('Critical Level'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('msPage', $p);

// Restore search from history
$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchMS', $search);

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
    'data-title-delete' => _('Delete meta service'),
    'data-msg-delete' => _('You are about to delete the selected meta service(s). This action cannot be undone. Do you want to delete?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate' => _('Duplicate meta service'),
    'data-msg-duplicate' => _('Do you want to duplicate the selected meta service(s)?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];
$form->addElement(
    'select',
    'o1',
    null,
    [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs1
);
$o1 = $form->getElement('o1');
$o1->setValue(null);

// Styled confirmation modals (clMoreAction in listing.js) replace the
// native confirm()/alert(); messages passed as data-* attributes.
$attrs2 = [
    'onchange' => 'clMoreAction(this);',
    'data-msg-select' => _('Please select one or more items'),
    'data-title-delete' => _('Delete meta service'),
    'data-msg-delete' => _('You are about to delete the selected meta service(s). This action cannot be undone. Do you want to delete?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate' => _('Duplicate meta service'),
    'data-msg-duplicate' => _('Do you want to duplicate the selected meta service(s)?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];
$form->addElement(
    'select',
    'o2',
    null,
    [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs2
);
$o2 = $form->getElement('o2');
$o2->setValue(null);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listMetaService.ihtml');
