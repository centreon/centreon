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

$tpl->assign('headerMenu_name', _('Hostgroup'));
$tpl->assign('headerMenu_desc', _('Service'));
$tpl->assign('headerMenu_sched', _('Scheduling'));
$tpl->assign('headerMenu_parent', _('Template'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('svcHgPage', $p);

$searchHG = $centreon->historySearch[$url]['searchHG'] ?? '';
$searchS  = $centreon->historySearch[$url]['searchS'] ?? '';
$templateVal = $centreon->historySearch[$url]['template'] ?? '';
$statusVal = $centreon->historySearch[$url]['status'] ?? '';
$tpl->assign('searchHG', $searchHG);
$tpl->assign('searchS', $searchS);

$dbResult = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'maxViewConfiguration'");
$gopt = $dbResult->fetch();
$defaultLimit = (int) ($gopt['value'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);
$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'SearchB', _('Search'), $attrBtnSuccess);

$tplRoute = './api/internal.php?object=centreon_configuration_servicetemplate&action=list';
$form->addElement('select2', 'template', '', [], ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $tplRoute, 'multiple' => false, 'defaultDataset' => $templateVal, 'linkedObject' => 'centreonServicetemplates', 'allowClear' => false]);

$statusFilter = ['' => '', 1 => _('Disabled'), 2 => _('Enabled')];
$statusDefault = '';
if ($statusVal) {
    $statusDefault = [$statusFilter[$statusVal] ?? '' => $statusVal];
}
$form->addElement('select2', 'status', '', $statusFilter, ['defaultDataset' => $statusDefault, 'allowClear' => false]);

$tpl->assign('msg', ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]);

?>
<script type="text/javascript">
    function setO(_i) { document.forms['form'].elements['o'].value = _i; }
</script>
<?php

foreach (['o1', 'o2'] as $option) {
    $attrs = ['onchange' => 'javascript: '
        . ' var bChecked = isChecked(); '
        . " if (this.form.elements['" . $option . "'].selectedIndex != 0 && !bChecked) {"
        . " alert('" . _('Please select one or more items') . "'); return false;} "
        . "if (this.form.elements['" . $option . "'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . "     setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option . "'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . "     setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option . "'].selectedIndex >= 3) {"
        . "     setO(this.form.elements['" . $option . "'].value); submit();} "
        . "this.form.elements['" . $option . "'].selectedIndex = 0"];
    $form->addElement('select', $option, null,
        [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change'), 'ms' => _('Enable'), 'mu' => _('Disable'), 'dv' => _('Detach'), 'mvH' => _('Move to hosts')], $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

$tpl->assign('limit', $limit);
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listServiceByHg.ihtml');
