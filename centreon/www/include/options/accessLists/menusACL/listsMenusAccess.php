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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\ValueObjectException;

include './include/common/autoNumLimit.php';

$searchStr = '';

$search = $_POST['searchACLM'] ?? $_GET['searchACLM'] ?? null;
if (! is_null($search)) {
    $centreon->historySearch[$url] = $search;
} elseif (isset($centreon->historySearch[$url])) {
    $search = $centreon->historySearch[$url];
}

if (! is_null($search)) {
    $search = HtmlSanitizer::createFromString($search)
        ->removeTags()
        ->sanitize()
        ->getString();
}

$rows = 0;
$dataRows = [];

try {
    $offset = (int) ($num * $limit);
    $max = (int) $limit;

    $paramsList = [];

    // ---------- COUNT query ----------
    $countSql = <<<'SQL'
            SELECT COUNT(*) AS total
            FROM acl_topology
        SQL;

    if ($search !== null && $search !== '') {
        $countSql .= ' WHERE (acl_topo_name LIKE :searchLike OR acl_topo_alias = :searchAlias)';
        $paramsList[] = QueryParameter::string('searchLike', '%' . $search . '%');
        $paramsList[] = QueryParameter::string('searchAlias', $search);
    }

    $countParams = QueryParameters::create($paramsList);
    $rows = (int) $pearDB->fetchOne($countSql, $countParams);

    // ---------- DATA query ----------
    $listSql = <<<'SQL'
            SELECT acl_topo_id, acl_topo_name, acl_topo_alias, acl_topo_activate
            FROM acl_topology
        SQL;

    if ($search !== null && $search !== '') {
        $listSql .= ' WHERE (acl_topo_name LIKE :searchLike OR acl_topo_alias = :searchAlias)';
    }

    $listSql .= " ORDER BY acl_topo_name ASC LIMIT {$offset}, {$max}";

    $dataParams = QueryParameters::create($paramsList);
    $dataRows = $pearDB->fetchAllAssociative($listSql, $dataParams);
} catch (CollectionException|ValueObjectException|ConnectionException $exception) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while listing ACL topologies: ' . $exception->getMessage(),
        [
            'search' => $search ?? null,
            'limit' => $limit ?? null,
            'num' => $num ?? null,
        ],
        $exception
    );
    $msg = new CentreonMsg();
    $msg->setImage('./img/icons/warning.png');
    $msg->setTextStyle('bold');
    $msg->setText(_('Error while fetching ACL topologies'));
}

include './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// start header menu
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_alias', _('Description'));
$tpl->assign('headerMenu_status', _('Status'));
$tpl->assign('headerMenu_options', _('Options'));

// end header menu

$search = tidySearchKey($search, $advanced_search);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

// Different style between each lines
$style = 'one';

// Fill a tab with a mutlidimensionnal Array we put in $tpl
$elemArr = [];
$centreonToken = createCSRFToken();

$i = 0;
foreach ($dataRows as $topo) {
    $selectedElements = $form->addElement('checkbox', 'select[' . $topo['acl_topo_id'] . ']');
    if ($topo['acl_topo_activate']) {
        $moptions = "<a href='main.php?p=" . $p . '&acl_topo_id=' . $topo['acl_topo_id'] . '&o=u&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/disabled.png' class='ico-14 margin_right' "
            . "border='0' alt='" . _('Disabled') . "'></a>&nbsp;&nbsp;";
    } else {
        $moptions = "<a href='main.php?p=" . $p . '&acl_topo_id=' . $topo['acl_topo_id'] . '&o=s&limit=' . $limit
            . '&num=' . $num . '&search=' . $search . '&centreon_token=' . $centreonToken
            . "'><img src='img/icons/enabled.png' class='ico-14 margin_right' "
            . "border='0' alt='" . _('Enabled') . "'></a>&nbsp;&nbsp;";
    }
    $moptions .= '&nbsp;';
    $moptions .= '<input onKeypress="if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) '
        . 'event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) '
        . "return false;\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr["
        . $topo['acl_topo_id'] . "]' />";
    // Contacts
    $elemArr[$i++] = ['MenuClass' => 'list_' . $style, 'RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_name' => CentreonUtils::escapeSecure(
        $topo['acl_topo_name'],
        CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
    ), 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&acl_topo_id=' . $topo['acl_topo_id'], 'RowMenu_alias' => CentreonUtils::escapeSecure(
        $topo['acl_topo_alias'],
        CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
    ), 'RowMenu_status' => $topo['acl_topo_activate'] ? _('Enabled') : _('Disabled'), 'RowMenu_badge' => $topo['acl_topo_activate'] ? 'service_ok' : 'service_critical', 'RowMenu_options' => $moptions];

    $style = $style != 'two' ? 'two' : 'one';
}
$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion?')]
);

?>
    <script type="text/javascript">
        function setO(_i) {
            document.forms['form'].elements['o'].value = _i;
        }
    </script>
<?php
foreach (['o1', 'o2'] as $option) {
    $attrs1 = ['onchange' => 'javascript: '
        . "if (this.form.elements['{$option}'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the duplication?') . "')) {"
        . "setO(this.form.elements['{$option}'].value); submit();} "
        . "else if (this.form.elements['{$option}'].selectedIndex == 2 && confirm('"
        . _('Do you confirm the deletion?') . "')) {"
        . "setO(this.form.elements['{$option}'].value); submit();} "
        . "else if (this.form.elements['{$option}'].selectedIndex == 3 || "
        . "this.form.elements['{$option}'].selectedIndex == 4) {"
        . "setO(this.form.elements['{$option}'].value); submit();}"];
    $form->addElement('select', $option, null, [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')], $attrs1);

    $form->setDefaults([$option => null]);
    $o1 = $form->getElement($option);
    $o1->setValue(null);
}

$tpl->assign('limit', $limit);
$tpl->assign('searchACLM', $search);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listsMenusAccess.ihtml');
