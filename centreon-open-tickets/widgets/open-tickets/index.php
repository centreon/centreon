<?php

/*
 * Copyright 2015 - 2020 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

require_once "../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';

$smartyDir = __DIR__ . '/../../../vendor/smarty/smarty/';
require_once $smartyDir . 'libs/Smarty.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

$path = $centreon_path . 'www/widgets/open-tickets/src/templates/';
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "/", $centreon_path);

try {
    $db = new CentreonDB();
    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $autoRefresh = 0;
    if (isset($preferences['refresh_interval'])) {
        $autoRefresh = $preferences['refresh_interval'];
    }
    $preferences['rule'] = (!empty($preferences['rule']) ? $preferences['rule'] : null);
    $rule = new Centreon_OpenTickets_Rule($db);
    $result = $rule->getAliasAndProviderId($preferences['rule']);

    if (
        !isset($preferences['rule'])
        || is_null($preferences['rule'])
        || $preferences['rule'] == ''
        || !isset($result['provider_id'])
    ) {
        $template->assign(
            'error',
            "<center><div class='update' style='text-align:center;width:350px;'>" .
            _("Please select a rule first") . "</div></center>"
        );
    }
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => "Generic-theme",
        'dark' => "Centreon-Dark",
        default => throw new \Exception('Unknown user theme : ' . $centreon->user->theme),
    };
} catch (Exception $e) {
    $template->assign(
        'error',
        "<center><div class='update' style='text-align:center;width:350px;'>" .
        $e->getMessage() . "</div></center>"
    );
}

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
