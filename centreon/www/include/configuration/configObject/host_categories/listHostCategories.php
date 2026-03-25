<?php

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
    $attrs = ['onchange' => 'javascript: '
        . ' var bChecked = isChecked(); '
        . " if (this.form.elements['" . $option . "'].selectedIndex != 0 && !bChecked) {"
        . " alert('" . _('Please select one or more items') . "'); return false;} "
        . "if (this.form.elements['" . $option . "'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option . "'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "else if (this.form.elements['" . $option . "'].selectedIndex == 3 || "
        . "this.form.elements['" . $option . "'].selectedIndex == 4) {"
        . " 	setO(this.form.elements['" . $option . "'].value); submit();} "
        . "this.form.elements['" . $option . "'].selectedIndex = 0"];
    $form->addElement('select', $option, null,
        [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')], $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

$tpl->assign('limit', $limit);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listHostCategories.ihtml');
