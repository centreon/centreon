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

// Getting hostgroup id
$hostgroupId = null;
if (! empty($_POST['hostgroup']) || ! empty($_GET['hostgroup'])) {
    $hostgroupId = filter_var(
        $_GET['hostgroup'] ?? $_POST['hostgroup'],
        FILTER_VALIDATE_INT
    );
}

if ($hostgroupId === false) {
    throw new InvalidArgumentException('Bad parameters');
}

// finding the user's allowed hostgroups
$allowedHostgroups = $centreon->user->access->getHostGroupAclConf(null, 'broker');

// checking if the user has ACL rights for this resource
if (! $centreon->user->admin
    && $hostgroupId !== null
    && ! array_key_exists($hostgroupId, $allowedHostgroups)
) {
    echo '<div align="center" style="color:red">'
        . '<b>You are not allowed to access this host group</b></div>';

    exit();
}

// Getting time interval to report
$dates = getPeriodToReport();

$startDate = null;
$endDate = null;

if (! empty($_GET['start'])) {
    $startDate = filter_var($_GET['start'], FILTER_VALIDATE_INT);
}

if (! empty($_GET['end'])) {
    $endDate = filter_var($_GET['end'], FILTER_VALIDATE_INT);
}

if ($startDate === false || $endDate === false) {
    throw new InvalidArgumentException('Bad parameters');
}

$hostgroupName = getHostgroupNameFromId($hostgroupId);

// file type setting
header('Cache-Control: public');
header('Pragma: public');
header('Content-Type: application/octet-stream');
header('Content-disposition: filename=' . $hostgroupName . '.csv');

echo _('Hostgroup') . ';'
    . _('Begin date') . '; '
    . _('End date') . '; '
    . _('Duration') . "\n";
echo $hostgroupName . '; '
    . date(_('d/m/Y H:i:s'), $startDate) . '; '
    . date(_('d/m/Y H:i:s'), $endDate) . '; '
    . ($endDate - $startDate) . "s\n";
echo "\n";
echo _('Status') . ';'
    . _('Total Time') . ';'
    . _('Mean Time') . '; '
    . _('Alert') . "\n";

// Getting stats on Host
$reportingTimePeriod = getreportingTimePeriod();
$hostgroupStats = [];
$hostgroupStats = getLogInDbForHostGroup($hostgroupId, $startDate, $endDate, $reportingTimePeriod);

echo _('UP') . ';'
    . $hostgroupStats['average']['UP_TP'] . '%;'
    . $hostgroupStats['average']['UP_MP'] . '%;'
    . $hostgroupStats['average']['UP_A'] . ";\n";
echo _('DOWN') . ';'
    . $hostgroupStats['average']['DOWN_TP'] . '%;'
    . $hostgroupStats['average']['DOWN_MP'] . '%;'
    . $hostgroupStats['average']['DOWN_A'] . ";\n";
echo _('UNREACHABLE') . ';'
    . $hostgroupStats['average']['UNREACHABLE_TP'] . '%;'
    . $hostgroupStats['average']['UNREACHABLE_MP'] . '%;'
    . $hostgroupStats['average']['UNREACHABLE_A'] . ";\n";
echo _('SCHEDULED DOWNTIME') . ';'
    . $hostgroupStats['average']['MAINTENANCE_TP'] . "%;\n";
echo _('UNDETERMINED') . ';'
    . $hostgroupStats['average']['UNDETERMINED_TP'] . "%;\n";
echo "\n\n";

echo _('Hosts') . ';'
    . _('Up') . ' %;'
    . _('Up Mean Time') . ' %;'
    . _('Up') . ' ' . _('Alert') . ';'
    . _('Down') . ' %;'
    . _('Down Mean Time') . ' %;'
    . _('Down') . ' ' . _('Alert') . ';'
    . _('Unreachable') . ' %;'
    . _('Unreachable Mean Time') . ' %;'
    . _('Unreachable') . ' ' . _('Alert') . ';'
    . _('Scheduled Downtimes') . ' %;'
    . _('Undetermined') . " %;\n";

foreach ($hostgroupStats as $key => $tab) {
    if ($key != 'average') {
        echo $tab['NAME'] . ';'
            . $tab['UP_TP'] . '%;'
            . $tab['UP_MP'] . '%;'
            . $tab['UP_A'] . ';'
            . $tab['DOWN_TP'] . '%;'
            . $tab['DOWN_MP'] . '%;'
            . $tab['DOWN_A'] . ';'
            . $tab['UNREACHABLE_TP'] . '%;'
            . $tab['UNREACHABLE_MP'] . '%;'
            . $tab['UNREACHABLE_A'] . ';'
            . $tab['MAINTENANCE_TP'] . '%;'
            . $tab['UNDETERMINED_TP'] . "%;\n";
    }
}
echo "\n";
echo "\n";

// getting all hosts from hostgroup
$str = '';
$dbResult = $pearDB->prepare(
    'SELECT host_host_id FROM `hostgroup_relation` '
    . 'WHERE `hostgroup_hg_id` = :hostgroupId'
);
$dbResult->bindValue(':hostgroupId', $hostgroupId, PDO::PARAM_INT);
$dbResult->execute();

while ($hg = $dbResult->fetch()) {
    if ($str != '') {
        $str .= ', ';
    }
    $str .= "'" . $hg['host_host_id'] . "'";
}
if ($str == '') {
    $str = "''";
}
unset($hg, $dbResult);

// Getting hostgroup stats evolution
$dbResult = $pearDBO->prepare(
    'SELECT `date_start`, `date_end`, sum(`UPnbEvent`) as UPnbEvent, sum(`DOWNnbEvent`) as DOWNnbEvent, '
    . 'sum(`UNREACHABLEnbEvent`) as UNREACHABLEnbEvent, '
    . 'avg( `UPTimeScheduled` ) as UPTimeScheduled, '
    . 'avg( `DOWNTimeScheduled` ) as DOWNTimeScheduled, '
    . 'avg( `UNREACHABLETimeScheduled` ) as UNREACHABLETimeScheduled '
    . 'FROM `log_archive_host` WHERE `host_id` IN (' . $str . ') '
    . 'AND `date_start` >= :startDate '
    . 'AND `date_end` <= :endDate '
    . 'GROUP BY `date_end`, `date_start` ORDER BY `date_start` desc'
);
$dbResult->bindValue(':startDate', $startDate, PDO::PARAM_INT);
$dbResult->bindValue(':endDate', $endDate, PDO::PARAM_INT);
$dbResult->execute();

echo _('Day') . ';'
    . _('Duration') . ';'
    . _('Up Mean Time') . ';'
    . _('Up Alert') . ';'
    . _('Down Mean Time') . ';'
    . _('Down Alert') . ';'
    . _('Unreachable Mean Time') . ';'
    . _('Unreachable Alert') . _('Day') . ";\n";

while ($row = $dbResult->fetch()) {
    $duration = $row['UPTimeScheduled'] + $row['DOWNTimeScheduled'] + $row['UNREACHABLETimeScheduled'];
    if ($duration === 0) {
        continue;
    }
    // Percentage by status
    $row['UP_MP'] = round($row['UPTimeScheduled'] * 100 / $duration, 2);
    $row['DOWN_MP'] = round($row['DOWNTimeScheduled'] * 100 / $duration, 2);
    $row['UNREACHABLE_MP'] = round($row['UNREACHABLETimeScheduled'] * 100 / $duration, 2);

    echo $row['date_start'] . ';'
        . $duration . 's;'
        . $row['UP_MP'] . '%;'
        . $row['UPnbEvent'] . ';'
        . $row['DOWN_MP'] . '%;'
        . $row['DOWNnbEvent'] . ';'
        . $row['UNREACHABLE_MP'] . '%;'
        . $row['UNREACHABLEnbEvent'] . ';'
        . date('Y-m-d H:i:s', $row['date_start']) . ";\n";
}
$dbResult->closeCursor();
