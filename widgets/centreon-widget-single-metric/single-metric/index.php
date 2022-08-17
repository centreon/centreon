<?php

/**
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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
require_once "functions.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'bootstrap.php';

CentreonSession::start(1);

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }
    $centreonDb = $dependencyInjector['configuration_db'];
    $centreonRtDb = $dependencyInjector['realtime_db'];

    $centreonWidget = new CentreonWidget($centreon, $centreonDb);
    $preferences = $centreonWidget->getWidgetPreferences($widgetId);
    $autoRefresh = filter_var($preferences['refresh_interval'], FILTER_VALIDATE_INT);
    $preferences['metric_name'] = filter_var($preferences['metric_name'], FILTER_SANITIZE_STRING);
    $preferences['font_size'] = filter_var($preferences['font_size'] ?? 80, FILTER_VALIDATE_INT);
    $preferences['display_number'] = filter_var($preferences['display_number'] ?? 1000, FILTER_VALIDATE_INT);
    $preferences['coloring'] = filter_var($preferences['coloring'] ?? 'black', FILTER_SANITIZE_STRING);
    $preferences['display_path'] = filter_var($preferences['display_path'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $preferences['display_threshold'] = filter_var($preferences['display_threshold'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = 30;
    }
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => "Generic-theme",
        'dark' => "Centreon-Dark",
        default => throw new \Exception('Unknown user theme : ' . $centreon->user->theme),
    };
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

$kernel = \App\Kernel::createForWeb();
/**
 * @var Centreon\Application\Controller\MonitoringResourceController $resourceController
 */
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

//configure smarty
$isAdmin = $centreon->user->admin === '1';
$accessGroups = [];
if (! $isAdmin) {
    $access = new CentreonACL($centreon->user->get_id());
    $accessGroups = $access->getAccessGroups();
}

$path = $centreon_path . "www/widgets/single-metric/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);
$template->assign('theme', $variablesThemeCSS);
$template->assign(
    'webTheme',
    $variablesThemeCSS === 'Generic-theme'
        ? $variablesThemeCSS . '/Variables-css'
        : $variablesThemeCSS
);

$data = array();

if (! isset($preferences['service']) || $preferences['service'] === "") {
    $template->display('metric.ihtml');
} else {
    list($hostId, $serviceId) = explode("-", $preferences['service']);
    $numLine = 0;
    if ($isAdmin || ! empty($accessGroups)) {
        $query =
            "SELECT
                i.host_name AS host_name,
                i.service_description AS service_description,
                i.service_id AS service_id,
                i.host_id AS host_id,
                REPLACE(m.current_value, '.', ',') AS current_value,
                m.current_value AS current_float_value,
                m.metric_name AS metric_name,
                m.unit_name AS unit_name,
                m.warn AS warning,
                m.crit AS critical,
                s.state AS status
            FROM
                metrics m,
                hosts h "
                    . (!$isAdmin ? ", centreon_acl acl " : "")
                    . " , index_data i
            LEFT JOIN services s ON s.service_id  = i.service_id AND s.enabled = 1
            WHERE i.service_id = :serviceId
            AND i.id = m.index_id
            AND m.metric_name = :metricName
            AND i.host_id = h.host_id 
            AND i.host_id = :hostId ";
        if (!$isAdmin) {
            $query .= "AND i.host_id = acl.host_id
                AND i.service_id = acl.service_id
                AND acl.group_id IN (" . implode(',', array_keys($accessGroups)) . ")";
        }
        $query .= "AND s.enabled = 1
            AND h.enabled = 1;";

        $stmt = $centreonRtDb->prepare($query);
        $stmt->bindParam(':hostId', $hostId, PDO::PARAM_INT);
        $stmt->bindParam(':metricName', $preferences['metric_name'], PDO::PARAM_STR);
        $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['details_uri'] = $resourceController->buildServiceDetailsUri($row['host_id'], $row['service_id']);
            $row['host_uri'] = $resourceController->buildHostDetailsUri($row['host_id']);
            $row['graph_uri'] = $resourceController->buildServiceUri($row['host_id'], $row['service_id'], 'graph');
            $data[] = $row;
            $numLine++;
        }
    }

    /* Calculate Threshold font size */
    $preferences['threshold_font_size'] = round($preferences['font_size'] / 8, 0);
    if ($preferences['threshold_font_size'] < 9) {
        $preferences['threshold_font_size'] = 9;
    }

    if ($numLine > 0) {
        // Human readable
        if ($preferences['display_number'] === 1000 || $preferences['display_number'] === 1024) {
            list($size, $data[0]['unit_displayed']) = convertSizeToHumanReadable(
                $data[0]['current_float_value'],
                $data[0]['unit_name'],
                $preferences['display_number']
            );
            $data[0]['value_displayed'] = str_replace(".", ",", (string) $size);
            if (is_numeric($data[0]['warning'])) {
                $newWarning = convertSizeToHumanReadable(
                    $data[0]['warning'],
                    $data[0]['unit_name'],
                    $preferences['display_number']
                );
                $data[0]['warning_displayed'] = str_replace(".", ",", (string) $newWarning[0]);
            }
            if (is_numeric($data[0]['critical'])) {
                $newCritical = convertSizeToHumanReadable(
                    $data[0]['critical'],
                    $data[0]['unit_name'],
                    $preferences['display_number']
                );
                $data[0]['critical_displayed'] = str_replace(".", ",", (string) $newCritical[0]);
            }
        } else {
            $data[0]['value_displayed'] = $data[0]['current_value'];
            $data[0]['unit_displayed'] =  $data[0]['unit_name'];
            $data[0]['warning_displayed'] = $data[0]['warning'];
            $data[0]['critical_displayed'] = $data[0]['critical'];
        }
    }

    $template->assign('preferences', $preferences);
    $template->assign('widgetId', $widgetId);
    $template->assign('autoRefresh', $autoRefresh);
    $template->assign('data', $data);
    $template->display('metric.ihtml');
}
