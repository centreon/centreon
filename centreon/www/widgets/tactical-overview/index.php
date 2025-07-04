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
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'bootstrap.php';

CentreonSession::start(1);

if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

try {
    $db_centreon = $dependencyInjector['configuration_db'];
    $db = $dependencyInjector['realtime_db'];

    if ($centreon->user->admin == 0) {
        $access = new CentreonACL($centreon->user->get_id());
        $grouplist = $access->getAccessGroups();
        $grouplistStr = $access->getAccessGroupsString();
    }

    $widgetObj = new CentreonWidget($centreon, $db_centreon);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
    $autoRefresh = (isset($preferences['refresh_interval']) && (int) $preferences['refresh_interval'] > 0)
        ? (int) $preferences['refresh_interval']
        : 30;
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => 'Generic-theme',
        'dark' => 'Centreon-Dark',
        default => throw new Exception('Unknown user theme : ' . $centreon->user->theme),
    };
} catch (Exception $e) {
    echo $e->getMessage() . '<br/>';

    exit;
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/tactical-overview/src/';
$template = SmartyBC::createSmartyTemplate($path, './');

$template->assign(
    'theme',
    $variablesThemeCSS === 'Generic-theme'
        ? $variablesThemeCSS . '/Variables-css'
        : $variablesThemeCSS
);

$kernel = App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    Centreon\Application\Controller\MonitoringResourceController::class
);

$buildParameter = function (string $id, string $name) {
    return [
        'id' => $id,
        'name' => $name,
    ];
};

if (
    isset($preferences['object_type'])
    && ($preferences['object_type'] === 'hosts' || $preferences['object_type'] == '')
) {
    require_once 'src/hosts_status.php';
} elseif (isset($preferences['object_type']) && $preferences['object_type'] === 'services') {
    require_once 'src/services_status.php';
}
