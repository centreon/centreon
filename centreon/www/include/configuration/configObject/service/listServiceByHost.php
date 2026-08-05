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

require_once './class/centreonUtils.class.php';

// Filter state belongs to the client: CentreonListing keeps it in sessionStorage
// and the AJAX endpoint reads it from the request. It must NOT be mirrored into
// the PHP session — a filter cleared in the UI never reaches the session, so the
// stale value came back on the next page load and looked impossible to clear.
//
// Two server-side pre-fills remain, both one-shot:
// - the host name carried by the "Display all Services for this host" link
//   (main.php?p=602&search=<host name>), which wins over the client state,
// - the values a bulk action posts back, so the round-trip keeps its context.
$deepLinkSearch = isset($_GET['search']) && $_GET['search'] !== '';

$searchH = HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchH'] ?? $_GET['search'] ?? '');
$searchS = HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchS'] ?? '');

$tpl = SmartyBC::createSmartyTemplate($path);

// Centreon path for i18n includes
$tpl->assign('centreon_path', _CENTREON_PATH_);

$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// Menu ACL on the linked pages (0 = no access, 1 = read/write, 2 = read-only;
// admins get read/write). Drives whether the host and the service templates are
// clickable in the listing and, if so, in edit (o=c) or view (o=w) mode.
$tpl->assign('hostAccess', (int) $centreon->user->access->page(60101));
$tpl->assign('svcTplAccess', (int) $centreon->user->access->page(60206));

$tpl->assign('headerMenu_name', _('Host'));
$tpl->assign('headerMenu_desc', _('Service'));
$tpl->assign('headerMenu_sched', _('Scheduling'));
$tpl->assign('headerMenu_parent', _('Template'));
$tpl->assign('headerMenu_mon', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('svcPage', $p);

$tpl->assign('searchH', $searchH);
$tpl->assign('searchS', $searchS);
// A deep link is a fresh filter: the template restores nothing from the client
// state in that case, and starts back on page 1.
$tpl->assign('deepLinkSearch', $deepLinkSearch ? 1 : 0);

$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);
$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'SearchB', _('Search'), $attrBtnSuccess);

// Service template select2 (AJAX datasource). The element label is the select2
// placeholder, and allowClear is left to its default so the centreon-select2
// eraser is the one revealed on focus (clInitAdvSelectClear).
// Both select2 filters start empty: CentreonListing re-applies the selected
// option (and its label) from its own session state on init.
$tplRoute = './api/internal.php?object=centreon_configuration_servicetemplate&action=list';
$form->addElement('select2', 'template', _('Select'), [], ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $tplRoute, 'multiple' => false, 'linkedObject' => 'centreonServicetemplates']);

// Status select2 (static options)
$statusFilter = ['' => '', 1 => _('Disabled'), 2 => _('Enabled')];
$form->addElement('select2', 'status', _('Select'), $statusFilter);

$tpl->assign('msg', ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

// Styled, secure confirmation modal (clMoreAction in listing.js) replaces the
// native confirm()/alert(); messages passed as data-* attributes so the handler
// stays locale-independent (keyed on the option value).
$attrs = [
    'onchange' => 'clMoreAction(this);',
    'data-msg-select' => _('Please select one or more items'),
    'data-title-delete-one' => _('Delete service'),
    'data-title-delete-many' => _('Delete services'),
    'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> service. This action cannot be undone. Do you want to delete it?'),
    'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} services.</strong> This action cannot be undone. Do you want to delete them?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate-one' => _('Duplicate service'),
    'data-title-duplicate-many' => _('Duplicate services'),
    'data-msg-duplicate-one' => _('You are about to duplicate the <strong>{{ name }}</strong> service. Do you want to duplicate it?'),
    'data-msg-duplicate-many' => _('You are about to duplicate <strong>{{ count }} services.</strong> Do you want to duplicate them?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];
$form->addElement('select', 'o1', null,
    [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change'), 'ms' => _('Enable'), 'mu' => _('Disable'), 'dv' => _('Detach')], $attrs);
$form->setDefaults(['o1' => null]);
$el = $form->getElement('o1');
$el->setValue(null);
$el->setSelected(null);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listService.ihtml');
