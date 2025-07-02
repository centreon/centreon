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

if (! defined('SMARTY_DIR')) {
    define('SMARTY_DIR', realpath('../vendor/smarty/smarty/libs/') . '/');
}

// Bench
function microtime_float(): bool
{
    [$usec, $sec] = explode(' ', microtime());

    return (float) $usec + (float) $sec;
}

set_time_limit(60);
$time_start = microtime_float();

$advanced_search = 0;

// Include
include_once realpath(__DIR__ . '/../../../../bootstrap.php');

require_once "{$classdir}/centreonDB.class.php";
require_once "{$classdir}/centreonLang.class.php";
require_once "{$classdir}/centreonSession.class.php";
require_once "{$classdir}/centreon.class.php";
require_once "{$classdir}/centreonFeature.class.php";

/*
 * Create DB Connection
 *  - centreon
 *  - centstorage
 */
$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');

$centreonSession = new CentreonSession();

CentreonSession::start();

// Check session and drop all expired sessions
if (! $centreonSession->updateSession($pearDB)) {
    CentreonSession::stop();
}

$args = '&redirect=' . urlencode(http_build_query($_GET));

// check centreon session
// if session is not valid and autologin token is not given, then redirect to login page
if (! isset($_SESSION['centreon'])) {
    if (! isset($_GET['autologin'])) {
        include __DIR__ . '/../../../index.html';
    } else {
        $args = null;
        foreach ($_GET as $key => $value) {
            $args ? $args .= '&' . $key . '=' . $value : $args = $key . '=' . $value;
        }
        header('Location: index.php?' . $args . '');
    }
}

// Define Oreon var alias
if (isset($_SESSION['centreon'])) {
    $oreon = $_SESSION['centreon'];
    $centreon = $_SESSION['centreon'];
}
if (! isset($centreon) || ! is_object($centreon)) {
    exit();
}

// Init different elements we need in a lot of pages
unset($centreon->optGen);
$centreon->initOptGen($pearDB);

if (! $p) {
    $rootMenu = getFirstAllowedMenu($centreon->user->access->topologyStr, $centreon->user->default_page);

    if ($rootMenu && $rootMenu['topology_url'] && $rootMenu['is_react']) {
        header("Location: .{$rootMenu['topology_url']}");
    } elseif ($rootMenu) {
        $p = $rootMenu['topology_page'];
        $tab = preg_split("/\=/", $rootMenu['topology_url_opt']);

        if (isset($tab[1])) {
            $o = $tab[1];
        }
    }
}

// Cut Page ID
$level1 = null;
$level2 = null;
$level3 = null;
$level4 = null;
switch (strlen($p)) {
    case 1:
        $level1 = $p;
        break;
    case 3:
        $level1 = substr($p, 0, 1);
        $level2 = substr($p, 1, 2);
        $level3 = substr($p, 3, 2);
        break;
    case 5:
        $level1 = substr($p, 0, 1);
        $level2 = substr($p, 1, 2);
        $level3 = substr($p, 3, 2);
        break;
    case 6:
        $level1 = substr($p, 0, 2);
        $level2 = substr($p, 2, 2);
        $level3 = substr($p, 3, 2);
        break;
    case 7:
        $level1 = substr($p, 0, 1);
        $level2 = substr($p, 1, 2);
        $level3 = substr($p, 3, 2);
        $level4 = substr($p, 5, 2);
        break;
    default:
        $level1 = $p;
        break;
}

// Update Session Table For last_reload and current_page row
$page = '' . $level1 . $level2 . $level3 . $level4;
if (empty($page)) {
    $page = null;
}
$sessionStatement = $pearDB->prepare(
    'UPDATE `session`
    SET `current_page` = :currentPage
    WHERE `session_id` = :sessionId'
);
$sessionStatement->bindValue(':currentPage', $page, PDO::PARAM_INT);
$sessionStatement->bindValue(':sessionId', session_id(), PDO::PARAM_STR);
$sessionStatement->execute();

// Init Language
$centreonLang = new CentreonLang(_CENTREON_PATH_, $centreon);
$centreonLang->bindLang();
$centreonLang->bindLang('help');

$centreon->user->access->getActions();

/**
 * Initialize features flipping
 */
$centreonFeature = new CentreonFeature($pearDB);
