<?php

/*
 * Copyright 2005-2025 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

if (!isset($centreon)) {
    exit();
}

require_once './class/centreonUtils.class.php';
include './include/common/autoNumLimit.php';
require_once _CENTREON_PATH_ . '/www/include/common/sqlCommonFunction.php';

use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\Exception\ConnectionException;


// Sanitize and persist/restore search input
$rawSearch = $_POST['searchH'] ?? $_GET['searchH'] ?? null;
if ($rawSearch !== null) {
    $search = HtmlSanitizer::createFromString($rawSearch)
        ->removeTags()
        ->sanitize()
        ->getString();
    $centreon->historySearch[$url]['search'] = $search;
} else {
    $search = $centreon->historySearch[$url]['search'] ?? null;
}

// Calculate offset
$offset = $num * $limit;

try {
    // Build main query
    $mainSelect = 'SELECT hc.hc_id, hc.hc_name, hc.hc_alias, hc.level, hc.hc_activate ';
    $mainQuery = 'FROM hostcategories hc';

    $parameters = [];

    if ($search !== '') {
        $mainQuery .= " WHERE (hc.hc_name LIKE :search OR hc.hc_alias LIKE :search)";
        $parameters[] = QueryParameter::string('search', "%$search%");
    }

    if (!$centreon->user->admin && $hcString !== "''") {
        $hcIds = array_map(
            fn(string $s) => (int) trim($s, "'\" \t\n\r\0\x0B"),
            explode(',', $hcString)
        );
        $bindparams = createMultipleBindParameters($hcIds, 'hcId', QueryParameterTypeEnum::INTEGER);
        if (count($bindparams["parameters"]) > 0) {
            $mainQuery .= $search !== '' ? " AND " : " WHERE ";
            $mainQuery .= "hc.hc_id IN ({$bindparams['placeholderList']})";
            $parameters = array_merge($parameters, $bindparams["parameters"]);
        }
    }

    $countSql = 'SELECT COUNT(*) AS total ' . $mainQuery;

    $mainQuery = $mainSelect . $mainQuery . " ORDER BY hc.hc_name LIMIT $limit OFFSET $offset";
    $queryParams = QueryParameters::create($parameters);

    // Execute fetch
    $hostCategories = $pearDB->fetchAllAssociative($mainQuery, $queryParams);
    $countRow = $pearDB->fetchAssociative($countSql, $queryParams);
    $totalRows = (int) ($countRow['total'] ?? 0);
} catch (ValueObjectException|CollectionException|ConnectionException $exception) {
    $totalRows = 0;
    $hostCategories = [];
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error fetching host categories list',
        [
            'hcString' => $hcString,
            'search' => $search,
            'limit' => $limit,
            'num' => $num
        ],
        $exception
    );
    $msg = new CentreonMsg();
    $msg->setImage("./img/icons/warning.png");
    $msg->setTextStyle("bold");
    $msg->setText('Error fetching host categories list');
}

// Prepare pagination and template
$rows = $totalRows;
$search = tidySearchKey($search, $advanced_search);

include_once "./include/common/checkPagination.php";

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

/* Access level */
($centreon->user->access->page($p) == 1) ? $lvl_access = 'w' : $lvl_access = 'r';
$tpl->assign('mode_access', $lvl_access);

// Header menu definitions
$tpl->assign('headerMenu_name',  _('Name'));
$tpl->assign('headerMenu_desc',  _('Alias'));
$tpl->assign('headerMenu_status',  _('Status'));
$tpl->assign('headerMenu_hc_type', _('Type'));
$tpl->assign('headerMenu_hostAct', _('Enabled Hosts'));
$tpl->assign('headerMenu_hostDeact', _('Disabled Hosts'));
$tpl->assign('headerMenu_options', _('Options'));

// Build search form
$form = new HTML_QuickFormCustom('select_form', 'POST', "?p=$p");
// Different style between each lines
$style = "one";
$attrBtn = [
    'class'   => 'btc bt_success',
    'onClick' => "window.history.replaceState('', '', '?p={$p}');"
];
$form->addElement('submit', 'Search', _('Search'), $attrBtn);

// Fill a tab with a multidimensional Array we put in $tpl
$elemArr = array();
$centreonToken = createCSRFToken();

// count enabled/disabled hosts per category
try {
    $countsSql = <<<SQL
            SELECT hcr.hostcategories_hc_id AS hc_id,
                SUM(CASE WHEN h.host_activate = "1" THEN 1 ELSE 0 END) AS enabled,
                SUM(CASE WHEN h.host_activate = "0" THEN 1 ELSE 0 END) AS disabled
            FROM hostcategories_relation hcr
            JOIN host h ON h.host_id = hcr.host_host_id
            WHERE h.host_register = "1"
        SQL;

    if (! $centreon->user->admin) {
        $countsSql .= <<<SQL
                AND EXISTS (
                    SELECT 1
                    FROM {$aclDbName}.centreon_acl acl
                    WHERE acl.host_id = h.host_id
                    AND acl.group_id IN ({$acl->getAccessGroupsString()})
                )
            SQL;
    }
    $countsSql .= " GROUP BY hcr.hostcategories_hc_id";

    $countsRows = $pearDB->fetchAllAssociative($countsSql);

    $countsByCategory = [];
    foreach ($countsRows as $rowHc) {
        $countsByCategory[$rowHc['hc_id']] = [
            'enabled'  => (int) $rowHc['enabled'],
            'disabled' => (int) $rowHc['disabled'],
        ];
    }
} catch (ConnectionException|CollectionException|ValueObjectException $exception) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        "Error fetching host categories counts",
        exception: $exception
    );
    $msg = new CentreonMsg();
    $msg->setImage("./img/icons/warning.png");
    $msg->setTextStyle("bold");
    $msg->setText("Error fetching host categories counts");
}
// Populate rows
foreach ($hostCategories as $hc) {
    // selection checkbox + action links
    $selectedElements = $form->addElement('checkbox', "select[{$hc['hc_id']}]");
    $moptions = '';
    if ($hc['hc_activate']) {
        $moptions .= "<a href='main.php?p={$p}&hc_id={$hc['hc_id']}&o=u"
                  . "&limit={$limit}&num={$num}&search={$search}&centreon_token={$centreonToken}'>"
                  . "<img src='img/icons/disabled.png' class='ico-14 margin_right' border='0' alt='"
                  . _('Disabled') . "'></a>";
    } else {
        $moptions .= "<a href='main.php?p={$p}&hc_id={$hc['hc_id']}&o=s"
                  . "&limit={$limit}&num={$num}&search={$search}&centreon_token={$centreonToken}'>"
                  . "<img src='img/icons/enabled.png' class='ico-14 margin_right' border='0' alt='"
                  . _('Enabled') . "'></a>";
    }
    $moptions .= "<input maxlength='3' size='3' value='1' style='margin-bottom:0px;' "
              . "name='dupNbr[{$hc['hc_id']}]' "
              . "onKeypress=\"if(event.keyCode>31&&(event.keyCode<45||event.keyCode>57))"
              . "event.returnValue=false;\" />";

    $elemArr[] = [
        'MenuClass'=> "list_{$style}",
        'RowMenu_select' => $selectedElements->toHtml(),
        'RowMenu_name' => CentreonUtils::escapeSecure($hc['hc_name']),
        'RowMenu_link' => "main.php?p=$p&o=c&hc_id={$hc['hc_id']}",
        'RowMenu_desc' => CentreonUtils::escapeSecure($hc['hc_alias']),
        'RowMenu_hc_type'=> $hc['level']
            ? _('Severity') . " ({$hc['level']})"
            : _('Regular'),
        'RowMenu_status' => $hc['hc_activate'] ? _('Enabled') : _('Disabled'),
        'RowMenu_badge' => $hc['hc_activate'] ? 'service_ok' : 'service_critical',
        'RowMenu_hostAct' => $countsByCategory[$hc['hc_id']]['enabled'] ?? 0,
        'RowMenu_hostDeact' => $countsByCategory[$hc['hc_id']]['disabled'] ?? 0,
        'RowMenu_options' => $moptions,
    ];

    $style = ($style === 'one') ? 'two' : 'one';
}

$tpl->assign('elemArr', $elemArr);
$tpl->assign('limit', $limit);
$tpl->assign('searchHC', $search);

$tpl->assign(
    'msg',
    [
        'addL' => "main.php?p=$p&o=a",
        'addT' => _('Add'),
        'delConfirm' => _('Do you confirm the deletion ?')
    ]
);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php
foreach (['o1', 'o2'] as $option) {
    $attrs1 = ['onchange' =>
        "var bChecked=isChecked();".
        "if(this.form.elements['$option'].selectedIndex!=0&&!bChecked){alert('"
        . _("Please select one or more items") . "');return false;}".
        "if(this.form.elements['$option'].selectedIndex==1&&confirm('"
        . _("Do you confirm the duplication ?") . "')){setO(this.value);submit();}".
        "else if(this.form.elements['$option'].selectedIndex==2&&confirm('"
        . _("Do you confirm the deletion ?") . "')){setO(this.value);submit();}".
        "else if(this.form.elements['$option'].selectedIndex==3){setO(this.value);submit();}".
        "else if(this.form.elements['$option'].selectedIndex==4){setO(this.value);submit();}".
        "this.form.elements['$option'].selectedIndex=0"
    ];
    $form->addElement(
        'select',
        $option,
        null,
        [
            null => _("More actions..."),
            'm' => _("Duplicate"),
            'd' => _("Delete"),
            'ms' => _("Enable"),
            'mu' => _("Disable")
        ],
        $attrs1
    );
    $form->setDefaults(array($option => null));
    $o1 = $form->getElement($option);
    $o1->setValue(null);
    $o1->setSelected(null);
}

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display("listHostCategories.ihtml");
