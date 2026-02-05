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

include './include/common/autoNumLimit.php';

$sort_types = ! isset($_GET['sort_types']) ? 0 : $_GET['sort_types'];
$order = ! isset($_GET['order']) ? 'ASC' : $_GET['order'];
$num = ! isset($_GET['num']) ? 0 : $_GET['num'];
$sort_type = ! isset($_GET['sort_type']) ? 'hostGroup_name' : $_GET['sort_type'];

$tab_class = ['0' => 'list_one', '1' => 'list_two'];
$rows = 10;

include_once './include/monitoring/status/Common/default_poller.php';
include_once $path_hg . 'hostGroupJS.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path_hg, '/templates/');

$tpl->assign('p', $p);
$tpl->assign('o', $o);
$tpl->assign('sort_types', $sort_types);
$tpl->assign('num', $num);
$tpl->assign('limit', $limit);
$tpl->assign('mon_host', _('Hosts'));
$tpl->assign('mon_status', _('Status'));
$tpl->assign('mon_ip', _('IP'));
$tpl->assign('mon_last_check', _('Last Check'));
$tpl->assign('mon_duration', _('Duration'));
$tpl->assign('mon_status_information', _('Status information'));
$tpl->assign('poller_listing', $centreon->user->access->checkAction('poller_listing'));

$form = new HTML_QuickFormCustom('select_form', 'GET', '?p=' . $p);

$tpl->assign('order', strtolower($order));
$tab_order = ['sort_asc' => 'sort_desc', 'sort_desc' => 'sort_asc'];
$tpl->assign('tab_order', $tab_order);

$tpl->assign('limit', $limit);
if (isset($_GET['searchHG'])) {
    $tpl->assign('searchHG', $_GET['searchHG']);
} else {
    $tpl->assign('searchHG', '');
}

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);

$tpl->assign('form', $renderer->toArray());
$tpl->display('hostGroup.ihtml');
