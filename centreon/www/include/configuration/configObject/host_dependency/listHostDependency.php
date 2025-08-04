<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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
 */

declare(strict_types=1);

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\ValueObjectException;

if (!isset($centreon)) {
    exit();
}

include_once './class/centreonUtils.class.php';
include './include/common/autoNumLimit.php';

// Preserve and sanitize search term
$rawSearch = $_POST['searchHD'] ?? $_GET['searchHD'] ?? null;

if ($rawSearch !== null) {
    //saving filters values
    $search = HtmlSanitizer::createFromString((string) $rawSearch)
        ->removeTags()
        ->sanitize()
        ->getString();
    $centreon->historySearch[$url]['search'] = $search;
} else {
    //restoring saved values
    $search = $centreon->historySearch[$url]['search'] ?? null;
}

// Fetch dependencies from DB with pagination
try {
    $db = $pearDB;
    $qb = $db->createQueryBuilder();

    $qb->select('dep.dep_id', 'dep.dep_name', 'dep.dep_description')
       ->distinct()
       ->from('dependency', 'dep')
       ->innerJoin('dep', 'dependency_hostParent_relation', 'dhpr', 'dhpr.dependency_dep_id = dep.dep_id');

    if (! $centreon->user->admin) {
        $qb->innerJoin(
            'dep',
            "$dbmon.centreon_acl",
            'acl',
            'dhpr.host_host_id = acl.host_id'
        )
        ->andWhere("acl.group_id IN ({$acl->getAccessGroupsString()})");
    }

    $params = null;
    // Search filter
    if ($search !== null && $search !== '') {
        $qb->andWhere(
            $qb->expr()->or(
                $qb->expr()->like('dep.dep_name', ':search'),
                $qb->expr()->like('dep.dep_description', ':search')
            )
        );
        $params = QueryParameters::create([QueryParameter::string('search', "%$search%")]);

    }

    // Ordering and pagination
    $qb->orderBy('dep.dep_name')
        ->addOrderBy('dep.dep_description')
        ->limit($limit)
        ->offset($num * $limit);

    $sql = $qb->getQuery();
    $dependencies = $db->fetchAllAssociative($sql, $params);

    // Count total for pagination
    $countQueryBuilder = $db->createQueryBuilder()
        ->select('COUNT(DISTINCT dep.dep_id) AS total')
        ->from('dependency', 'dep')
        ->innerJoin('dep', 'dependency_hostParent_relation', 'dhpr', 'dhpr.dependency_dep_id = dep.dep_id');

    if (! $centreon->user->admin) {
        $countQueryBuilder->innerJoin(
            'dep',
            "$dbmon.centreon_acl",
            'acl',
            'dhpr.host_host_id = acl.host_id'
        )
        ->andWhere("acl.group_id IN ({$acl->getAccessGroupsString()})");
    }
    if ($search !== null && $search !== '') {
        $countQueryBuilder->andWhere(
            $countQueryBuilder->expr()->or(
                $countQueryBuilder->expr()->like('dep.dep_name', ':search'),
                $countQueryBuilder->expr()->like('dep.dep_description', ':search')
            )
        );
    }

    $countSql = $countQueryBuilder->getQuery();
    $countResult = $pearDB->fetchAssociative($countSql, $params);
    $rows = (int) ($countResult['total'] ?? 0);
} catch (ValueObjectException | CollectionException | ConnectionException $exception) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while fetching host dependencies',
        ['search' => $search],
        $exception
    );
    $msg = new CentreonMsg();
    $msg->setImage('./img/icons/warning.png');
    $msg->setTextStyle('bold');
    $msg->setText(_('Error while retrieving host dependencies'));
    $dependencies = [];
    $rows = 0;
}

// Pagination setup
include "./include/common/checkPagination.php";

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);
$lvlAccess = ($centreon->user->access->page($p) === 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvlAccess);
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_description', _('Description'));
$tpl->assign('headerMenu_options', _('Options'));

// Build search form & results
$searchKey = tidySearchKey($search, $advanced_search);
$form = new HTML_QuickFormCustom('select_form', 'POST', "?p=$p");
$form->addElement(
    'submit','Search',_('Search'),
    [
        'class' => 'btc bt_success',
        'onClick' => "window.history.replaceState('', '', '?p=$p');"
    ]
);

$elemArr = [];
$style = 'one';
foreach ($dependencies as $dep) {
    $depId = (int) $dep['dep_id'];
    $checkbox = $form->addElement('checkbox', "select[$depId]");
    $dupInput = sprintf(
        '<input onKeypress="if(event.keyCode>31&&(event.keyCode<45||event.keyCode>57))event.returnValue=false;if(event.which>31&&(event.which<45||event.which>57))return false;" maxlength="3" size="3" value="1" style="margin-bottom:0;" name="dupNbr[%d]"/>',
        $depId
    );

    $elemArr[] = [
        'MenuClass' => "list_{$style}",
        'RowMenu_select' => $checkbox->toHtml(),
        'RowMenu_name' => CentreonUtils::escapeSecure(myDecode((string) $dep['dep_name'])),
        'RowMenu_description' => CentreonUtils::escapeSecure(myDecode((string) $dep['dep_description'])),
        'RowMenu_link' => "main.php?p=$p&o=c&dep_id=$depId",
        'RowMenu_options' => $dupInput,
    ];
    $style = ($style === 'one') ? 'two' : 'one';
}

$tpl->assign('elemArr', $elemArr);

// Different messages we put in the template
$tpl->assign('msg', [
    'addL' => "main.php?p=$p&o=a",
    'addT' => _('Add'),
    'delConfirm' => _('Do you confirm the deletion ?'),
]);


// Toolbar select
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php
$attrs1 = ['onchange' => "javascript: " .
    " var bChecked = isChecked(); " .
    " if (this.form.elements['o1'].selectedIndex != 0 && !bChecked) {" .
    " alert('" . _("Please select one or more items") . "'); return false;} " .
    "if (this.form.elements['o1'].selectedIndex == 1 && confirm('" .
    _("Do you confirm the duplication ?") . "')) {" .
    " 	setO(this.form.elements['o1'].value); submit();} " .
    "else if (this.form.elements['o1'].selectedIndex == 2 && confirm('" .
    _("Do you confirm the deletion ?") . "')) {" .
    " 	setO(this.form.elements['o1'].value); submit();} " .
    "else if (this.form.elements['o1'].selectedIndex == 3) {" .
    " 	setO(this.form.elements['o1'].value); submit();} " .
    ""];
$form->addElement(
    'select',
    'o1',
    null,
    [null => _("More actions..."), "m" => _("Duplicate"), "d" => _("Delete")],
    $attrs1
);
$form->setDefaults(['o1' => null]);

$attrs2 = ['onchange' => "javascript: " .
    " var bChecked = isChecked(); " .
    " if (this.form.elements['o2'].selectedIndex != 0 && !bChecked) {" .
    " alert('" . _("Please select one or more items") . "'); return false;} " .
    "if (this.form.elements['o2'].selectedIndex == 1 && confirm('"
    . _("Do you confirm the duplication ?") . "')) {" .
    " 	setO(this.form.elements['o2'].value); submit();} " .
    "else if (this.form.elements['o2'].selectedIndex == 2 && confirm('"
    . _("Do you confirm the deletion ?") . "')) {" .
    " 	setO(this.form.elements['o2'].value); submit();} " .
    "else if (this.form.elements['o2'].selectedIndex == 3) {" .
    " 	setO(this.form.elements['o2'].value); submit();} " .
    ""];
$form->addElement(
    'select',
    'o2',
    null,
    [null => _("More actions..."), "m" => _("Duplicate"), "d" => _("Delete")],
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
$tpl->assign('searchHD', $searchKey);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listHostDependency.ihtml');
