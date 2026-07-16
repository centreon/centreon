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

$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_description', _('Description'));
$tpl->assign('headerMenu_command_line', _('Command Line'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('connPage', $p);

$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchC', $search);

$dbResult = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'maxViewConfiguration'");
$gopt = $dbResult->fetch();
$defaultLimit = (int) ($gopt['value'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$tpl->assign('msg', ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]);

// Welcome / empty-state labels (JS-safe)
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$tpl->assign('welcomeTitleJs', json_encode(_('Welcome to the connectors page'), $jsonFlags));
$tpl->assign('welcomeDescJs', json_encode(_('Define connectors used to speed up plugin execution on your pollers.'), $jsonFlags));
$tpl->assign('welcomeCtaJs', json_encode(_('Add connector'), $jsonFlags));

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

// "More actions" dropdown (duplicate / delete) sitting next to the Add button
// Styled confirmation modals (clMoreAction in listing.js) replace the
// native confirm()/alert(); messages passed as data-* attributes.
$attrs = [
    'onchange' => 'clMoreAction(this);',
    'data-msg-select' => _('Please select one or more items'),
    'data-title-delete' => _('Delete connector'),
    'data-msg-delete' => _('You are about to delete the selected connector(s). This action cannot be undone. Do you want to delete?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate' => _('Duplicate connector'),
    'data-msg-duplicate' => _('Do you want to duplicate the selected connector(s)?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];
$form->addElement('select', 'o1', null,
    [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete')], $attrs);
$form->setDefaults(['o1' => null]);
$el = $form->getElement('o1');
$el->setValue(null);
$el->setSelected(null);

$tpl->assign('limit', $limit);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listConnector.ihtml');
