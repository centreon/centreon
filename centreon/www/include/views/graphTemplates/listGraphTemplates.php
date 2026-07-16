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
    exit;
}

include './include/common/autoNumLimit.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// Column headers
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Description'));
$tpl->assign('headerMenu_split_component', _('Split Components'));
$tpl->assign('headerMenu_base', _('Base'));

$tpl->assign('gtPage', $p);

// Restore search from history
$search = '';
if (isset($_POST['searchGT'])) {
    $search = $_POST['searchGT'];
} elseif (isset($centreon->historySearch[$url])) {
    $search = $centreon->historySearch[$url];
}
$tpl->assign('searchGT', htmlentities($search ?? '', ENT_QUOTES));

// Default limit
$dbResult = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'maxViewConfiguration'");
$gopt = $dbResult->fetch();
$defaultLimit = (int) ($gopt['value'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

// Form (bulk actions + search submit)
$form = new HTML_QuickFormCustom('form', 'POST', '?p=' . $p);
$form->addElement('submit', 'Search', _('Search'), ['class' => 'btc bt_success']);
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php
// Styled confirmation modals (clMoreAction in listing.js) replace the
// native confirm()/alert(); messages passed as data-* attributes.
$attrs = [
    'onchange' => 'clMoreAction(this);',
    'data-msg-select' => _('Please select one or more items'),
    'data-title-delete' => _('Delete graph template'),
    'data-msg-delete' => _('You are about to delete the selected graph template(s). This action cannot be undone. Do you want to delete?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate' => _('Duplicate graph template'),
    'data-msg-duplicate' => _('Do you want to duplicate the selected graph template(s)?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];
$form->addElement('select', 'o1', null, [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete')], $attrs);
$form->setDefaults(['o1' => null]);
$form->getElement('o1')->setValue(null);

$tpl->assign('msg', ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]);
$tpl->assign('limit', $limit);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
// Welcome / empty-state labels (JS-safe)
$welcomeJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$tpl->assign('welcomeTitleJs', json_encode(_('Welcome to the graph templates page'), $welcomeJsonFlags));
$tpl->assign('welcomeDescJs', json_encode(_('Define reusable graph templates for your performance graphs.'), $welcomeJsonFlags));
$tpl->assign('welcomeCtaJs', json_encode(_('Add graph template'), $welcomeJsonFlags));

$tpl->display('listGraphTemplates.ihtml');
