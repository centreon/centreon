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

$tpl = SmartyBC::createSmartyTemplate($path);

$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// Menu ACL on the Host Templates page (topology 60103): 0 = no access,
// 1 = read/write, 2 = read-only (admins get read/write). Drives whether a host
// template is clickable in the listing and, if so, in edit or view mode.
$tpl->assign('hostTplAccess', (int) $centreon->user->access->page(60103));

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Alias'));
$tpl->assign('headerMenu_address', _('IP / DNS'));
$tpl->assign('headerMenu_poller', _('Poller'));
$tpl->assign('headerMenu_parent', _('Templates'));
$tpl->assign('headerMenu_mon', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('hostPage', $p);
$tpl->assign('Hosts', _('Name'));
$tpl->assign('Hostgroup', _('Hostgroup'));
$tpl->assign('Poller', _('Poller'));
$tpl->assign('Template', _('Template'));
$tpl->assign('listServicesIcon', returnSvg('www/img/icons/all_services.svg', 'var(--icons-fill-color)', 18, 18));
$tpl->assign('HelpServices', _('Display all Services for this host'));

// Restore search from history
$search    = $centreon->historySearch[$url]['search'] ?? '';
$hostgroup = $centreon->historySearch[$url]['hostgroup'] ?? '';
$pollerVal = $centreon->historySearch[$url]['poller'] ?? '';
$templateVal = $centreon->historySearch[$url]['template'] ?? '';
$statusVal = $centreon->historySearch[$url]['status'] ?? '';
$tpl->assign('searchH', $search);

$dbResult = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'maxViewConfiguration'");
$gopt = $dbResult->fetch();
$defaultLimit = (int) ($gopt['value'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'SearchB', _('Search'), $attrBtnSuccess);

// Select2 filters
$hgRoute = './api/internal.php?object=centreon_configuration_hostgroup&action=list';
$form->addElement('select2', 'hostgroup', _('Select'), [], ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $hgRoute, 'multiple' => false, 'defaultDataset' => $hostgroup, 'linkedObject' => 'centreonHostgroups', 'allowClear' => true]);

$pollerRoute = './api/internal.php?object=centreon_configuration_poller&action=list';
$form->addElement('select2', 'poller', _('Select'), [], ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $pollerRoute, 'multiple' => false, 'defaultDataset' => $pollerVal, 'linkedObject' => 'centreonInstance', 'allowClear' => true]);

$tplRoute = './api/internal.php?object=centreon_configuration_hosttemplate&action=list';
$form->addElement('select2', 'template', _('Select'), [], ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $tplRoute, 'multiple' => false, 'defaultDataset' => $templateVal, 'linkedObject' => 'centreonHosttemplates', 'allowClear' => true]);

$statusFilter = ['' => '', 1 => _('Disabled'), 2 => _('Enabled')];
$statusDefault = '';
if ($statusVal) {
    $statusDefault = [$statusFilter[$statusVal] ?? '' => $statusVal];
}
$form->addElement('select2', 'status', '', $statusFilter, ['defaultDataset' => $statusDefault]);

$tpl->assign('msg', ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

foreach (['o1', 'o2'] as $option) {
    // Styled, secure confirmation modal (clMoreAction in listing.js) replaces
    // the native confirm()/alert(); messages passed as data-* attributes so the
    // handler stays locale-independent (keyed on the option value). The delete
    // title/message vary by selection count, with the name (single) or count
    // (several) rendered in bold via the {{ name }} / {{ count }} placeholders.
    $attrs = [
        'onchange' => 'clMoreAction(this);',
        'data-msg-select' => _('Please select one or more items'),
        'data-title-delete-one' => _('Delete host'),
        'data-title-delete-many' => _('Delete hosts'),
        'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> host. This action cannot be undone. Do you want to delete it?'),
        'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} hosts.</strong> This action cannot be undone. Do you want to delete them?'),
        'data-label-delete' => _('Delete'),
        'data-title-duplicate-one' => _('Duplicate host'),
        'data-title-duplicate-many' => _('Duplicate hosts'),
        'data-msg-duplicate-one' => _('You are about to duplicate the <strong>{{ name }}</strong> host. Do you want to duplicate it?'),
        'data-msg-duplicate-many' => _('You are about to duplicate <strong>{{ count }} hosts.</strong> Do you want to duplicate them?'),
        'data-label-duplicate' => _('Duplicate'),
        'data-label-cancel' => _('Cancel'),
    ];
    $form->addElement('select', $option, null,
        [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change'), 'ms' => _('Enable'), 'mu' => _('Disable'), 'dp' => _('Deploy Service')], $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

$tpl->assign('limit', $limit);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listHost.ihtml');
