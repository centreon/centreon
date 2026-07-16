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

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_alias', _('Alias'));
$tpl->assign('headerMenu_eHosts', _('Enabled Hosts'));
$tpl->assign('headerMenu_dHosts', _('Disabled Hosts'));
$tpl->assign('headerMenu_type', _('Type'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('hcPage', $p);

$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchHC', $search);

$dbResult = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'maxViewConfiguration'");
$gopt = $dbResult->fetch();
$defaultLimit = (int) ($gopt['value'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

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

foreach (['o1', 'o2'] as $option) {
    // Styled confirmation modals (clMoreAction in listing.js) replace the
    // native confirm()/alert(); messages passed as data-* attributes.
    $attrs = [
        'onchange' => 'clMoreAction(this);',
        'data-msg-select' => _('Please select one or more items'),
        'data-title-delete' => _('Delete host category'),
        'data-msg-delete' => _('You are about to delete the selected host category(s). This action cannot be undone. Do you want to delete?'),
        'data-label-delete' => _('Delete'),
        'data-title-duplicate' => _('Duplicate host category'),
        'data-msg-duplicate' => _('Do you want to duplicate the selected host category(s)?'),
        'data-label-duplicate' => _('Duplicate'),
        'data-label-cancel' => _('Cancel'),
    ];
    $form->addElement('select', $option, null,
        [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')], $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

$tpl->assign('limit', $limit);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
// Welcome / empty-state labels (JS-safe)
$welcomeJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$tpl->assign('welcomeTitleJs', json_encode(_('Welcome to the host categories page'), $welcomeJsonFlags));
$tpl->assign('welcomeDescJs', json_encode(_('Group hosts into categories to organize and filter your configuration.'), $welcomeJsonFlags));
$tpl->assign('welcomeCtaJs', json_encode(_('Add host category'), $welcomeJsonFlags));

$tpl->display('listHostCategories.ihtml');
