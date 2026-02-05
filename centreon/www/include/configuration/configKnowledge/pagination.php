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
global $centreon;

if (! isset($centreon)) {
    exit();
}

global $bNewChart, $num, $limit, $search, $url, $pearDB, $search_type_service,
$search_type_host, $host_name, $rows, $p, $gopt, $pagination, $poller, $order, $orderby;

$type = $_REQUEST['type'] ?? null;
$o = $_GET['o'] ?? null;

// saving current pagination filter value and current displayed page
$centreon->historyPage[$url] = $num;
$centreon->historyLastUrl = $url;

$tab_order = ['sort_asc' => 'sort_desc', 'sort_desc' => 'sort_asc'];

if (isset($_GET['search_type_service'])) {
    $search_type_service = $_GET['search_type_service'];
    $centreon->search_type_service = $_GET['search_type_service'];
} elseif (isset($centreon->search_type_service)) {
    $search_type_service = $centreon->search_type_service;
} else {
    $search_type_service = null;
}

if (isset($_GET['search_type_host'])) {
    $search_type_host = $_GET['search_type_host'];
    $centreon->search_type_host = $_GET['search_type_host'];
} elseif (isset($centreon->search_type_host)) {
    $search_type_host = $centreon->search_type_host;
} else {
    $search_type_host = null;
}

if (! isset($_GET['search_type_host'])
    && ! isset($centreon->search_type_host)
    && ! isset($_GET['search_type_service'])
    && ! isset($centreon->search_type_service)
) {
    $search_type_host = 1;
    $centreon->search_type_host = 1;
    $search_type_service = 1;
    $centreon->search_type_service = 1;
}

$url_var = '';

if (isset($search_type_service)) {
    $url_var .= '&search_type_service=' . $search_type_service;
}
if (isset($search_type_host)) {
    $url_var .= '&search_type_host=' . $search_type_host;
}

if (isset($_REQUEST['searchHost']) && $_REQUEST['searchHost']) {
    $url_var .= '&searchHost=' . $_REQUEST['searchHost'];
}

if (isset($_REQUEST['searchHostgroup']) && $_REQUEST['searchHostgroup']) {
    $url_var .= '&searchHostgroup=' . $_REQUEST['searchHostgroup'];
}

if (isset($_REQUEST['searchPoller']) && $_REQUEST['searchPoller']) {
    $url_var .= '&searchPoller=' . $_REQUEST['searchPoller'];
}

if (isset($_REQUEST['searchService']) && $_REQUEST['searchService']) {
    $url_var .= '&searchService=' . $_REQUEST['searchService'];
}

if (isset($_REQUEST['searchServicegroup']) && $_REQUEST['searchServicegroup']) {
    $url_var .= '&searchServicegroup=' . $_REQUEST['searchServicegroup'];
}

if (isset($_REQUEST['searchHostTemplate']) && $_REQUEST['searchHostTemplate']) {
    $url_var .= '&searchHostTemplate=' . $_REQUEST['searchHostTemplate'];
}

if (isset($_REQUEST['searchServiceTemplate']) && $_REQUEST['searchServiceTemplate']) {
    $url_var .= '&searchServiceTemplate=' . $_REQUEST['searchServiceTemplate'];
}

if (isset($_GET['sort_types'])) {
    $url_var .= '&sort_types=' . $_GET['sort_types'];
    $sort_type = $_GET['sort_types'];
}

// Smarty template initialization
$path = './include/configuration/configKnowledge/';
$tpl = SmartyBC::createSmartyTemplate($path, './');

$page_max = ceil($rows / $limit);
if ($num >= $page_max && $rows) {
    $num = $page_max - 1;
}

$pageArr = [];
$iStart = 0;
for ($i = 5, $iStart = $num; $iStart && $i > 0; $i--) {
    $iStart--;
}

for ($i2 = 0, $iEnd = $num; ($iEnd < ($rows / $limit - 1)) && ($i2 < (5 + $i)); $i2++) {
    $iEnd++;
}

if ($rows != 0) {
    for ($i = $iStart; $i <= $iEnd; $i++) {
        $urlPage = 'main.php?p=' . $p . '&order=' . $order . '&orderby=' . $orderby
        . '&num=' . $i . '&limit=' . $limit . '&type=' . $type
        . '&o=' . $o . $url_var;
        $pageArr[$i] = ['url_page' => $urlPage, 'label_page' => '<b>' . ($i + 1) . '</b>', 'num' => $i];
    }

    if ($i > 1) {
        $tpl->assign('pageArr', $pageArr);
    }

    $tpl->assign('num', $num);
    $tpl->assign('first', _('First page'));
    $tpl->assign('previous', _('Previous page'));
    $tpl->assign('next', _('Next page'));
    $tpl->assign('last', _('Last page'));

    if (($prev = $num - 1) >= 0) {
        $tpl->assign(
            'pagePrev',
            ('./main.php?p=' . $p . '&order=' . $order . '&orderby=' . $orderby . '&num=' . $prev . '&limit='
                . $limit . '&type=' . $type . '&o=' . $o . $url_var)
        );
    }

    if (($next = $num + 1) < ($rows / $limit)) {
        $tpl->assign(
            'pageNext',
            ('./main.php?p=' . $p . '&order=' . $order . '&orderby=' . $orderby . '&num=' . $next . '&limit='
                . $limit . '&type=' . $type . '&o=' . $o . $url_var)
        );
    }

    $pageNumber = ceil($rows / $limit);
    if (($rows / $limit) > 0) {
        $tpl->assign('pageNumber', ($num + 1) . '/' . $pageNumber);
    } else {
        $tpl->assign('pageNumber', ($num) . '/' . $pageNumber);
    }

    if ($page_max > 5 && $num != 0) {
        $tpl->assign(
            'firstPage',
            ('./main.php?p=' . $p . '&order=' . $order . '&orderby=' . $orderby . '&num=0&limit='
                . $limit . '&type=' . $type . '&o=' . $o . $url_var)
        );
    }

    if ($page_max > 5 && $num != ($pageNumber - 1)) {
        $tpl->assign(
            'lastPage',
            ('./main.php?p=' . $p . '&order=' . $order . '&orderby=' . $orderby . '&num=' . ($pageNumber - 1)
                . '&limit=' . $limit . '&type=' . $type . '&o=' . $o . $url_var)
        );
    }

    // Select field to change the number of row on the page
    for ($i = 10; $i <= 100; $i = $i + 10) {
        $select[$i] = $i;
    }

    if (isset($gopt[$pagination]) && $gopt[$pagination]) {
        $select[$gopt[$pagination]] = $gopt[$pagination];
    }

    if (isset($rows) && $rows) {
        $select[$rows] = $rows;
    }

    ksort($select);
}

?>
<script type="text/javascript">
    function setL(_this) {
        var _l = document.getElementsByName('l');
        document.forms['form'].elements['limit'].value = _this;
        _l[0].value = _this;
        _l[1].value = _this;
    }
</script>
<?php
$form = new HTML_QuickFormCustom(
    'select_form',
    'GET',
    '?p=' . $p . '&search_type_service=' . $search_type_service . '&search_type_host=' . $search_type_host
);
$selLim = $form->addElement(
    'select',
    'l',
    _('Rows'),
    $select,
    ['onChange' => 'setL(this.value);  this.form.submit()']
);
$selLim->setSelected($limit);

// Element we need when we reload the page
$form->addElement('hidden', 'p');
$form->addElement('hidden', 'search');
$form->addElement('hidden', 'num');
$form->addElement('hidden', 'order');
$form->addElement('hidden', 'type');
$form->addElement('hidden', 'sort_types');
$form->setDefaults(['p' => $p, 'search' => $search, 'num' => $num]);

// Init QuickForm
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);

$host_name = $_GET['host_name'] ?? null;
$status = $_GET['status'] ?? null;

$tpl->assign('host_name', $host_name);
$tpl->assign('status', $status);
$tpl->assign('limite', $limite ?? null);
$tpl->assign('begin', $num);
$tpl->assign('end', $limit);
$tpl->assign('pagin_page', _('Page'));
$tpl->assign(
    'order',
    isset($_GET['order']) && $_GET['order'] === 'DESC' ? 'DESC' : 'ASC'
);
$tpl->assign('tab_order', $tab_order);
$tpl->assign('form', $renderer->toArray());

$tpl->display('templates/pagination.ihtml');
