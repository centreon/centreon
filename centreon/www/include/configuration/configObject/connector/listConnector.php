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
$tpl->assign('centreon_path', _CENTREON_PATH_);

$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_description', _('Description'));
$tpl->assign('headerMenu_command_line', _('Command Line'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('connPage', $p);

$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchC', $search);

$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$tpl->assign('msg', ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php

// "More actions" dropdown (duplicate / delete) sitting next to the Add button
$attrs = ['onchange' => 'javascript: '
    . ' var bChecked = isChecked(); '
    . " if (this.form.elements['o1'].selectedIndex != 0 && !bChecked) {"
    . " alert('" . _('Please select one or more items') . "'); return false;} "
    . "if (this.form.elements['o1'].selectedIndex == 1 && confirm('"
    . _('Do you confirm the duplication ?') . "')) {"
    . " 	setO(this.form.elements['o1'].value); submit();} "
    . "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('"
    . _('Do you confirm the deletion ?') . "')) {"
    . " 	setO(this.form.elements['o1'].value); submit();} "
    . "this.form.elements['o1'].selectedIndex = 0"];
$form->addElement('select', 'o1', null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')], $attrs);
$form->setDefaults(['o1' => null]);
$el = $form->getElement('o1');
$el->setValue(null);
$el->setSelected(null);

$tpl->assign('limit', $limit);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listConnector.ihtml');
