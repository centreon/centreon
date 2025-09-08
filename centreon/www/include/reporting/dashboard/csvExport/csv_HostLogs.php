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
require_once _CENTREON_PATH_ . 'www/class/centreonDuration.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
include_once _CENTREON_PATH_ . 'www/include/reporting/dashboard/DB-Func.php';

// DB connexion
$pearDB    = new CentreonDB();
$pearDBO    = new CentreonDB('centstorage');

if (! isset($_SESSION['centreon'])) {
    CentreonSession::start();
    if (! CentreonSession::checkSession(session_id(), $pearDB)) {
        echo 'Bad Session';

        exit();
    }
}

$centreon = $_SESSION['centreon'];

// Checking session
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

// getting host id
$hostId = filter_var(
    $_GET['host'] ?? $_POST['host'] ?? null,
    FILTER_VALIDATE_INT
);

$allowedHosts = $centreon->user->access->getHostAclConf(null, 'broker');

// checking if the user has ACL rights for this resource
if (! $centreon->user->admin
    && $hostId !== null
    && ! array_key_exists($hostId, $allowedHosts)
) {
    echo '<div align="center" style="color:red">'
        . '<b>You are not allowed to access this host</b></div>';

    exit();
}

// Getting time interval to report
$dates = getPeriodToReport();
$startDate = htmlentities($_GET['start'], ENT_QUOTES, 'UTF-8');
$endDate = htmlentities($_GET['end'], ENT_QUOTES, 'UTF-8');
$hostName = getHostNameFromId($hostId);

// file type setting
header('Cache-Control: public');
header('Pragma: public');
header('Content-Type: application/octet-stream');
header('Content-disposition: filename=' . $hostName . '.csv');

echo _('Host') . ';'
    . _('Begin date') . '; '
    . _('End date') . '; '
    . _('Duration') . "\n";

echo $hostName . '; '
    . date(_('d/m/Y H:i:s'), $startDate) . '; '
    . date(_('d/m/Y H:i:s'), $endDate) . '; '
    . ($endDate - $startDate) . "s\n";
echo "\n";
echo "\n";

echo _('Status') . ';'
    . _('Duration') . ';'
    . _('Total Time') . ';'
    . _('Mean Time') . '; '
    . _('Alert') . "\n";

// Getting stats on Host
$reportingTimePeriod = getreportingTimePeriod();
$hostStats = getLogInDbForHost(
    $hostId,
    $startDate,
    $endDate,
    $reportingTimePeriod
);

echo _('DOWN') . ';'
    . $hostStats['DOWN_T'] . 's;'
    . $hostStats['DOWN_TP'] . '%;'
    . $hostStats['DOWN_MP'] . '%;'
    . $hostStats['DOWN_A'] . ";\n";

echo _('UP') . ';'
    . $hostStats['UP_T'] . 's;'
    . $hostStats['UP_TP'] . '%;'
    . $hostStats['UP_MP'] . '%;'
    . $hostStats['UP_A'] . ";\n";

echo _('UNREACHABLE') . ';'
    . $hostStats['UNREACHABLE_T'] . 's;'
    . $hostStats['UNREACHABLE_TP'] . '%;'
    . $hostStats['UNREACHABLE_MP'] . '%;'
    . $hostStats['UNREACHABLE_A'] . ";\n";

echo _('SCHEDULED DOWNTIME') . ';'
    . $hostStats['MAINTENANCE_T'] . 's;'
    . $hostStats['MAINTENANCE_TP'] . "%;;;\n";

echo _('UNDETERMINED') . ';'
    . $hostStats['UNDETERMINED_T'] . 's;'
    . $hostStats['UNDETERMINED_TP'] . "%;\n";
echo "\n";
echo "\n";

echo _('Service') . ';'
    . _('OK') . ' %; ' . _('OK') . ' Alert;'
    . _('Warning') . ' %;' . _('Warning') . ' Alert;'
    . _('Critical') . ' %;' . _('Critical') . ' Alert;'
    . _('Unknown') . ' %;' . _('Unknown') . ' Alert;'
    . _('Scheduled Downtimes') . ' %;' . _('Undetermined') . "%;\n";

$hostServicesStats =  getLogInDbForHostSVC(
    $hostId,
    $startDate,
    $endDate,
    $reportingTimePeriod
);

foreach ($hostServicesStats as $tab) {
    if (isset($tab['DESCRIPTION']) && $tab['DESCRIPTION'] != '') {
        echo $tab['DESCRIPTION'] . ';'
            . $tab['OK_TP'] . ' %;'
            . $tab['OK_A'] . ';'
            . $tab['WARNING_TP'] . ' %;'
            . $tab['WARNING_A'] . ';'
            . $tab['CRITICAL_TP'] . ' %;'
            . $tab['CRITICAL_A'] . ';'
            . $tab['UNKNOWN_TP'] . '%;'
            . $tab['UNKNOWN_A'] . ';'
            . $tab['MAINTENANCE_TP'] . ' %;'
            . $tab['UNDETERMINED_TP'] . "%;\n";
    }
}
echo "\n";
echo "\n";

// Evolution of host availability in time
echo _('Day') . ';'
    . _('Duration') . ';'
    . _('Up') . ' (s);'
    . _('Up') . ' %;'
    . _('Up') . ' ' . _('Alert') . ';'
    . _('Down') . ' (s);'
    . _('Down') . ' %;'
    . _('Down') . ' ' . _('Alert') . ';'
    . _('Unreachable') . ' (s);'
    . _('Unreachable') . ' %;'
    . _('Unreachable') . ' ' . _('Alert')
    . _('Day') . ";\n";

$dbResult = $pearDBO->prepare(
    'SELECT  * FROM `log_archive_host` '
    . 'WHERE `host_id` = :hostId '
    . 'AND `date_start` >= :startDate '
    . 'AND `date_end` <= :endDate '
    . 'ORDER BY `date_start` desc'
);
$dbResult->bindValue(':hostId', $hostId, PDO::PARAM_INT);
$dbResult->bindValue(':startDate', $startDate, PDO::PARAM_INT);
$dbResult->bindValue(':endDate', $endDate, PDO::PARAM_INT);
$dbResult->execute();

while ($row = $dbResult->fetch()) {
    $duration = $row['UPTimeScheduled'] + $row['DOWNTimeScheduled'] + $row['UNREACHABLETimeScheduled'];
    if ($duration === 0) {
        continue;
    }
    // Percentage by status
    $row['UP_MP'] = round($row['UPTimeScheduled'] * 100 / $duration, 2);
    $row['DOWN_MP'] = round($row['DOWNTimeScheduled'] * 100 / $duration, 2);
    $row['UNREACHABLE_MP'] = round($row['UNREACHABLETimeScheduled'] * 100 / $duration, 2);
    echo $row['date_start'] . ';' . $duration . ';'
        . $row['UPTimeScheduled'] . ';'
        . $row['UP_MP'] . '%;'
        . $row['UPnbEvent'] . ';'
        . $row['DOWNTimeScheduled'] . ';'
        . $row['DOWN_MP'] . '%;'
        . $row['DOWNnbEvent'] . ';'
        . $row['UNREACHABLETimeScheduled'] . ';'
        . $row['UNREACHABLE_MP'] . '%;'
        . $row['UNREACHABLEnbEvent'] . ';'
        . date('Y-m-d H:i:s', $row['date_start']) . ";\n";
}
