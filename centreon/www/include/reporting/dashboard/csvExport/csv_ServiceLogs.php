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

session_start();
session_write_close();

// DB connexion
$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');

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

// getting host and service id
$hostId = filter_var(
    $_GET['host'] ?? $_POST['host'] ?? null,
    FILTER_VALIDATE_INT
);

$serviceId = filter_var(
    $_GET['service'] ?? $_POST['service'] ?? null,
    FILTER_VALIDATE_INT
);

// finding the user's allowed resources
$services = $centreon->user->access->getHostServiceAclConf($hostId, 'broker', null);

// checking if the user has ACL rights for this resource
if (! $centreon->user->admin
    && $serviceId !== null
    && (! array_key_exists($serviceId, $services))
) {
    echo '<div align="center" style="color:red">'
        . '<b>You are not allowed to access this service</b></div>';

    exit();
}

// Getting time interval to report
$dates = getPeriodToReport();
$startDate =  htmlentities($_GET['start'], ENT_QUOTES, 'UTF-8');
$endDate =  htmlentities($_GET['end'], ENT_QUOTES, 'UTF-8');
$hostName = getHostNameFromId($hostId);
$serviceDescription = getServiceDescriptionFromId($serviceId);

// file type setting
header('Cache-Control: public');
header('Pragma: public');
header('Content-Type: application/octet-stream');
header('Content-disposition: attachment ; filename=' . $hostName . '_' . $serviceDescription . '.csv');

echo _('Host') . ';'
    . _('Service') . ';'
    . _('Begin date') . '; '
    . _('End date') . '; '
    . _('Duration') . "\n";

echo $hostName . '; '
    . $serviceDescription . '; '
    . date(_('d/m/Y H:i:s'), $startDate) . '; '
    . date(_('d/m/Y H:i:s'), $endDate) . '; '
    . ($endDate - $startDate) . "s\n";
echo "\n";

echo _('Status') . ';'
    . _('Time') . ';'
    . _('Total Time') . ';'
    . _('Mean Time') . '; '
    . _('Alert') . "\n";

$reportingTimePeriod = getreportingTimePeriod();
$servicesStats = getServicesLogs(
    [[
        'hostId' => $hostId,
        'serviceId' => $serviceId,
    ]],
    $startDate,
    $endDate,
    $reportingTimePeriod
);
$serviceStats = $servicesStats[$hostId][$serviceId];

echo 'OK;'
    . $serviceStats['OK_T'] . 's;'
    . $serviceStats['OK_TP'] . '%;'
    . $serviceStats['OK_MP'] . '%;'
    . $serviceStats['OK_A'] . ";\n";

echo 'WARNING;'
    . $serviceStats['WARNING_T'] . 's;'
    . $serviceStats['WARNING_TP'] . '%;'
    . $serviceStats['WARNING_MP'] . '%;'
    . $serviceStats['WARNING_A'] . ";\n";

echo 'CRITICAL;'
    . $serviceStats['CRITICAL_T'] . 's;'
    . $serviceStats['CRITICAL_TP'] . '%;'
    . $serviceStats['CRITICAL_MP'] . '%;'
    . $serviceStats['CRITICAL_A'] . ";\n";

echo 'UNKNOWN;'
    . $serviceStats['UNKNOWN_T'] . 's;'
    . $serviceStats['UNKNOWN_TP'] . '%;'
    . $serviceStats['UNKNOWN_MP'] . '%;'
    . $serviceStats['UNKNOWN_A'] . ";\n";

echo _('SCHEDULED DOWNTIME') . ';'
    . $serviceStats['MAINTENANCE_T'] . 's;'
    . $serviceStats['MAINTENANCE_TP'] . "%;;;\n";

echo 'UNDETERMINED;'
    . $serviceStats['UNDETERMINED_T'] . 's;'
    . $serviceStats['UNDETERMINED_TP'] . "%;;;\n";
echo "\n";
echo "\n";

// Getting evolution of service stats in time
echo _('Day') . ';'
    . _('Duration') . ';'
    . _('OK') . ' (s); '
    . _('OK') . ' %; '
    . _('OK') . ' Alert;'
    . _('Warning') . ' (s); '
    . _('Warning') . ' %;'
    . _('Warning') . ' Alert;'
    . _('Unknown') . ' (s); '
    . _('Unknown') . ' %;'
    . _('Unknown') . ' Alert;'
    . _('Critical') . ' (s); '
    . _('Critical') . ' %;'
    . _('Critical') . ' Alert;'
    . _('Day') . ";\n";

$dbResult = $pearDBO->prepare(
    'SELECT  * FROM `log_archive_service` '
    . 'WHERE `host_id` = :hostId '
    . 'AND `service_id` = :serviceId '
    . 'AND `date_start` >= :startDate '
    . 'AND `date_end` <= :endDate '
    . 'ORDER BY `date_start` DESC'
);
$dbResult->bindValue(':hostId', $hostId, PDO::PARAM_INT);
$dbResult->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
$dbResult->bindValue(':startDate', $startDate, PDO::PARAM_INT);
$dbResult->bindValue(':endDate', $endDate, PDO::PARAM_INT);
$dbResult->execute();

while ($row = $dbResult->fetch()) {
    $duration = $row['OKTimeScheduled']
        + $row['WARNINGTimeScheduled']
        + $row['UNKNOWNTimeScheduled']
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
        . $duration . ';'
        . $row['OKTimeScheduled'] . 's;'
        . $row['OK_MP'] . '%;'
        . $row['OKnbEvent'] . ';'
        . $row['WARNINGTimeScheduled'] . 's;'
        . $row['WARNING_MP'] . '%;'
        . $row['WARNINGnbEvent'] . ';'
        . $row['UNKNOWNTimeScheduled'] . 's;'
        . $row['UNKNOWN_MP'] . '%;'
        . $row['UNKNOWNnbEvent'] . ';'
        . $row['CRITICALTimeScheduled'] . 's;'
        . $row['CRITICAL_MP'] . '%;'
        . $row['CRITICALnbEvent'] . ';'
        . date('Y-m-d H:i:s', $row['date_start']) . ";\n";
}
$dbResult->closeCursor();
