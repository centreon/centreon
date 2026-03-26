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

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
include_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';
include_once _CENTREON_PATH_ . 'www/include/reporting/dashboard/common-Func.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDuration.class.php';
include_once _CENTREON_PATH_ . 'www/include/reporting/dashboard/DB-Func.php';

// DB Connexion
$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');

if (! isset($_SESSION['centreon'])) {
    CentreonSession::start();
    if (! CentreonSession::checkSession(session_id(), $pearDB)) {
        echo 'Bad Session';

        exit();
    }
}

$sid = session_id();
if (! empty($sid) && isset($_SESSION['centreon'])) {
    $oreon = $_SESSION['centreon'];
    $res = $pearDB->prepare('SELECT COUNT(*) as count FROM session WHERE user_id = :id');
    $res->bindValue(':id', (int) $oreon->user->user_id, PDO::PARAM_INT);
    $res->execute();
    $row = $res->fetch(PDO::FETCH_ASSOC);
    if ($row['count'] < 1) {
        get_error('bad session id');
    }
} else {
    get_error('need session id!');
}

$centreon = $oreon;

$servicegroupId = filter_var(
    $_GET['servicegroup'] ?? $_POST['servicegroup'] ?? null,
    FILTER_VALIDATE_INT
);

// finding the user's allowed servicegroup
$allowedServicegroup = $centreon->user->access->getServiceGroupAclConf(null, 'broker');

// checking if the user has ACL rights for this resource
if (! $centreon->user->admin
    && $servicegroupId !== null
    && ! array_key_exists($servicegroupId, $allowedServicegroup)
) {
    echo '<div align="center" style="color:red">'
        . '<b>You are not allowed to access this service group</b></div>';

    exit();
}

// Getting time interval to report
$dates = getPeriodToReport();
$startDate = htmlentities($_GET['start'], ENT_QUOTES, 'UTF-8');
$endDate = htmlentities($_GET['end'], ENT_QUOTES, 'UTF-8');
$servicegroupName = getServiceGroupNameFromId($servicegroupId);

// file type setting
header('Cache-Control: public');
header('Pragma: public');
header('Content-Type: application/octet-stream');
header('Content-disposition: filename=' . $servicegroupName . '.csv');

echo _('ServiceGroup') . ';'
    . _('Begin date') . '; '
    . _('End date') . '; '
    . _('Duration') . "\n";

echo $servicegroupName . ';'
    . date(_('d/m/Y H:i:s'), $startDate) . '; '
    . date(_('d/m/Y H:i:s'), $endDate) . '; '
    . ($endDate - $startDate) . "s\n\n";

echo "\n";

$stringMeanTime = _('Mean Time');
$stringAlert = _('Alert');
$stringOk = _('OK');
$stringWarning = _('Warning');
$stringCritical = _('Critical');
$stringUnknown = _('Unknown');
$stringDowntime = _('Scheduled Downtimes');
$stringUndetermined = _('Undetermined');

// Getting service group start
$reportingTimePeriod = getreportingTimePeriod();
$stats = [];
$stats = getLogInDbForServicesGroup(
    $servicegroupId,
    $startDate,
    $endDate,
    $reportingTimePeriod
);

echo _('Status') . ';'
    . _('Total Time') . ';'
    . $stringMeanTime . ';'
    . $stringAlert . "\n";

echo $stringOk . ';'
    . $stats['average']['OK_TP'] . '%;'
    . $stats['average']['OK_MP'] . '%;'
    . $stats['average']['OK_A'] . ";\n";

echo $stringWarning . ';'
    . $stats['average']['WARNING_TP'] . '%;'
    . $stats['average']['WARNING_MP'] . '%;'
    . $stats['average']['WARNING_A'] . ";\n";

echo $stringCritical . ';'
    . $stats['average']['CRITICAL_TP'] . '%;'
    . $stats['average']['CRITICAL_MP'] . '%;'
    . $stats['average']['CRITICAL_A'] . ";\n";

echo $stringUnknown . ';'
    . $stats['average']['UNKNOWN_TP'] . '%;'
    . $stats['average']['UNKNOWN_MP'] . '%;'
    . $stats['average']['UNKNOWN_A'] . ";\n";

echo $stringDowntime . ';'
    . $stats['average']['MAINTENANCE_TP'] . "%;;;\n";

echo $stringUndetermined . ';'
    . $stats['average']['UNDETERMINED_TP'] . "%;;;\n\n";

echo "\n\n";

// Services group services stats
echo _('Host') . ';'
    . _('Service') . ';'
    . $stringOk . ' %;'
    . $stringOk . ' ' . $stringMeanTime . ' %;'
    . $stringOk . ' ' . $stringAlert . ';'
    . $stringWarning . ' %;'
    . $stringWarning . ' ' . $stringMeanTime . ' %;'
    . $stringWarning . ' ' . $stringAlert . ';'
    . $stringCritical . ' %;'
    . $stringCritical . ' ' . $stringMeanTime . ' %;'
    . $stringCritical . ' ' . $stringAlert . ';'
    . $stringUnknown . ' %;'
    . $stringUnknown . $stringMeanTime . ' %;'
    . $stringUnknown . ' ' . $stringAlert . ';'
    . $stringDowntime . ' %;'
    . $stringUndetermined . "\n";

foreach ($stats as $key => $tab) {
    if ($key != 'average') {
        echo $tab['HOST_NAME'] . ';'
            . $tab['SERVICE_DESC'] . ';'
            . $tab['OK_TP'] . '%;'
            . $tab['OK_MP'] . '%;'
            . $tab['OK_A'] . ';'
            . $tab['WARNING_TP'] . '%;'
            . $tab['WARNING_MP'] . '%;'
            . $tab['WARNING_A'] . ';'
            . $tab['CRITICAL_TP'] . '%;'
            . $tab['CRITICAL_MP'] . '%;'
            . $tab['CRITICAL_A'] . ';'
            . $tab['UNKNOWN_TP'] . '%;'
            . $tab['UNKNOWN_MP'] . '%;'
            . $tab['UNKNOWN_A'] . ';'
            . $tab['MAINTENANCE_TP'] . ' %;'
            . $tab['UNDETERMINED_TP'] . "%\n";
    }
}
echo "\n\n";

// Services group stats evolution
echo _('Day') . ';'
    . _('Duration') . ';'
    . $stringOk . ' ' . $stringMeanTime . ';'
    . $stringOk . ' ' . $stringAlert . ';'
    . $stringWarning . ' ' . $stringMeanTime . ';'
    . $stringWarning . ' ' . $stringAlert . ';'
    . $stringUnknown . ' ' . $stringMeanTime . ';'
    . $stringUnknown . ' ' . $stringAlert . ';'
    . $stringCritical . ' ' . $stringMeanTime . ';'
    . $stringCritical . ' ' . $stringAlert . ';'
    . _('Day') . "\n";

$dbResult = $pearDB->prepare(
    'SELECT `service_service_id` FROM `servicegroup_relation` '
    . 'WHERE `servicegroup_sg_id` = :servicegroupId'
);
$dbResult->bindValue(':servicegroupId', $servicegroupId, PDO::PARAM_INT);
$dbResult->execute();

$str = '';
while ($sg = $dbResult->fetch()) {
    if ($str != '') {
        $str .= ', ';
    }
    $str .= "'" . $sg['service_service_id'] . "'";
}
$dbResult->closeCursor();
if ($str == '') {
    $str = "''";
}
unset($sg, $dbResult);

$res = $pearDBO->prepare(
    'SELECT `date_start`, `date_end`, sum(`OKnbEvent`) as OKnbEvent, '
    . 'sum(`CRITICALnbEvent`) as CRITICALnbEvent, '
    . 'sum(`WARNINGnbEvent`) as WARNINGnbEvent, '
    . 'sum(`UNKNOWNnbEvent`) as UNKNOWNnbEvent, '
    . 'avg( `OKTimeScheduled` ) as OKTimeScheduled, '
    . 'avg( `WARNINGTimeScheduled` ) as WARNINGTimeScheduled, '
    . 'avg( `UNKNOWNTimeScheduled` ) as UNKNOWNTimeScheduled, '
    . 'avg( `CRITICALTimeScheduled` ) as CRITICALTimeScheduled '
    . 'FROM `log_archive_service` WHERE `service_id` IN (' . $str . ') '
    . 'AND `date_start` >= :startDate '
    . 'AND `date_end` <= :endDate '
    . 'GROUP BY `date_end`, `date_start` order by `date_start` desc'
);
$res->bindValue(':startDate', $startDate, PDO::PARAM_INT);
$res->bindValue(':endDate', $endDate, PDO::PARAM_INT);
$res->execute();

$statesTab = ['OK', 'WARNING', 'CRITICAL', 'UNKNOWN'];
while ($row = $res->fetch()) {
    $duration = $row['OKTimeScheduled'] + $row['WARNINGTimeScheduled'] + $row['UNKNOWNTimeScheduled']
        + $row['CRITICALTimeScheduled'];
    if ($duration === 0) {
        continue;
    }
    // Percentage by status
    $row['OK_MP'] = round($row['OKTimeScheduled'] * 100 / $duration, 2);
    $row['WARNING_MP'] = round($row['WARNINGTimeScheduled'] * 100 / $duration, 2);
    $row['UNKNOWN_MP'] = round($row['UNKNOWNTimeScheduled'] * 100 / $duration, 2);
    $row['CRITICAL_MP'] = round($row['CRITICALTimeScheduled'] * 100 / $duration, 2);

    echo $row['date_start'] . ';'
        . $duration . 's;'
        . $row['OK_MP'] . '%;'
        . $row['OKnbEvent'] . ';'
        . $row['WARNING_MP'] . '%;'
        . $row['WARNINGnbEvent'] . ';'
        . $row['UNKNOWN_MP'] . '%;'
        . $row['UNKNOWNnbEvent'] . ';'
        . $row['CRITICAL_MP'] . '%;'
        . $row['CRITICALnbEvent'] . ';'
        . date('Y-m-d H:i:s', $row['date_start']) . ";\n";
}
$res->closeCursor();
