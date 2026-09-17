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

require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonCustomView.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonWidget.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'bootstrap.php';

session_start();
session_write_close();

try {
    if (! isset($_SESSION['centreon'])) {
        throw new Exception('No session found');
    }
    $centreon = $_SESSION['centreon'];
    $db = new CentreonDB();
    $locale = $centreon->user->get_lang();
    putenv("LANG={$locale}");
    setlocale(LC_ALL, $locale);
    bindtextdomain('messages', _CENTREON_PATH_ . 'www/locale/');
    bind_textdomain_codeset('messages', 'UTF-8');
    textdomain('messages');

    if (CentreonSession::checkSession(session_id(), $db) === false) {
        throw new Exception('Invalid session');
    }
    $viewObj = new CentreonCustomView($centreon, $db);
    $widgetObj = new CentreonWidget($centreon, $db);

    // Smarty template initialization
    $path = _CENTREON_PATH_ . 'www/include/home/customViews/layouts/';
    $template = SmartyBC::createSmartyTemplate($path, './');

    $viewId = $viewObj->getCurrentView();
    $permission = $viewObj->checkPermission($viewId) ? 1 : 0;
    $ownership = $viewObj->checkOwnership($viewId) ? 1 : 0;
    $widgets = [];
    $columnClass = 'column_0';
    $widgetNumber = 0;
    if ($viewId) {
        $columnClass = $viewObj->getLayout($viewId);
        $widgets = $widgetObj->getWidgetsFromViewId($viewId);
        foreach ($widgets as $widgetId => $val) {
            if (isset($widgets[$widgetId]['widget_order']) && $widgets[$widgetId]['widget_order']) {
                $tmp = explode('_', $widgets[$widgetId]['widget_order']);
                $widgets[$widgetId]['column'] = $tmp[0];
            } else {
                $widgets[$widgetId]['column'] = 0;
            }
            if (! $permission && $widgets[$widgetId]['title'] === '') {
                $widgets[$widgetId]['title'] = '&nbsp;';
            }
            $widgetNumber++;
        }
        $template->assign('columnClass', $columnClass);
        $template->assign('jsonWidgets', json_encode($widgets));
        $template->assign('widgets', $widgets);
    }
    $template->assign('permission', $permission);
    $template->assign('widgetNumber', $widgetNumber);
    $template->assign('ownership', $ownership);
    $template->assign('userId', $centreon->user->user_id);
    $template->assign('view_id', $viewId);
    $template->assign(
        'error_msg',
        _('No widget configured in this view. Please add a new widget with the "Add widget" button.')
    );
    $template->assign(
        'helpIcon',
        returnSvg('www/img/icons/question_2.svg', 'var(--help-tool-tip-icon-fill-color)', 18, 18)
    );
    $template->display($columnClass . '.ihtml');
} catch (CentreonWidgetException|CentreonCustomViewException|Exception $e) {
    echo $e->getMessage() . '<br/>';
}
