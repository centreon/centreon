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
    exit;
}

// Required files
require_once './include/reporting/dashboard/initReport.php';

// Getting hostgroup to report
$id = filter_var($_GET['item'] ?? $_POST['itemElement'] ?? false, FILTER_VALIDATE_INT);
/*
 * Formulary
 *
 * Hostgroup Selection
 *
 */

$formHostGroup = new HTML_QuickFormCustom('formHostGroup', 'post', '?p=' . $p);
$redirect = $formHostGroup->addElement('hidden', 'o');
$redirect->setValue($o);

$hostsGroupRoute = ['datasourceOrigin' => 'ajax', 'multiple' => false, 'linkedObject' => 'centreonHostgroups', 'availableDatasetRoute' => './api/internal.php?object=centreon_configuration_hostgroup&action=list', 'defaultDatasetRoute' => './api/internal.php?object=centreon_configuration_hostgroup'
. '&action=defaultValues&target=service&field=service_hgPars&id=' . $id];
$hostGroupSelectBox = $formPeriod->addElement(
    'select2',
    'itemElement',
    _('Host Group'),
    [],
    $hostsGroupRoute
);
$hostGroupSelectBox->addJsCallback(
    'change',
    'this.form.submit();'
);
$formHostGroup->addElement(
    'hidden',
    'period',
    $period
);
$formHostGroup->addElement(
    'hidden',
    'StartDate',
    $get_date_start
);
$formHostGroup->addElement(
    'hidden',
    'EndDate',
    $get_date_end
);

if (isset($id)) {
    $formPeriod->setDefaults(['itemElement' => $id]);
}

// Set hostgroup id with period selection form
if ($id !== false) {
    $formPeriod->addElement('hidden', 'item', $id);

    /*
     * Stats Display for selected hostgroup
     * Getting periods values
     */
    $dates = getPeriodToReport('alternate');
    $start_date = $dates[0];
    $end_date = $dates[1];

    // Getting hostgroup and his hosts stats
    $hostgroupStats = [];
    $hostgroupStats = getLogInDbForHostGroup($id, $start_date, $end_date, $reportingTimePeriod);

    // Chart datas
    $tpl->assign('hostgroup_up', $hostgroupStats['average']['UP_TP']);
    $tpl->assign('hostgroup_down', $hostgroupStats['average']['DOWN_TP']);
    $tpl->assign('hostgroup_unreachable', $hostgroupStats['average']['UNREACHABLE_TP']);
    $tpl->assign('hostgroup_undetermined', $hostgroupStats['average']['UNDETERMINED_TP']);
    $tpl->assign('hostgroup_maintenance', $hostgroupStats['average']['MAINTENANCE_TP']);

    // Exporting variables for ihtml
    $tpl->assign('totalAlert', $hostgroupStats['average']['TOTAL_ALERTS']);
    $tpl->assign('summary', $hostgroupStats['average']);

    // removing average infos from table
    $hostgroupFinalStats = [];
    foreach ($hostgroupStats as $key => $value) {
        if ($key != 'average') {
            $hostgroupFinalStats[$key] = $value;
        }
    }

    $tpl->assign('components', $hostgroupFinalStats);
    $tpl->assign('period_name', _('From'));
    $tpl->assign('date_start', $start_date);
    $tpl->assign('to', _('to'));
    $tpl->assign('date_end', $end_date);
    $tpl->assign('period', $period);
    $formPeriod->setDefaults(['period' => $period]);
    $tpl->assign('hostgroup_id', $id);
    $tpl->assign('Alert', _('Alert'));

    /*
     * Ajax timeline and CSV export initialization
     * CSV export
     */
    $tpl->assign(
        'link_csv_url',
        './include/reporting/dashboard/csvExport/csv_HostGroupLogs.php?hostgroup='
        . $id . '&start=' . $start_date . '&end=' . $end_date
    );
    $tpl->assign('link_csv_name', _('Export in CSV format'));

    // Status colors
    $color = substr($colors['up'], 1)
            . ':' . substr($colors['down'], 1)
            . ':' . substr($colors['unreachable'], 1)
            . ':' . substr($colors['maintenance'], 1)
            . ':' . substr($colors['undetermined'], 1);

    // Ajax timeline
    $type = 'HostGroup';
    include './include/reporting/dashboard/ajaxReporting_js.php';
} else {
    ?><script type="text/javascript"> function initTimeline() {;} </script><?php
}
$tpl->assign('resumeTitle', _('Hosts group state'));

// Rendering Forms
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$formPeriod->accept($renderer);
$tpl->assign('formPeriod', $renderer->toArray());

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$formHostGroup->accept($renderer);
$tpl->assign('formHostGroup', $renderer->toArray());

if (
    ! $formPeriod->isSubmitted()
    || ($formPeriod->isSubmitted() && $formPeriod->validate())
) {
    $tpl->display('template/viewHostGroupLog.ihtml');
}
