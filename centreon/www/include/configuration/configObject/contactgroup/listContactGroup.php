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
$tpl->assign('centreon_path', _CENTREON_PATH_);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Description'));
$tpl->assign('headerMenu_contacts', _('Contacts'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('cgPage', $p);

// The term is restored client-side from the listing's own session state;
// the template only needs the key to exist.
$tpl->assign('searchCG', '');

$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]
);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

foreach (['o1'] as $option) {
    // Styled, secure confirmation modal (clMoreAction in listing.js) replaces
    // the native confirm()/alert(); messages passed as data-* attributes so the
    // handler stays locale-independent (keyed on the option value).
    $attrs = [
        'onchange' => 'clMoreAction(this);',
        'data-msg-select' => _('Please select one or more items'),
        'data-title-delete-one' => _('Delete contact group'),
        'data-title-delete-many' => _('Delete contact groups'),
        'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> contact group. This action cannot be undone. Do you want to delete it?'),
        'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} contact groups.</strong> This action cannot be undone. Do you want to delete them?'),
        'data-label-delete' => _('Delete'),
        'data-title-duplicate-one' => _('Duplicate contact group'),
        'data-title-duplicate-many' => _('Duplicate contact groups'),
        'data-msg-duplicate-one' => _('You are about to duplicate the <strong>{{ name }}</strong> contact group. Do you want to duplicate it?'),
        'data-msg-duplicate-many' => _('You are about to duplicate <strong>{{ count }} contact groups.</strong> Do you want to duplicate them?'),
        'data-label-duplicate' => _('Duplicate'),
        'data-label-cancel' => _('Cancel'),
    ];
    $form->addElement(
        'select',
        $option,
        null,
        [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete')],
        $attrs
    );
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listContactGroup.ihtml');
