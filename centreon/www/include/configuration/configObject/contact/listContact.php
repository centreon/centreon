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

$tpl->assign('headerMenu_name', _('Full Name'));
$tpl->assign('headerMenu_desc', _('Alias / Login'));
$tpl->assign('headerMenu_email', _('Email'));
$tpl->assign('headerMenu_hostNotif', _('Host Notification Period'));
$tpl->assign('headerMenu_svNotif', _('Services Notification Period'));
$tpl->assign('headerMenu_lang', _('Language'));
$tpl->assign('headerMenu_access', _('Access'));
$tpl->assign('headerMenu_admin', _('Admin'));
$tpl->assign('headerMenu_options', _('Options'));
$tpl->assign('isAdmin', $centreon->user->admin);

$tpl->assign('contactPage', $p);

// CSRF token for the single-contact unblock action link
$tpl->assign('centreonToken', createCSRFToken());

// Restore search from history
$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchC', $search);

// Default limit from DB
$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

// Check LDAP configured
$res = $pearDB->query('SELECT count(ar_id) as count_ldap FROM auth_ressource');
$row = $res->fetch();
if ($row['count_ldap'] > 0) {
    $tpl->assign('ldap', '1');
}

// Form for bulk actions
$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Contact group filter (select2 AJAX)
$contactGrRoute = './api/internal.php?object=centreon_configuration_contactgroup&action=list';
// No linkedObject / defaultDataset here: the filter must start empty, and the
// listing restores the chosen value and its label from its own session state.
$attrContactgroups = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $contactGrRoute, 'multiple' => false];
$form->addElement('select2', 'contactGroup', _('Select'), [], $attrContactgroups);

$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'ldap_importL' => 'main.php?p=' . $p . '&o=li', 'ldap_importT' => _('LDAP Import'), 'view_notif' => _('View contact notifications')]
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
        'data-title-delete-one' => _('Delete contact'),
        'data-title-delete-many' => _('Delete contacts'),
        'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> contact. This action cannot be undone. Do you want to delete it?'),
        'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} contacts.</strong> This action cannot be undone. Do you want to delete them?'),
        'data-label-delete' => _('Delete'),
        'data-title-duplicate-one' => _('Duplicate contact'),
        'data-title-duplicate-many' => _('Duplicate contacts'),
        'data-msg-duplicate-one' => _('You are about to duplicate the <strong>{{ name }}</strong> contact. Do you want to duplicate it?'),
        'data-msg-duplicate-many' => _('You are about to duplicate <strong>{{ count }} contacts.</strong> Do you want to duplicate them?'),
        'data-label-duplicate' => _('Duplicate'),
        'data-label-cancel' => _('Cancel'),
    ];

    $formOptions = [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change'), 'ms' => _('Enable'), 'mu' => _('Disable')];

    $form->addElement('select', $option, null, $formOptions, $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}


// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listContact.ihtml');
