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
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'bootstrap.php';

session_start();
if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

$variablesThemeCSS = match ($centreon->user->theme) {
    'light' => 'Generic-theme',
    'dark' => 'Centreon-Dark',
    default => throw new Exception('Unknown user theme : ' . $centreon->user->theme),
};

$theme = $variablesThemeCSS === 'Generic-theme' ? $variablesThemeCSS . '/Variables-css' : $variablesThemeCSS;

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }
    $db = $dependencyInjector['configuration_db'];
    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $autoRefresh = filter_var($preferences['refresh_interval'], FILTER_VALIDATE_INT);
    $frameheight = filter_var($preferences['frameheight'], FILTER_VALIDATE_INT);
    $website = filter_var($preferences['website'], FILTER_VALIDATE_URL);

    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = 30;
    }

    if ($frameheight === false) {
        $frameheight = 900;
    }

    if ($website === false) {
        throw new Exception(_('The URL provided for the website does not use a valid URL pattern.'));
    }
} catch (Exception $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error while using httploader widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

    exit;
}

?>
<html>
    <style type="text/css">
        body{ margin:0; padding:0;}
        div#actionBar { position:absolute; top:0; left:0; width:100%; height:25px; background-color: #FFFFFF; }
        @media screen { body>div#actionBar { position: fixed; } }
        * html body { overflow:hidden; }
        * html div#hgMonitoringTable { height:100%; overflow:auto; }
    </style>
    <head>
        <title>Graph Monitoring</title>
        <link href="../../Themes/Generic-theme/style.css" rel="stylesheet" type="text/css"/>
        <link href="../../Themes/Generic-theme/jquery-ui/jquery-ui.css" rel="stylesheet" type="text/css"/>
        <link href="../../Themes/Generic-theme/jquery-ui/jquery-ui-centreon.css" rel="stylesheet" type="text/css"/>
        <link href="./Themes/<?php echo $variablesThemeCSS === 'Generic-theme' ? $variablesThemeCSS . '/Variables-css/'
            : $variablesThemeCSS . '/'; ?>variables.css" rel="stylesheet" type="text/css"
        />
        <script type="text/javascript" src="../../include/common/javascript/jquery/jquery.min.js"></script>
        <script type="text/javascript" src="../../include/common/javascript/jquery/jquery-ui.js"></script>
        <script type="text/javascript" src="../../include/common/javascript/widgetUtils.js"></script>
    </head>
    <body>
        <iframe id="webContainer" width="100%" height="900px"></iframe>
    </body>
    <script type="text/javascript">
        var widgetId = <?php echo $widgetId; ?>;
        var website = '<?php echo $preferences['website']; ?>';
        var frameheight = <?php echo $frameheight; ?>;
        var autoRefresh = <?php echo $autoRefresh; ?>;
        var timeout;

        function loadPage() {
            jQuery("#webContainer").attr('src', website);
            parent.iResize(window.name, frameheight);
            if (autoRefresh) {
                if (timeout) {
                    clearTimeout(timeout);
                }
                timeout = setTimeout(loadPage, (autoRefresh * 1000));
            }
        }
        jQuery(function() {
            loadPage();
        });
    </script>
</html>
