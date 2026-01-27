<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
require_once '../widget-error-handling.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonAclLazy.class.php';
require_once $centreon_path . 'bootstrap.php';

const OBJECT_TYPE_HOST = 'hosts';
const OBJECT_TYPE_SERVICE = 'services';

CentreonSession::start(1);

if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

try {
    $configurationDatabase = $dependencyInjector['configuration_db'];

    $widgetObj = new CentreonWidget($centreon, $configurationDatabase);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $objectType = ! empty($preferences['object_type']) ? $preferences['object_type'] : OBJECT_TYPE_HOST;

    if (! in_array($objectType, [OBJECT_TYPE_HOST, OBJECT_TYPE_SERVICE])) {
        throw new InvalidArgumentException('Invalid object type provided in tactical-overview. Accepted: hosts or services');
    }

    $autoRefresh = (isset($preferences['refresh_interval']) && (int) $preferences['refresh_interval'] > 0)
        ? (int) $preferences['refresh_interval']
        : 30;
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => 'Generic-theme',
        'dark' => 'Centreon-Dark',
        default => throw new Exception('Unknown user theme : ' . $centreon->user->theme),
    };

    $theme = $variablesThemeCSS === 'Generic-theme'
        ? $variablesThemeCSS . '/Variables-css'
        : $variablesThemeCSS;
} catch (Exception $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error fetching data for tactical-overview widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

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

$objectType === OBJECT_TYPE_HOST ? require_once 'src/hosts_status.php' : require_once 'src/services_status.php';
