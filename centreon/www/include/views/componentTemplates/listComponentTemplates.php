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
$tpl->assign('headerMenu_color', _('Color'));
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_host', _('Host'));
$tpl->assign('headerMenu_desc', _('Data Source Name'));
$tpl->assign('headerMenu_legend', _('Legend'));
$tpl->assign('headerMenu_stack', _('Stacked'));
$tpl->assign('headerMenu_order', _('Order'));
$tpl->assign('headerMenu_Transp', _('Transparency'));
$tpl->assign('headerMenu_tickness', _('Thickness'));
$tpl->assign('headerMenu_fill', _('Filling'));

$tpl->assign('curvePage', $p);

// Restore search from history
$search = '';
if (isset($_POST['searchCurve'])) {
    $search = $_POST['searchCurve'];
} elseif (isset($centreon->historySearch[$url])) {
    $search = $centreon->historySearch[$url];
}
$tpl->assign('searchCurve', htmlentities($search ?? '', ENT_QUOTES));

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
    'data-title-delete' => _('Delete component template'),
    'data-msg-delete' => _('You are about to delete the selected component template(s). This action cannot be undone. Do you want to delete?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate' => _('Duplicate component template'),
    'data-msg-duplicate' => _('Do you want to duplicate the selected component template(s)?'),
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
$tpl->display('listComponentTemplates.ihtml');
