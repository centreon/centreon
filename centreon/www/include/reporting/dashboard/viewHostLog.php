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
    exit;
}

// Required files
require_once './include/reporting/dashboard/initReport.php';

// Getting host to report
$id = filter_var($_GET['host'] ?? $_POST['hostElement'] ?? false, FILTER_VALIDATE_INT);

// Formulary

// Host Selection
$formHost = new HTML_QuickFormCustom('formHost', 'post', '?p=' . $p);
$redirect = $formHost->addElement('hidden', 'o');
$redirect->setValue($o);

$hostsRoute = ['datasourceOrigin' => 'ajax', 'multiple' => false, 'linkedObject' => 'centreonHost', 'availableDatasetRoute' => './api/internal.php?object=centreon_configuration_host&action=list', 'defaultDatasetRoute' => './api/internal.php?object=centreon_configuration_host
        &action=defaultValues&target=host&field=host_id&id=' . $id];

$selHost = $formPeriod->addElement(
    'select2',
    'hostElement',
    _('Host'),
    [],
    $hostsRoute
);

$selHost->addJsCallback(
    'change',
    'this.form.submit();'
);
$formHost->addElement(
    'hidden',
    'period',
    $period
);
$formHost->addElement(
    'hidden',
    'StartDate',
    $get_date_start
);
$formHost->addElement(
    'hidden',
    'EndDate',
    $get_date_end
);

if (isset($id)) {
    $formPeriod->setDefaults(['hostElement' => $id]);
}

// Set host id with period selection form
if ($id !== false) {
    $formPeriod->addElement(
        'hidden',
        'host',
        $id
    );

    /*
     * Stats Display for selected host
     * Getting periods values
     */
    $dates = getPeriodToReport('alternate');
    $startDate = $dates[0];
    $endDate = $dates[1];
    // $formPeriod->setDefaults(array('period' => $period));

    // Getting host and his services stats
    $hostStats = getLogInDbForHost($id, $startDate, $endDate, $reportingTimePeriod);

    $hostServicesStats = getLogInDbForHostSVC($id, $startDate, $endDate, $reportingTimePeriod);

    // Chart datas
    $tpl->assign('host_up', $hostStats['UP_TP']);
    $tpl->assign('host_down', $hostStats['DOWN_TP']);
    $tpl->assign('host_unreachable', $hostStats['UNREACHABLE_TP']);
    $tpl->assign('host_undetermined', $hostStats['UNDETERMINED_TP']);
    $tpl->assign('host_maintenance', $hostStats['MAINTENANCE_TP']);

    // Exporting variables for ihtml
    $tpl->assign('totalAlert', $hostStats['TOTAL_ALERTS']);
    $tpl->assign('totalTime', $hostStats['TOTAL_TIME_F']);
    $tpl->assign('summary', $hostStats);
    $tpl->assign('components_avg', array_shift($hostServicesStats));
    $tpl->assign('components', $hostServicesStats);
    $tpl->assign('period_name', _('From'));
    $tpl->assign('date_start', $startDate);
    $tpl->assign('to', _('to'));
    $tpl->assign('date_end', $endDate);
    $tpl->assign('period', $period);
    $tpl->assign('host_id', $id);
    $tpl->assign('Alert', _('Alert'));

    /*
     * Ajax TimeLine and CSV export initialization
     * CSV export
     */
    $tpl->assign(
        'link_csv_url',
        './include/reporting/dashboard/csvExport/csv_HostLogs.php?host='
            . $id . '&start=' . $startDate . '&end=' . $endDate
    );
    $tpl->assign('link_csv_name', _('Export in CSV format'));

    // Status colors
    $color = substr($colors['up'], 1)
        . ':' . substr($colors['down'], 1)
        . ':' . substr($colors['unreachable'], 1)
        . ':' . substr($colors['undetermined'], 1)
        . ':' . substr($colors['maintenance'], 1);

    // Ajax timeline
    $type = 'Host';
    include './include/reporting/dashboard/ajaxReporting_js.php';
} else {
    ?><script type="text/javascript"> function initTimeline() {;} </script> <?php
}
$tpl->assign('resumeTitle', _('Host state'));

// Rendering Forms
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$formPeriod->accept($renderer);
$tpl->assign('formPeriod', $renderer->toArray());

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$formHost->accept($renderer);
$tpl->assign('formHost', $renderer->toArray());

if (
    ! $formPeriod->isSubmitted()
    || ($formPeriod->isSubmitted() && $formPeriod->validate())
) {
    $tpl->display('template/viewHostLog.ihtml');
}
