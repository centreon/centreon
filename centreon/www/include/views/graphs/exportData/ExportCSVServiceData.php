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

function get_error($str)
{
    echo $str . '<br />';

    exit(0);
}

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
include_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
include_once _CENTREON_PATH_ . 'www/class/HtmlAnalyzer.php';

$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');

session_start();
session_write_close();

$sid = session_id();
if (isset($sid)) {
    $res = $pearDB->query("SELECT * FROM session WHERE session_id = '" . $sid . "'");
    if (! $session = $res->fetchRow()) {
        get_error('bad session id');
    }
} else {
    get_error('need session id !');
}

$index = filter_var(
    $_GET['index'] ?? $_POST['index'] ?? false,
    FILTER_VALIDATE_INT
);
$period = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_GET['period'] ?? $_POST['period'] ?? 'today'
);
$start = filter_var(
    $_GET['start'] ?? false,
    FILTER_VALIDATE_INT
);
$end = filter_var(
    $_GET['end'] ?? false,
    FILTER_VALIDATE_INT
);
$chartId = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_GET['chartId'] ?? null
);

if (! empty($chartId)) {
    if (preg_match('/([0-9]+)_([0-9]+)/', $chartId, $matches)) {
        // Should be allowed chartId matching int_int regexp
        $hostId = (int) $matches[1];
        $serviceId = (int) $matches[2];

        // Making sure that splitted values are positive.
        if ($hostId > 0 && $serviceId > 0) {
            $query = 'SELECT id'
                . ' FROM index_data'
                . ' WHERE host_id = :hostId'
                . ' AND service_id = :serviceId';

            $stmt = $pearDBO->prepare($query);
            $stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
            $stmt->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $index = $row['id'];
            }
        }
    } else {
        exit('Resource not found');
    }
}
if ($index !== false) {
    $stmt = $pearDBO->prepare(
        'SELECT host_name, service_description FROM index_data WHERE id = :index'
    );
    $stmt->bindValue(':index', $index, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hName = $row['host_name'];
        $sName = $row['service_description'];
    }

    header('Content-Type: application/csv-tab-delimited-table');
    if (isset($hName, $sName)) {
        header('Content-disposition: filename=' . $hName . '_' . $sName . '.csv');
    } else {
        header('Content-disposition: filename=' . $index . '.csv');
    }

    if ($start === false || $end === false) {
        exit('Start or end time is not consistent or not an integer');
    }

    $listMetric = [];
    $datas = [];
    $listEmptyMetric = [];

    $stmt = $pearDBO->prepare(
        'SELECT DISTINCT metric_id, metric_name '
        . 'FROM metrics, index_data '
        . 'WHERE metrics.index_id = index_data.id AND id = :index ORDER BY metric_name'
    );

    $stmt->bindValue(':index', $index, PDO::PARAM_INT);
    $stmt->execute();

    while ($indexData = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listMetric[$indexData['metric_id']] = $indexData['metric_name'];
        $listEmptyMetric[$indexData['metric_id']] = '';
        $stmt2 = $pearDBO->prepare(
            'SELECT ctime, `value` FROM data_bin WHERE id_metric = :metricId '
            . 'AND ctime >= :start AND ctime < :end'
        );
        $stmt2->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt2->bindValue(':end', $end, PDO::PARAM_INT);
        $stmt2->bindValue(':metricId', $indexData['metric_id'], PDO::PARAM_INT);
        $stmt2->execute();
        while ($data = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $datas[$data['ctime']][$indexData['metric_id']] = $data['value'];
        }
    }
}

// Order by timestamp
ksort($datas);
foreach ($datas as $key => $data) {
    $datas[$key] = $data + $listEmptyMetric;
    // Order by metric
    ksort($datas[$key]);
}

echo 'time;humantime';
if (count($listMetric)) {
    ksort($listMetric);
    echo ';' . implode(';', $listMetric);
}
echo "\n";

foreach ($datas as $ctime => $tab) {
    echo $ctime . ';' . date('Y-m-d H:i:s', $ctime);
    foreach ($tab as $metric_value) {
        if ($metric_value !== '') {
            printf(';%f', $metric_value);
        } else {
            echo ';';
        }
    }
    echo "\n";
}
