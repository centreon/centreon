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

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

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

// Restore search from history
$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchC', $search);

// Default limit from DB
$dbResult = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'maxViewConfiguration'");
$gopt = $dbResult->fetch();
$defaultLimit = (int) ($gopt['value'] ?? 30) ?: 30;
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
$attrContactgroups = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $contactGrRoute, 'multiple' => false, 'linkedObject' => 'centreonContactgroup', 'allowClear' => false];
$form->addElement('select2', 'contactGroup', '', [], $attrContactgroups);

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

foreach (['o1', 'o2'] as $option) {
    $attrs = ['onchange' => 'javascript: '
        . ' var bChecked = isChecked(); '
        . "if (this.form.elements['" . $option . "'].selectedIndex != 0 && !bChecked) {"
        . " alert('" . _('Please select one or more items') . "'); return false;} "
        . "if (this.form.elements['" . $option . "'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option . "'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option . "'].selectedIndex == 3 || "
        . "this.form.elements['" . $option . "'].selectedIndex == 4 || "
        . "this.form.elements['" . $option . "'].selectedIndex == 5) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "this.form.elements['" . $option . "'].selectedIndex = 0"];

    $formOptions = [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change'), 'ms' => _('Enable'), 'mu' => _('Disable')];

    $form->addElement('select', $option, null, $formOptions, $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

$tpl->assign('limit', $limit);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listContact.ihtml');
