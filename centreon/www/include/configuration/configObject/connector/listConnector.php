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

$tpl = SmartyBC::createSmartyTemplate($path);
$tpl->assign('centreon_path', _CENTREON_PATH_);

$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_description', _('Description'));
$tpl->assign('headerMenu_command_line', _('Command Line'));

$tpl->assign('connPage', $p);

$tpl->assign('batchErrorCount', count($batchErrors ?? []));
$tpl->assign('batchInvalidCount', count($batchInvalid ?? []));

$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$tpl->assign('msg', ['addT' => _('Add')]);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

// clMoreAction reads the confirmation wording from these data-* attributes, so
// the handler stays locale-independent and keys on the option value instead.
$attrs = [
    'onchange' => 'clMoreAction(this);',
    'data-msg-select' => _('Please select one or more items'),
    'data-title-delete-one' => _('Delete connector'),
    'data-title-delete-many' => _('Delete connectors'),
    'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> connector. This action cannot be undone. Do you want to delete it?'),
    'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} connectors.</strong> This action cannot be undone. Do you want to delete them?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate-one' => _('Duplicate connector'),
    'data-title-duplicate-many' => _('Duplicate connectors'),
    'data-msg-duplicate-one' => _('You are about to duplicate the <strong>{{ name }}</strong> connector. Do you want to duplicate it?'),
    'data-msg-duplicate-many' => _('You are about to duplicate <strong>{{ count }} connectors.</strong> Do you want to duplicate them?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];
$form->addElement('select', 'o1', null,
    [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete')], $attrs);
$form->setDefaults(['o1' => null]);
$el = $form->getElement('o1');
$el->setValue(null);
$el->setSelected(null);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listConnector.ihtml');
