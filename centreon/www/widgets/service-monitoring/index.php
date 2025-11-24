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
require_once '../widget-error-handling.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';

CentreonSession::start(1);
if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
    exit();
}
$centreon = $_SESSION['centreon'];

$widgetId = filter_input(
    INPUT_GET,
    'widgetId',
    FILTER_VALIDATE_INT,
    ['options' => ['default' => 0]],
);

try {
    if ($widgetId <= 0) {
        throw new InvalidArgumentException('Widget ID must be a positive integer');
    }
    $configurationDatabase = $dependencyInjector['configuration_db'];
    $widgetObj = new CentreonWidget($centreon, $configurationDatabase);

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
    $theme = $variablesThemeCSS === 'Generic-theme'
        ? $variablesThemeCSS . '/Variables-css'
        : $variablesThemeCSS;
} catch (Exception $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error fetching data for service-monitoring widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

    exit;
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/service-monitoring/src/';
$template = SmartyBC::createSmartyTemplate($path, '/');

$template->assign('widgetId', $widgetId);
$template->assign('preferences', $preferences);
$template->assign('autoRefresh', $autoRefresh);

$bMoreViews = 0;
if ($preferences['more_views']) {
    $bMoreViews = $preferences['more_views'];
}
$template->assign('more_views', $bMoreViews);
$template->assign('theme', $variablesThemeCSS);

$template->display('index.ihtml');
