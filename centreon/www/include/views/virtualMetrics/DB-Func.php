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

/**
 * Validates the RPN function syntax by building a minimal rrdtool command
 * and executing it. Works for both CDEF (def_type=0) and VDEF (def_type=1).
 *
 * @param array<string,mixed> $fields Form fields
 *
 * @return true|array<string,string>
 */
function testRpnSyntaxWithRrdtool(array $fields): array|true
{
    global $pearDBO, $oreon;

    if (! isset($fields['def_type'])) {
        return true;
    }

    $rpnFunction = trim($fields['rpn_function'] ?? '');
    if ($rpnFunction === '') {
        return true;
    }

    $defType = (int) $fields['def_type'] === 1 ? 'VDEF' : 'CDEF';
    $hostServiceId = $fields['host_id'] ?? '';

    $indexId = getIndexIdFromHostServiceId($pearDBO, $hostServiceId);
    if ($indexId === null) {
        return true;
    }

    $config = $pearDBO->fetchAssociative('SELECT RRDdatabase_path FROM config LIMIT 1');
    $rrdPath = rtrim($config['RRDdatabase_path'] ?? '/var/lib/centreon/metrics', '/') . '/';

    $metricsStmt = $pearDBO->prepare(
        'SELECT metric_id, metric_name FROM metrics WHERE index_id = :index_id'
    );
    $metricsStmt->bindValue(':index_id', (int) $indexId, PDO::PARAM_INT);
    $metricsStmt->execute();

    $metrics = [];
    while ($row = $metricsStmt->fetch(PDO::FETCH_ASSOC)) {
        $metrics[$row['metric_name']] = $row['metric_id'];
    }

    if ($metrics === []) {
        return true;
    }

    $rpnParts = explode(',', $rpnFunction);
    $defArgs = [];
    $usedMetrics = [];

    foreach ($rpnParts as &$part) {
        $trimmed = trim($part);
        if (isset($metrics[$trimmed]) && ! isset($usedMetrics[$trimmed])) {
            $metricId = $metrics[$trimmed];
            $rrdFile = $rrdPath . $metricId . '.rrd';
            if (file_exists($rrdFile)) {
                $defArgs[] = 'DEF:v' . $metricId . '=' . $rrdFile . ':value:AVERAGE';
                $usedMetrics[$trimmed] = 'v' . $metricId;
            }
        }
        if (isset($usedMetrics[$trimmed])) {
            $part = $usedMetrics[$trimmed];
        }
    }
    unset($part);

    $rpnResolved = implode(',', $rpnParts);

    if ($defType === 'VDEF' && $defArgs === []) {
        return true;
    }

    $rrdtoolBin = $oreon->optGen['rrdtool_path_bin'] ?? '/usr/bin/rrdtool';
    $cmd = escapeshellarg($rrdtoolBin) . ' graph /dev/null --start now-1h';
    foreach ($defArgs as $def) {
        $cmd .= ' ' . escapeshellarg($def);
    }
    $cmd .= ' ' . escapeshellarg($defType . ':vtest=' . $rpnResolved);
    $cmd .= ' 2>&1';

    exec($cmd, $output, $rc);

    if ($rc !== 0) {
        $lastLine = end($output) ?: 'unknown error';
        $rrdtoolError = preg_replace('/^ERROR:\s*/', '', $lastLine);

        return ['rpn_function' => 'Invalid RPN syntax (RRDtool: ' . $rrdtoolError . ')'];
    }

    return true;
}

function _TestRPNInfinityLoop()
{
    global $form;
    $gsvs = null;
    if (isset($form)) {
        $gsvs = $form->getSubmitValues();
    }

    return ! (
        $gsvs['vmetric_name'] != null
        && preg_match('/' . $gsvs['vmetric_name'] . '/i', $gsvs['rpn_function'])
    );

}

/**
 * Indicates if a virtual metric name has already been used
 *
 * @global CentreonDB $pearDB
 * @global CentreonDB $pearDBO
 * @global HTML_QuickFormCustom $form
 * @param string $vmetricName
 * @param int $indexId
 * @return bool Return false if the virtual metric name has already been used
 */
function hasVirtualNameNeverUsed($vmetricName = null, $indexId = null)
{
    global $pearDB, $pearDBO, $form;
    $gsvs = null;
    if (isset($form)) {
        $gsvs = $form->getSubmitValues();
    }
    if (is_null($vmetricName) && isset($gsvs['vmetric_name'])) {
        $vmetricName = htmlentities($gsvs['vmetric_name'], ENT_QUOTES, 'UTF-8');
    }
    if (is_null($indexId) && isset($gsvs['index_id'])) {
        $indexId = $gsvs['index_id'];
    }

    $prepareVirtualM = $pearDB->prepare(
        'SELECT vmetric_id FROM virtual_metrics WHERE '
        . 'vmetric_name = :metric_name AND index_id = :index_id'
    );

    $prepareVirtualM->bindValue(':metric_name', $vmetricName, PDO::PARAM_STR);
    $prepareVirtualM->bindValue(':index_id', $indexId, PDO::PARAM_INT);

    try {
        $prepareVirtualM->execute();
    } catch (PDOException $e) {
        echo 'DB Error : ' . $e->getMessage();
    }

    $vmetric = $prepareVirtualM->fetch();
    $numberOfVirtualMetric = $prepareVirtualM->rowCount();
    $prepareVirtualM->closeCursor();

    $prepareMetric = $pearDBO->prepare(
        'SELECT metric_id FROM metrics WHERE '
        . 'metric_name = :metric_name AND index_id = :index_id'
    );

    $prepareMetric->bindValue(':metric_name', $vmetricName, PDO::PARAM_STR);
    $prepareMetric->bindValue(':index_id', $indexId, PDO::PARAM_INT);

    try {
        $prepareMetric->execute();
    } catch (PDOException $e) {
        echo 'DB Error : ' . $e->getMessage();
    }

    $metric = $prepareMetric->fetch();
    $numberOfVirtualMetric += $prepareMetric->rowCount();
    $prepareMetric->closeCursor();

    return ! (
        ($numberOfVirtualMetric >= 1
        && $vmetric['vmetric_id'] != $gsvs['vmetric_id'])
        || isset($metric['metric_id'])
    );

}

/**
 * Delete a list of virtual metric
 *
 * @global CentreonDB $pearDB
 * @param int[] $vmetrics List of virtual metric id to delete
 */
function deleteVirtualMetricInDB($vmetrics = [])
{
    global $pearDB;
    $prepareStatement = $pearDB->prepare(
        'DELETE FROM virtual_metrics WHERE vmetric_id = :vmetric_id'
    );
    foreach (array_keys($vmetrics) as $vmetricId) {
        try {
            $prepareStatement->bindValue(':vmetric_id', $vmetricId, PDO::PARAM_INT);
            $prepareStatement->execute();
        } catch (PDOException $e) {
            echo 'DB Error : ' . $e->getMessage();
        }
    }
}

/**
 * Duplicates a list of virtual metric
 *
 * @global CentreonDB $pearDB
 * @param int[] $vmetrics List of virtual metric id to duplicate
 * @param int[] $nbrDup Number of copy
 */
function multipleVirtualMetricInDB($vmetrics = [], $nbrDup = [])
{
    global $pearDB;
    $selectStmt = $pearDB->prepare(
        'SELECT * FROM virtual_metrics WHERE vmetric_id = :vmetric_id LIMIT 1'
    );

    foreach (array_keys($vmetrics) as $vmetricId) {
        $selectStmt->bindValue(':vmetric_id', $vmetricId, PDO::PARAM_INT);

        try {
            $selectStmt->execute();
        } catch (PDOException $e) {
            echo 'DB Error : ' . $e->getMessage();
        }

        $vmConfiguration = $selectStmt->fetch();
        unset($vmConfiguration['vmetric_id']);

        $columns = array_keys($vmConfiguration);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO virtual_metrics (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $originalVmetricName = $vmConfiguration['vmetric_name'];
        $indexId = (int) $vmConfiguration['index_id'];

        for ($newIndex = 1; $newIndex <= $nbrDup[$vmetricId]; $newIndex++) {
            $count = 1;
            $virtualMetricName = $originalVmetricName . '_' . $count;
            while (! hasVirtualNameNeverUsed($virtualMetricName, $indexId)) {
                $count++;
                $virtualMetricName = $originalVmetricName . '_' . $count;
            }
            $vmConfiguration['vmetric_name'] = $virtualMetricName;

            foreach ($columns as $col) {
                $value = $vmConfiguration[$col];
                $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }

            try {
                $insertStmt->execute();
            } catch (PDOException $e) {
                echo 'DB Error : ' . $e->getMessage();
            }
        }
    }
}

function updateVirtualMetricInDB($vmetric_id = null)
{
    if (! $vmetric_id) {
        return;
    }
    updateVirtualMetric($vmetric_id);
}

function insertVirtualMetricInDB()
{
    return insertVirtualMetric();
}

/**
 * Insert a virtual metric
 *
 * @global HTML_QuickFormCustom $form
 * @global CentreonDB $pearDB
 * @global CentreonDB $pearDBO
 * @return int New virtual metric id
 */
function insertVirtualMetric()
{
    global $form, $pearDB, $pearDBO;

    $ret = $form->getSubmitValues();

    $indexId = isset($ret['host_id'])
        ? getIndexIdFromHostServiceId($pearDBO, $ret['host_id'])
        : null;

    $insertStatement = $pearDB->prepare(
        'INSERT INTO `virtual_metrics`
          (`index_id`, `vmetric_name`, `def_type` , `rpn_function`,
          `unit_name` , `warn`, `crit`, `hidden` , `comment` , `vmetric_activate`, `ck_state`)
        VALUES
          (:index_id, :vmetric_name, :def_type, :rpn_function,
          :unit_name , :warn, :crit, :hidden, :comment, NULL, NULL)'
    );

    $insertStatement->bindValue(
        ':index_id',
        $indexId,
        PDO::PARAM_INT
    );

    $insertStatement->bindValue(
        ':vmetric_name',
        isset($ret['vmetric_name'])
            ? htmlentities($ret['vmetric_name'], ENT_QUOTES, 'UTF-8')
            : null,
        PDO::PARAM_STR
    );

    $insertStatement->bindValue(
        ':def_type',
        $ret['def_type'] ?? null,
        PDO::PARAM_STR
    );

    $insertStatement->bindValue(
        ':rpn_function',
        $ret['rpn_function'] ?? null,
        PDO::PARAM_STR
    );

    $insertStatement->bindValue(
        ':unit_name',
        $ret['unit_name'] ?? null,
        PDO::PARAM_STR
    );

    $insertStatement->bindValue(
        ':warn',
        array_key_exists('warn', $ret) && is_numeric($ret['warn']) ? $ret['warn'] : null,
        PDO::PARAM_INT
    );

    $insertStatement->bindValue(
        ':crit',
        array_key_exists('crit', $ret) && is_numeric($ret['crit']) ? $ret['crit'] : null,
        PDO::PARAM_INT
    );

    $insertStatement->bindValue(
        ':hidden',
        $ret['vhidden'] ?? null,
        PDO::PARAM_STR
    );

    $insertStatement->bindValue(
        ':comment',
        isset($ret['comment'])
            ? htmlentities($ret['comment'], ENT_QUOTES, 'UTF-8')
            : null,
        PDO::PARAM_STR
    );

    $insertStatement->execute();

    $dbResult = $pearDB->query('SELECT MAX(vmetric_id) FROM virtual_metrics');
    $vmetricId = $dbResult->fetch();

    return $vmetricId['MAX(vmetric_id)'];
}

/**
 * Update a virtual metric
 *
 * @params int|null $vmetricId
 * @global HTML_QuickFormCustom $form
 * @global CentreonDB $pearDB
 * @global CentreonDB $pearDBO
 * @param null|mixed $vmetricId
 */
function updateVirtualMetric($vmetricId = null)
{
    if ($vmetricId === null) {
        return;
    }

    global $form, $pearDB, $pearDBO;

    $ret = $form->getSubmitValues();

    $indexId = isset($ret['host_id'])
        ? getIndexIdFromHostServiceId($pearDBO, $ret['host_id'])
        : null;

    $updateStatement = $pearDB->prepare(
        'UPDATE `virtual_metrics` SET
          `index_id` = :index_id,
          `vmetric_name` = :vmetric_name,
          `def_type` = :def_type,
          `rpn_function` = :rpn_function,
          `unit_name` = :unit_name,
          `warn` = :warn,
          `crit` = :crit,
          `hidden` = :hidden,
          `comment` = :comment,
          `vmetric_activate` = NULL,
          `ck_state` = NULL
        WHERE vmetric_id = :vmetric_id'
    );

    $updateStatement->bindValue(
        ':index_id',
        $indexId,
        PDO::PARAM_INT
    );

    $updateStatement->bindValue(
        ':vmetric_name',
        isset($ret['vmetric_name'])
            ? htmlentities($ret['vmetric_name'], ENT_QUOTES, 'UTF-8')
            : null,
        PDO::PARAM_STR
    );

    $updateStatement->bindValue(
        ':def_type',
        $ret['def_type'] ?? null,
        PDO::PARAM_STR
    );

    $updateStatement->bindValue(
        ':rpn_function',
        $ret['rpn_function'] ?? null,
        PDO::PARAM_STR
    );

    $updateStatement->bindValue(
        ':unit_name',
        $ret['unit_name'] ?? null,
        PDO::PARAM_STR
    );

    $updateStatement->bindValue(
        ':warn',
        array_key_exists('warn', $ret) && is_numeric($ret['warn']) ? $ret['warn'] : null,
        PDO::PARAM_INT
    );

    $updateStatement->bindValue(
        ':crit',
        array_key_exists('crit', $ret) && is_numeric($ret['crit']) ? $ret['crit'] : null,
        PDO::PARAM_INT
    );

    $updateStatement->bindValue(
        ':hidden',
        $ret['vhidden'] ?? null,
        PDO::PARAM_STR
    );

    $updateStatement->bindValue(
        ':comment',
        isset($ret['comment'])
            ? htmlentities($ret['comment'], ENT_QUOTES, 'UTF-8')
            : null,
        PDO::PARAM_STR
    );

    $updateStatement->bindValue(
        ':vmetric_id',
        $vmetricId,
        PDO::PARAM_INT
    );

    $updateStatement->execute();

    if (! enableVirtualMetricInDB($vmetricId)) {
        disableVirtualMetricInDB($vmetricId, 1);
    }
}

/**
 * get index id from host and service id
 *
 * @param CentreonDB $dbMonitoring
 * @param string $hostServiceId
 * @return int|null
 */
function getIndexIdFromHostServiceId(CentreonDB $dbMonitoring, string $hostServiceId): ?int
{
    $indexId = null;

    if (preg_match('/\d+\-\d+/', $hostServiceId)) {
        // Get index_id
        [$hostId, $serviceId] = explode('-', $hostServiceId);

        $prepare = $dbMonitoring->prepare(
            'SELECT id FROM index_data
            WHERE host_id = :host_id
            AND service_id = :service_id'
        );
        $prepare->bindValue(':host_id', $hostId, PDO::PARAM_INT);
        $prepare->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
        $prepare->execute();

        if ($result = $prepare->fetch(PDO::FETCH_ASSOC)) {
            $indexId = $result['id'];
        }
    }

    return $indexId;
}

function disableVirtualMetricInDB($vmetric_id = null, $force = 0)
{
    if (! $vmetric_id) {
        return 0;
    }
    global $pearDB;

    $v_dis = disableVirtualMetric($vmetric_id, $force);
    if (! count($v_dis)) {
        return 0;
    }
    $statement = $pearDB->prepare(
        "UPDATE `virtual_metrics` SET `vmetric_activate` = '0' WHERE `vmetric_id` = :vmetric_id"
    );
    foreach ($v_dis as $vm) {
        $statement->bindValue(':vmetric_id', (int) $vm, PDO::PARAM_INT);
        $statement->execute();
    }

    return 1;
}

function &disableVirtualMetric($v_id = null, $force = 0)
{
    global $pearDB;
    $v_dis = [];

    $repA = ['*', '+', '-', '?', '^', '$'];
    $repB = ['\\\\*', '\\\\+', '\\\\-', '\\\\?', '\\\\^', '\\\$'];
    $l_where = ($force == 0) ? " AND `vmetric_activate` = '1'" : '';
    $statement = $pearDB->prepare(
        "SELECT index_id, vmetric_name FROM `virtual_metrics` WHERE `vmetric_id`=:vmetric_id{$l_where}"
    );
    $statement->bindValue(':vmetric_id', (int) $v_id, PDO::PARAM_INT);
    $statement->execute();
    if ($statement->rowCount() == 1) {
        $vmetric = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        $query = "SELECT vmetric_id FROM `virtual_metrics` WHERE `index_id`= :index_id AND `vmetric_activate` = '1' "
            . 'AND `rpn_function` REGEXP :rpn_function';
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':index_id', (int) $vmetric['index_id'], PDO::PARAM_INT);
        $statement->bindValue(
            ':rpn_function',
            '(^|,)' . str_replace($repA, $repB, $vmetric['vmetric_name']) . '(,|$)',
            PDO::PARAM_STR
        );
        $statement->execute();
        while ($d_vmetric = $statement->fetch(PDO::FETCH_ASSOC)) {
            $lv_dis = disableVirtualMetric($d_vmetric['vmetric_id']);
            if (is_array($lv_dis)) {
                foreach ($lv_dis as $pkey => $vm) {
                    $v_dis[] = $vm;
                }
            }
        }
        $statement->closeCursor();
        if (! $force) {
            $v_dis[] = $v_id;
        }
    }

    return $v_dis;
}

function enableVirtualMetricInDB($vmetric_id = null)
{
    if (! $vmetric_id) {
        return 0;
    }

    global $pearDB;

    $v_ena = enableVirtualMetric($vmetric_id);
    if (! count($v_ena)) {
        return 0;
    }
    $statement = $pearDB->prepare(
        "UPDATE `virtual_metrics` SET `vmetric_activate` = '1' WHERE `vmetric_id` = :vmetric_id"
    );
    foreach ($v_ena as $v_id) {
        [$rc, $output] = checkRRDGraphData($v_id);
        if ($rc) {
            $error = preg_replace('/^ERROR:\s*/', '', $output);

            throw new Exception("Wrong RPN syntax (RRDtool said: {$error})");
        }
        $statement->bindValue(':vmetric_id', (int) $v_id, PDO::PARAM_INT);
        $statement->execute();
    }

    return 1;
}

function enableVirtualMetric($v_id, $v_name = null, $index_id = null)
{
    global $pearDB;
    $v_ena = [];

    $l_where = 'vmetric_id = :vmetric_id';
    if (is_null($v_id)) {
        $l_where = 'vmetric_name = :vmetric_name AND index_id = :index_id';
    }

    $query = 'SELECT vmetric_id, index_id, rpn_function FROM virtual_metrics '
        . "WHERE {$l_where} AND (vmetric_activate = '0' OR vmetric_activate IS NULL);";
    $statement = $pearDB->prepare($query);
    if (is_null($v_id)) {
        $statement->bindValue(':vmetric_name', $v_name, PDO::PARAM_STR);
        $statement->bindValue(':index_id', (int) $index_id, PDO::PARAM_INT);
    } else {
        $statement->bindValue(':vmetric_id', (int) $v_id, PDO::PARAM_INT);
    }
    $statement->execute();
    if ($statement->rowCount() == 1) {
        $p_vmetric = $statement->fetch(PDO::FETCH_ASSOC);
        $l_mlist = preg_split("/\,/", $p_vmetric['rpn_function']);
        foreach ($l_mlist as $l_mnane) {
            $lv_ena = enableVirtualMetric(null, $l_mnane, $p_vmetric['index_id']);
            if (is_array($lv_ena)) {
                foreach ($lv_ena as $pkey => $vm) {
                    $v_ena[] = $vm;
                }
            }
        }
        $v_ena[] = $p_vmetric['vmetric_id'];
    }
    $statement->closeCursor();

    return $v_ena;
}

function checkRRDGraphData($v_id = null, $force = 0)
{
    global $pearDB, $oreon;
    if (! isset($v_id)) {

    }

    // Check if already Valid
    $query = 'SELECT vmetric_id, def_type FROM virtual_metrics '
        . "WHERE vmetric_id = :vmetric_id AND ( ck_state <> '1' OR ck_state IS NULL );";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':vmetric_id', (int) $v_id, PDO::PARAM_INT);
    $statement->execute();
    if ($statement->rowCount() == 1) {
        /**
         * Create XML Request Objects
         */
        $centreon = &$_SESSION['centreon'];
        $obj = new CentreonGraph($centreon->user->get_id(), null, 0, 1);

        /**
         * We check only one curve
         **/
        $obj->onecurve = true;
        $obj->checkcurve = true;

        $obj->init();
        /**
         * Init Curve list
         */
        $obj->setMetricList("v{$v_id}");
        $obj->initCurveList();

        /**
         * Create Legend
         */
        $obj->createLegend();

        /**
         * Display Images Binary Data
         */
        $lastline = exec($oreon->optGen['rrdtool_path_bin'] . $obj->displayImageFlow() . ' 2>&1', $result, $rc);
        $ckstate = (! $rc) ? '1' : '2';
        $statement = $pearDB->prepare(
            'UPDATE `virtual_metrics` SET `ck_state` = :ck_state WHERE `vmetric_id` = :vmetric_id'
        );
        $statement->bindValue(':ck_state', $ckstate, PDO::PARAM_STR);
        $statement->bindValue(':vmetric_id', (int) $v_id, PDO::PARAM_INT);
        $statement->execute();

        return [$rc, $lastline];
    }

    return null;
}
