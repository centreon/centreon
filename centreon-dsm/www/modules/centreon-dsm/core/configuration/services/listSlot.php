<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

if (! isset($oreon)) {
    exit();
}

require './include/common/autoNumLimit.php';

// create TP cache
$tpCache = [];
$dbResult = $pearDB->query('SELECT tp_name, tp_id FROM timeperiod');
while ($data = $dbResult->fetch()) {
    $tpCache[$data['tp_id']] = $data['tp_name'];
}
$dbResult->closeCursor();

if (isset($search)) {
    $dbResult = $pearDB->query(
        "SELECT COUNT(*) FROM mod_dsm_pool WHERE (pool_name LIKE '%"
        . htmlentities($search, ENT_QUOTES) . "%' OR pool_description LIKE '%"
        . htmlentities($search, ENT_QUOTES) . "%')"
    );
} else {
    $dbResult = $pearDB->query('SELECT COUNT(*) FROM mod_dsm_pool');
}

$tmp = $dbResult->fetch();
$rows = $tmp['COUNT(*)'];

require './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// start header menu
$tpl->assign('headerMenu_icone', "<img src='./img/icones/16x16/pin_red.gif'>");
$tpl->assign('headerMenu_desc', _('Description'));
$tpl->assign('headerMenu_name', _('Slot Name'));
$tpl->assign('headerMenu_email', _('Email'));
$tpl->assign('headerMenu_prefix', _('Prefix'));
$tpl->assign('headerMenu_number', _('Number'));
$tpl->assign('headerMenu_status', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));
$tpl->assign('searchLabel', _('Search'));
$tpl->assign('search', $search);
$tpl->assign('p', $p);

if ($search) {
    $rq = "SELECT
            pool_id,
            pool_prefix,
            pool_name,
            pool_description,
            pool_number,
            pool_activate
        FROM mod_dsm_pool
        WHERE (
            pool_name LIKE '%" . htmlentities($search, ENT_QUOTES) . "%'
        OR pool_description LIKE '%" . htmlentities($search, ENT_QUOTES) . "%')
        ORDER BY pool_name LIMIT " . $num * $limit . ', ' . $limit;
} else {
    $rq = 'SELECT
            pool_id,
            pool_prefix,
            pool_name,
            pool_description,
            pool_number,
            pool_activate
        FROM mod_dsm_pool
        ORDER BY pool_name
        LIMIT ' . $num * $limit . ', ' . $limit;
}
$dbResult = $pearDB->query($rq);

$search = tidySearchKey($search, $advanced_search);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

// Fill a tab with a mutlidimensionnal Array we put in $tpl
$elemArr = [];
for ($i = 0; $contact = $dbResult->fetch(); $i++) {
    $selectedElements = $form->addElement('checkbox', 'select[' . $contact['pool_id'] . ']');
    if ($contact['pool_activate']) {
        $moptions = "<a href='main.php?p=" . $p . '&pool_id=' . $contact['pool_id'] . '&o=u&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . "'><img src='img/icones/16x16/element_previous.gif' "
            . "border='0' alt='" . _('Disabled') . "'></a>&nbsp;&nbsp;";
    } else {
        $moptions = "<a href='main.php?p=" . $p . '&pool_id=' . $contact['pool_id'] . '&o=s&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . "'><img src='img/icones/16x16/element_next.gif' "
            . "border='0' alt='" . _('Enabled') . "'></a>&nbsp;&nbsp;";
    }
    $moptions .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    $moptions .= '<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
        . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;" '
        . "maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr["
        . $contact['pool_id'] . "]' />";
    $elemArr[$i] = [
        'MenuClass' => 'list_' . $style,
        'RowMenu_select' => $selectedElements->toHtml(),
        'RowMenu_name' => html_entity_decode($contact['pool_name']),
        'RowMenu_link' => '?p=' . $p . '&o=c&pool_id=' . $contact['pool_id'],
        'RowMenu_desc' => html_entity_decode($contact['pool_description']),
        'RowMenu_number' => html_entity_decode($contact['pool_number'], ENT_QUOTES),
        'RowMenu_prefix' => html_entity_decode($contact['pool_prefix'], ENT_QUOTES),
        'RowMenu_status' => $contact['pool_activate'] ? _('Enabled') : _('Disabled'),
        'RowMenu_options' => $moptions,
    ];
    $style = $style != 'two' ? 'two' : 'one';
}
$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    [
        'addL' => '?p=' . $p . '&o=a',
        'addT' => _('Add'),
    ]
);

// Toolbar select
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</SCRIPT>
<?php
$attrs1 = [
    'onchange' => 'javascript: '
        . "if (this.form.elements['o1'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . "    setO(this.form.elements['o1'].value); submit();} "
        . "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . "    setO(this.form.elements['o1'].value); submit();} "
        . "else if (this.form.elements['o1'].selectedIndex == 3 || this.form.elements['o1'].selectedIndex == 4 || "
        . "this.form.elements['o1'].selectedIndex == 5){"
        . "    setO(this.form.elements['o1'].value); submit();} "
        . "this.form.elements['o1'].selectedIndex = 0",
];
$form->addElement(
    'select',
    'o1',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs1
);
$form->setDefaults(['o1' => null]);

$attrs2 = [
    'onchange' => 'javascript: '
        . "if (this.form.elements['o2'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . "    setO(this.form.elements['o2'].value); submit();} "
        . "else if (this.form.elements['o2'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . "    setO(this.form.elements['o2'].value); submit();} "
        . "else if (this.form.elements['o2'].selectedIndex == 3 || this.form.elements['o2'].selectedIndex == 4 || "
        . "this.form.elements['o2'].selectedIndex == 5){"
        . "    setO(this.form.elements['o2'].value); submit();} "
        . "this.form.elements['o1'].selectedIndex = 0",
];
$form->addElement(
    'select',
    'o2',
    null,
    [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
    $attrs2
);
$form->setDefaults(['o2' => null]);

$o1 = $form->getElement('o1');
$o1->setValue(null);
$o1->setSelected(null);

$o2 = $form->getElement('o2');
$o2->setValue(null);
$o2->setSelected(null);

$tpl->assign('limit', $limit);

// Fill a tab with a mutlidimensionnal Array we put in $tpl
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listSlot.ihtml');
?>
