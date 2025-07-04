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

require_once __DIR__ . '/../../../../../bootstrap.php';
require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');

$path = _CENTREON_PATH_ . '/www';
require_once "{$path}/class/centreon.class.php";
require_once "{$path}/class/centreonSession.class.php";
require_once "{$path}/class/centreonDB.class.php";

$DB = new CentreonDB();

CentreonSession::start();
if (! CentreonSession::checkSession(session_id(), $DB)) {
    echo 'Bad Session ID';

    exit();
}

$centreon = $_SESSION['centreon'];

if (isset($_POST['url'])) {
    $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);
    if (! empty($url)) {
        if (isset($_POST['search'])) {
            $search = isset($_POST['search']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['search']) : null;
            $centreon->historySearchService[$url] = $search;
        }

        if (isset($_POST['search_host'])) {
            $searchHost = isset($_POST['seach_host'])
                ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['seach_host'])
                : null;
            $centreon->historySearch[$url] = $searchHost;
        }

        if (isset($_POST['search_output'])) {
            $searchOutput = isset($_POST['search_output'])
                ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['search_output'])
                : null;
            $centreon->historySearchOutput[$url] = $searchOutput;
        }

        $defaultLimit = $centreon->optGen['maxViewConfiguration'] >= 1
            ? (int) $centreon->optGen['maxViewConfiguration']
            : 30;
        if (isset($_POST['limit'])) {
            $limit = filter_input(
                INPUT_POST,
                'limit',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'default' => $defaultLimit]]
            );
            $centreon->historyLimit[$url] = $limit;
        }

        if (isset($_POST['page'])) {
            $page = filter_input(
                INPUT_POST,
                'page',
                FILTER_VALIDATE_INT
            );
            if (false !== $page) {
                $centreon->historyPage[$url] = $page;
            }
        }
    }
}
