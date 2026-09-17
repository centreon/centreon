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

CentreonSession::start(1);
if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }

    $db = $dependencyInjector['configuration_db'];
    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $autoRefresh = filter_var($preferences['refresh_interval'], FILTER_VALIDATE_INT);
    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = 30;
    }
    $broker = 'broker';
    $res = $db->query("SELECT `value` FROM `options` WHERE `key` = 'broker'");
    if ($res->rowCount()) {
        $row = $res->fetchRow();
        $broker = strtolower($row['value']);
    } else {
        throw new Exception('Unknown broker module');
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
?>
<HTML>
    <HEAD>
        <TITLE></TITLE>

        <link href="../../Themes/Generic-theme/style.css" rel="stylesheet" type="text/css"/>
        <link href="../../Themes/Generic-theme/jquery-ui/jquery-ui.css" rel="stylesheet" type="text/css"/>
        <link href="../../Themes/Generic-theme/jquery-ui/jquery-ui-centreon.css" rel="stylesheet" type="text/css"/>
        <link
                href="../../Themes/<?php
                echo $variablesThemeCSS === 'Generic-theme' ? $variablesThemeCSS . '/Variables-css/'
                    : $variablesThemeCSS . '/'; ?>variables.css"
                rel="stylesheet"
                type="text/css"
        />
        <script type="text/javascript" src="../../include/common/javascript/jquery/jquery.min.js"></script>
        <script type="text/javascript" src="../../include/common/javascript/jquery/jquery-ui.js"></script>
        <script type="text/javascript"
                src="../../include/common/javascript/jquery/plugins/pagination/jquery.pagination.js"></script>
        <script type="text/javascript" src="../../include/common/javascript/widgetUtils.js"></script>
        <script type="text/javascript"
                src="../../include/common/javascript/jquery/plugins/treeTable/jquery.treeTable.min.js"></script>
        <script src="./lib/apexcharts.min.js" language="javascript"></script>
    </HEAD>
<BODY>
    <DIV id='global_health'></DIV>
</BODY>

<script type="text/javascript">
    let widgetId = <?= $widgetId; ?>;
    let autoRefresh = <?= $autoRefresh; ?>;
    let timeout;
    let pageNumber = 0;
    let broker = '<?= $broker; ?>';

    jQuery(function () {
        loadPage();
    });

    function loadPage() {
        let indexPage = "global_health";
        jQuery.ajax("./src/" + indexPage + ".php?widgetId=" + widgetId, {
            success: function (htmlData) {
                jQuery("#global_health").html("");
                jQuery("#global_health").html(htmlData);
                let h = jQuery("#global_health").prop("scrollHeight") + 36;
                parent.iResize(window.name, h);
                jQuery("#global_health").find("img, style, script, link").on('load', function () {
                    let h = jQuery("#global_health").prop("scrollHeight") + 36;
                    parent.iResize(window.name, h);
                });
            }
        });
        if (autoRefresh) {
            if (timeout) {
                clearTimeout(timeout);
            }
            timeout = setTimeout(loadPage, (autoRefresh * 1000));
        }
    }
</script>
</HTML>
