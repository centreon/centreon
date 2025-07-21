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

// Getting service group to report
$id = filter_var($_GET['item'] ?? $_POST['itemElement'] ?? false, FILTER_VALIDATE_INT);
// FORMS

$serviceGroupForm = new HTML_QuickFormCustom('formServiceGroup', 'post', '?p=' . $p);
$redirect = $serviceGroupForm->addElement('hidden', 'o');
$redirect->setValue($o);

$serviceGroupRoute = ['datasourceOrigin' => 'ajax', 'multiple' => false, 'linkedObject' => 'centreonServicegroups', 'availableDatasetRoute' => './api/internal.php?object=centreon_configuration_servicegroup&action=list', 'defaultDatasetRoute' => './api/internal.php?object=centreon_configuration_servicegroup'
    . '&action=defaultValues&target=service&field=service_sgs&id=' . $id];
$serviceGroupSelectBox = $formPeriod->addElement(
    'select2',
    'itemElement',
    _('Service Group'),
    [],
    $serviceGroupRoute
);
$serviceGroupSelectBox->addJsCallback(
    'change',
    'this.form.submit();'
);
$serviceGroupForm->addElement(
    'hidden',
    'period',
    $period
);
$serviceGroupForm->addElement(
    'hidden',
    'StartDate',
    $get_date_start
);
$serviceGroupForm->addElement(
    'hidden',
    'EndDate',
    $get_date_end
);

if (isset($id)) {
    $formPeriod->setDefaults(['itemElement' => $id]);
}

// Set servicegroup id with period selection form
if ($id !== false) {
    $formPeriod->addElement(
        'hidden',
        'item',
        $id
    );

    /*
     * Stats Display for selected services group
     * Getting periods values
     */
    $dates = getPeriodToReport('alternate');
    $startDate = $dates[0];
    $endDate = $dates[1];

    // Getting servicegroups logs
    $servicesgroupStats = getLogInDbForServicesGroup($id, $startDate, $endDate, $reportingTimePeriod);

    // Chart datas
    $tpl->assign('servicegroup_ok', $servicesgroupStats['average']['OK_TP']);
    $tpl->assign('servicegroup_warning', $servicesgroupStats['average']['WARNING_TP']);
    $tpl->assign('servicegroup_critical', $servicesgroupStats['average']['CRITICAL_TP']);
    $tpl->assign('servicegroup_unknown', $servicesgroupStats['average']['UNKNOWN_TP']);
    $tpl->assign('servicegroup_undetermined', $servicesgroupStats['average']['UNDETERMINED_TP']);
    $tpl->assign('servicegroup_maintenance', $servicesgroupStats['average']['MAINTENANCE_TP']);

    // Exporting variables for ihtml
    $tpl->assign('totalAlert', $servicesgroupStats['average']['TOTAL_ALERTS']);
    $tpl->assign('summary', $servicesgroupStats['average']);

    // Removing average infos from table
    $servicesgroupFinalStats = [];
    foreach ($servicesgroupStats as $key => $value) {
        if ($key != 'average') {
            $servicesgroupFinalStats[$key] = $value;
        }
    }

    $tpl->assign('components', $servicesgroupFinalStats);
    $tpl->assign('period_name', _('From'));
    $tpl->assign('date_start', $startDate);
    $tpl->assign('to', _('to'));
    $tpl->assign('date_end', $endDate);
    $tpl->assign('period', $period);
    $formPeriod->setDefaults(['period' => $period]);
    $tpl->assign('servicegroup_id', $id);
    $tpl->assign('Alert', _('Alert'));

    /*
     * Ajax timeline and CSV export initialization
     * CSV export
     */
    $tpl->assign(
        'link_csv_url',
        './include/reporting/dashboard/csvExport/csv_ServiceGroupLogs.php?servicegroup='
        . $id . '&start=' . $startDate . '&end=' . $endDate
    );
    $tpl->assign(
        'link_csv_name',
        _('Export in CSV format')
    );

    // Status colors
    $color = substr($colors['up'], 1)
        . ':' . substr($colors['down'], 1)
        . ':' . substr($colors['unreachable'], 1)
        . ':' . substr($colors['maintenance'], 1)
        . ':' . substr($colors['undetermined'], 1);

    // Ajax timeline
    $type = 'ServiceGroup';
    include './include/reporting/dashboard/ajaxReporting_js.php';
} else {
    ?><script type="text/javascript"> function initTimeline() {;} </script> <?php
}
$tpl->assign('resumeTitle', _('Service group state'));
$tpl->assign('p', $p);

// Rendering forms
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$formPeriod->accept($renderer);
$tpl->assign('formPeriod', $renderer->toArray());

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$serviceGroupForm->accept($renderer);
$tpl->assign('serviceGroupForm', $renderer->toArray());

if (
    ! $formPeriod->isSubmitted()
    || ($formPeriod->isSubmitted() && $formPeriod->validate())
) {
    $tpl->display('template/viewServicesGroupLog.ihtml');
}
