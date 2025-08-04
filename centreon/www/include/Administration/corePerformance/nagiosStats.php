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

if (! isset($oreon)) {
    exit();
}

require_once './include/monitoring/common-Func.php';

unset($tpl, $path);

// Time period select
$form = new HTML_QuickFormCustom('form', 'post', '?p=' . $p);

// Get Poller List
$pollerList = [];
$defaultPoller = [];
$DBRESULT = $pearDB->query('SELECT * FROM `nagios_server` WHERE `ns_activate` = 1 ORDER BY `name`');

while ($data = $DBRESULT->fetchRow()) {
    if (isset($_POST['pollers']) && $_POST['pollers'] != '') {
        if ($_POST['pollers'] == $data['id']) {
            $defaultPoller[$data['name']] = $data['id'];
            $pollerId = $data['id'];
        }
    } elseif ($data['localhost']) {
        $defaultPoller[$data['name']] = $data['id'];
        $pollerId = $data['id'];
    }
}
$DBRESULT->closeCursor();

$selectedPoller = isset($_POST['pollers']) && $_POST['pollers'] != ''
    ? $_POST['pollers'] : $defaultPoller;

$attrPollers = ['datasourceOrigin' => 'ajax', 'allowClear' => false, 'availableDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_monitoring_poller&action=list', 'multiple' => false, 'defaultDataset' => $defaultPoller, 'linkedObject' => 'centreonInstance'];
$form->addElement('select2', 'pollers', _('Poller'), [], $attrPollers);

// Get Period
$time_period = ['last3hours'  => _('Last 3 hours'), 'today' => _('Today'), 'yesterday' => _('Yesterday'), 'last4days' => _('Last 4 days'), 'lastweek' => _('Last week'), 'lastmonth' => _('Last month'), 'last6month' => _('Last 6 months'), 'lastyear' => _('Last year')];

$defaultPeriod = [];
$currentPeriod = '';
if (isset($_POST['start']) && ($_POST != '')) {
    $defaultPeriod[$time_period[$_POST['start']]] = $_POST['start'];
    $currentPeriod .= $_POST['start'];
} else {
    $defaultPeriod[$time_period['today']] = 'today';
    $currentPeriod .= 'today';
}

switch ($currentPeriod) {
    case 'last3hours':
        $start = time() - (60 * 60 * 3);
        break;
    case 'today':
        $start = time() - (60 * 60 * 24);
        break;
    case 'yesterday':
        $start = time() - (60 * 60 * 48);
        break;
    case 'last4days':
        $start = time() - (60 * 60 * 96);
        break;
    case 'lastweek':
        $start = time() - (60 * 60 * 168);
        break;
    case 'lastmonth':
        $start = time() - (60 * 60 * 24 * 30);
        break;
    case 'last6month':
        $start = time() - (60 * 60 * 24 * 30 * 6);
        break;
    case 'lastyear':
        $start = time() - (60 * 60 * 24 * 30 * 12);
        break;
}

// Get end values
$end = time();

$periodSelect = ['allowClear' => false, 'multiple' => false, 'defaultDataset' => $defaultPeriod];

$selTP = $form->addElement('select2', 'start', _('Period'), $time_period, $periodSelect);

$options = ['active_host_check' => 'nagios_active_host_execution.rrd', 'active_service_check' => 'nagios_active_service_execution.rrd', 'active_host_last' => 'nagios_active_host_last.rrd', 'active_service_last' => 'nagios_active_service_last.rrd', 'host_latency' => 'nagios_active_host_latency.rrd', 'service_latency' => 'nagios_active_service_latency.rrd', 'host_states' => 'nagios_hosts_states.rrd', 'service_states' => 'nagios_services_states.rrd', 'cmd_buffer' => 'nagios_cmd_buffer.rrd'];

$title = ['active_host_check' => _('Host Check Execution Time'), 'active_host_last' => _('Hosts Actively Checked'), 'host_latency' => _('Host check latency'), 'active_service_check' => _('Service Check Execution Time'), 'active_service_last' => _('Services Actively Checked'), 'service_latency' => _('Service check latency'), 'cmd_buffer' => _('Commands in buffer'), 'host_states' => _('Host status'), 'service_states' => _('Service status')];

$path = './include/Administration/corePerformance/';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path, './');

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);

// Assign values

$tpl->assign('form', $renderer->toArray());

if (isset($_POST['start'])) {
    $tpl->assign('startPeriod', $_POST['start']);
} else {
    $tpl->assign('startPeriod', 'today');
}
if (isset($host_list) && $host_list) {
    $tpl->assign('host_list', $host_list);
}
if (isset($tab_server) && $tab_server) {
    $tpl->assign('tab_server', $tab_server);
}

$tpl->assign('p', $p);
if (isset($pollerName)) {
    $tpl->assign('pollerName', $pollerName);
}
$tpl->assign('options', $options);
$tpl->assign('startTime', $start);
$tpl->assign('endTime', $end);
$tpl->assign('pollerId', $pollerId);
$tpl->assign('title', $title);
$tpl->assign('session_id', session_id());
$tpl->display('nagiosStats.html');
