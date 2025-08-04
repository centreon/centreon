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

require_once '../require.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUser.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';

$pearDB = $dependencyInjector['configuration_db'];

CentreonSession::start(1);
if (! CentreonSession::checkSession(session_id(), $pearDB)) {
    echo 'Bad Session';

    exit();
}

if (! isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }

    global $pearDB;
    $pearDB = $dependencyInjector['configuration_db'];
    $dbAcl = $dependencyInjector['configuration_db'];
    $db = $dependencyInjector['configuration_db'];
    $db2 = $dependencyInjector['realtime_db'];

    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $autoRefresh = filter_var($preferences['refresh_interval'], FILTER_VALIDATE_INT);

    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = 30;
    }
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => 'Generic-theme',
        'dark' => 'Centreon-Dark',
        default => throw new Exception('Unknown user theme : ' . $centreon->user->theme),
    };
} catch (Exception $e) {
    echo $e->getMessage() . '<br/>';

    exit;
}

if ($centreon->user->admin == 0) {
    $access = new CentreonACL($centreon->user->get_id());
    $grouplist = $access->getAccessGroups();
    $grouplistStr = $access->getAccessGroupsString();
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/graph-monitoring/src/';
$template = SmartyBC::createSmartyTemplate($path, '/');

// Check ACL

$acl = 1;
if (isset($tab[0], $tab[1])   && $centreon->user->admin == 0) {
    $sql = <<<'SQL'
        SELECT
            1 AS REALTIME,
            host_id
        FROM centreon_acl
        WHERE host_id = :hostId
        AND service_id = :serviceId
        AND group_id IN (:groupList)
        SQL;

    $res = $dbAcl->prepare($sql);
    $res->bindValue(':hostId', $tab[0], PDO::PARAM_INT);
    $res->bindValue(':serviceId', $tab[1], PDO::PARAM_INT);
    $res->bindValue(':groupList', $grouplistStr, PDO::PARAM_STR);
    $res->execute();

    if (! $res->rowCount()) {
        $acl = 0;
    }
}

$servicePreferences = '';

if ($acl === 0) {
    $servicePreferences = '';
} elseif (false === isset($preferences['service']) || trim($preferences['service']) === '') {
    $servicePreferences = "<div class='update' style='text-align:center;margin-left: auto;margin-right: "
        . "auto;width:350px;'>" . _('Please select a resource first') . '</div>';
} elseif (false === isset($preferences['graph_period']) || trim($preferences['graph_period']) === '') {
    $servicePreferences = "<div class='update' style='text-align:center;margin-left: auto;margin-right: "
        . "auto;width:350px;'>" . _('Please select a graph period') . '</div>';
}
$template->assign(
    'theme',
    $variablesThemeCSS === 'Generic-theme' ? $variablesThemeCSS . '/Variables-css' : $variablesThemeCSS
);
$template->assign('widgetId', $widgetId);
$template->assign('preferences', $preferences);
$template->assign('interval', $preferences['graph_period']);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('graphId', str_replace('-', '_', $preferences['service']));
$template->assign('servicePreferences', $servicePreferences);

$template->display('index.ihtml');
