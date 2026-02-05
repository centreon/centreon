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

include './include/common/autoNumLimit.php';

// Init Host Method
$host_method = new CentreonHost($pearDB);
$mediaObj = new CentreonMedia($pearDB);

// Get Extended informations
$ehiCache = [];
$DBRESULT = $pearDB->query('SELECT ehi_icon_image, host_host_id FROM extended_host_information');
while ($ehi = $DBRESULT->fetch()) {
    $ehiCache[$ehi['host_host_id']] = $ehi['ehi_icon_image'];
}
$DBRESULT->closeCursor();

$search = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['searchHT'] ?? $_GET['searchHT'] ?? $centreon->historySearch[$url]['search'] ?? ''
);

$displayLocked = filter_var(
    $_POST['displayLocked'] ?? $_GET['displayLocked'] ?? 'off',
    FILTER_VALIDATE_BOOLEAN
);

// keep checkbox state if navigating in pagination
// this trick is mandatory cause unchecked checkboxes do not post any data
if (
    (isset($centreon->historyPage[$url]) && $centreon->historyPage[$url] > 0 || $num !== 0)
    && isset($centreon->historySearch[$url]['displayLocked'])
) {
    $displayLocked = $centreon->historySearch[$url]['displayLocked'];
}

// store filters in session
$centreon->historySearch[$url] = [
    'search' => $search,
    'displayLocked' => $displayLocked,
];

// Locked filter
$lockedFilter = $displayLocked ? '' : 'AND host_locked = 0 ';

// Host Template list
$rq = 'SELECT SQL_CALC_FOUND_ROWS host_id, host_name, host_alias, host_template_model_htm_id '
    . 'FROM host '
    . "WHERE host_register = '0' "
    . $lockedFilter;
if ($search) {
    $rq .= "AND (host_name LIKE '%" . CentreonDB::escape($search) . "%' OR host_alias LIKE '%"
        . CentreonDB::escape($search) . "%')";
}
$rq .= ' ORDER BY host_name LIMIT ' . $num * $limit . ', ' . $limit;

$DBRESULT = $pearDB->query($rq);
$rows = $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

include './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

// start header menu
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Alias'));
$tpl->assign('headerMenu_svChilds', _('Linked Services Templates'));
$tpl->assign('headerMenu_parent', _('Templates'));
$tpl->assign('headerMenu_options', _('Options'));

$search = tidySearchKey($search, $advanced_search);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Fill a tab with a multidimensional Array we put in $tpl
$elemArr = [];
$centreonToken = createCSRFToken();

for ($i = 0; $host = $DBRESULT->fetch(); $i++) {
    $moptions = '';
    $selectedElements = $form->addElement('checkbox', 'select[' . $host['host_id'] . ']');
    if (isset($lockedElements[$host['host_id']])) {
        $selectedElements->setAttribute('disabled', 'disabled');
    } else {
        $moptions .= '<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
            . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) '
            . "return false;\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" "
            . "name='dupNbr[" . $host['host_id'] . "]' />";
    }
    // If the name of our Host Model is in the Template definition, we have to catch it, whatever the level of it :-)
    if (! $host['host_name']) {
        $host['host_name'] = getMyHostName($host['host_template_model_htm_id']);
    }

    // TPL List
    $tplArr = [];
    $tplStr = null;

    $tplArr = getMyHostMultipleTemplateModels($host['host_id']);
    if (count($tplArr)) {
        $firstTpl = 1;
        foreach ($tplArr as $key => $value) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            if ($firstTpl) {
                $tplStr .= "<a href='main.php?p=60103&o=c&host_id=" . $key . "'>" . $value . '</a>';
                $firstTpl = 0;
            } else {
                $tplStr .= "&nbsp;|&nbsp;<a href='main.php?p=60103&o=c&host_id=" . $key . "'>" . $value . '</a>';
            }
        }
    }

    // Check icon
    $isHostTemplateSvgFile = true;
    if ((isset($ehiCache[$host['host_id']]) && $ehiCache[$host['host_id']])) {
        $isHostTemplateSvgFile = false;
        $host_icone = './img/media/' . $mediaObj->getFilename($ehiCache[$host['host_id']]);
    } elseif (
        $icone = $host_method->replaceMacroInString(
            $host['host_id'],
            getMyHostExtendedInfoImage($host['host_id'], 'ehi_icon_image', 1)
        )
    ) {
        $isHostTemplateSvgFile = false;
        $host_icone = './img/media/' . $icone;
    } else {
        $isHostTemplateSvgFile = true;
        $host_icone = returnSvg('www/img/icons/host.svg', 'var(--icons-fill-color)', 16, 16);
    }

    // Service List
    $svArr = [];
    $svStr = null;
    $svArr = getMyHostServices($host['host_id']);
    $elemArr[$i] = [
        'MenuClass' => 'list_' . $style,
        'RowMenu_select' => $selectedElements->toHtml(),
        'RowMenu_name' => $host['host_name'],
        'RowMenu_link' => 'main.php?p=' . $p . '&o=c&host_id=' . $host['host_id'],
        'RowMenu_desc' => $host['host_alias'],
        'RowMenu_icone' => $host_icone,
        'RowMenu_svChilds' => count($svArr),
        'RowMenu_parent' => CentreonUtils::escapeSecure($tplStr),
        'RowMenu_options' => $moptions,
        'isHostTemplateSvgFile' => $isHostTemplateSvgFile,
    ];
    $style = $style != 'two' ? 'two' : 'one';
}
$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion ?')]
);

// Toolbar select
?>
    <script type="text/javascript">
        function setO(_i) {
            document.forms['form'].elements['o'].value = _i;
        }
    </SCRIPT>
<?php
foreach (['o1', 'o2'] as $option) {
    $attrs1 = ['onchange' => 'javascript: '
        . 'var bChecked = isChecked();'
        . "if (this.form.elements['{$option}'].selectedIndex != 0 && !bChecked) {"
        . "   alert('" . _('Please select one or more items') . "'); return false;} "
        . "if (this.form.elements['{$option}'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication ?') . "')) {"
        . "   setO(this.form.elements['{$option}'].value); submit();} "
        . "else if (this.form.elements['{$option}'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . "   setO(this.form.elements['{$option}'].value); submit();} "
        . "else if (this.form.elements['{$option}'].selectedIndex == 3 || "
        . "this.form.elements['{$option}'].selectedIndex == 4 || this.form.elements['{$option}'].selectedIndex == 5){"
        . "   setO(this.form.elements['{$option}'].value); submit();} "
        . "this.form.elements['o1'].selectedIndex = 0"];
    $form->addElement(
        'select',
        $option,
        null,
        [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change')],
        $attrs1
    );
    $form->setDefaults([$option => null]);
    $o1 = $form->getElement($option);
    $o1->setValue(null);
    $o1->setSelected(null);
}

$tpl->assign('limit', $limit);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('searchHT', $search);
$tpl->assign('displayLocked', $displayLocked);
$tpl->display('listHostTemplateModel.ihtml');
