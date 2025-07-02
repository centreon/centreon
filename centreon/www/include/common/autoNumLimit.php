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
    exit();
}

$limitNotInRequestParameter = ! isset($_POST['limit']) && ! isset($_GET['limit']);
$historyLimitNotDefault = isset($centreon->historyLimit[$url]) && $centreon->historyLimit[$url] !== 30;
$sessionLimitKey = "results_limit_{$url}";

// Setting the limit filter
if (isset($_POST['limit']) && $_POST['limit']) {
    $limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 20]]);
} elseif (isset($_GET['limit'])) {
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 20]]);
} elseif ($limitNotInRequestParameter && $historyLimitNotDefault) {
    $limit = $centreon->historyLimit[$url];
} elseif (isset($_SESSION[$sessionLimitKey])) {
    $limit = $_SESSION[$sessionLimitKey];
} else {
    if (($p >= 200 && $p < 300) || ($p >= 20000 && $p < 30000)) {
        $dbResult = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'maxViewMonitoring'");
    } else {
        $dbResult = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'maxViewConfiguration'");
    }
    $gopt = $dbResult->fetch();
    $limit = (int) $gopt['value'] ?: 30;
}

$_SESSION[$sessionLimitKey] = $limit;

// Setting the pagination filter
if (isset($_POST['num'], $_POST['search'])
    || (isset($centreon->historyLastUrl) && $centreon->historyLastUrl !== $url)
) {
    // Checking if the current page and the last displayed page are the same and resetting the filters
    $num = 0;
} elseif (isset($_REQUEST['num'])) {
    // Checking if a pagination filter has been sent in the http request
    $num = filter_var(
        $_GET['num'] ?? $_POST['num'] ?? 0,
        FILTER_VALIDATE_INT
    );
} else {
    // Resetting the pagination filter
    $num = $centreon->historyPage[$url] ?? 0;
}

// Cast limit and num to avoid sql error on prepared statement (PDO::PARAM_INT)
$limit = (int) $limit;
$num = (int) $num;

global $search;
