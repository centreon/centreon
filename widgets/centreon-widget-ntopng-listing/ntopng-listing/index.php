<?php

/*
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

require_once '../require.php';
require_once __DIR__ . '/../../class/centreon.class.php';
require_once __DIR__ . '/../../class/centreonSession.class.php';
require_once __DIR__ . '/../../class/centreonWidget.class.php';
require_once __DIR__ . '/../../../bootstrap.php';

const MAX_NUMBER_OF_LINE = 100;
const DEFAULT_NUMBER_OF_LINES = 10;
const DEFAULT_AUTO_REFRESH = 60;

const OSI_LEVEL_4 = 'l4';
const OSI_LEVEL_7 = 'l7';

try {
    CentreonSession::start(1);
    if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
        exit;
    }
    $centreon = $_SESSION['centreon'];

    $widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }

    $centreonWidget = new CentreonWidget($centreon, $dependencyInjector['configuration_db']);

    $preferences = $centreonWidget->getWidgetPreferences($widgetId);
    $preferences['login'] = filter_var($preferences['login'] ?? "", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $preferences['password'] = filter_var($preferences['password'] ?? "", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $preferences['token'] = filter_var($preferences['token'] ?? "", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $preferences['address'] = filter_var($preferences['address'] ?? "", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $preferences['protocol'] = filter_var($preferences['protocol'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $preferences['filter-address'] = filter_var($preferences['filter-address'] ?? "", FILTER_VALIDATE_IP);
    $preferences['filter-port'] = filter_var($preferences['filter-port'] ?? "", FILTER_VALIDATE_INT);
    $preferences['interface'] = filter_var($preferences['interface'] ?? 0, FILTER_VALIDATE_INT);
    $preferences['port'] = filter_var($preferences['port'] ?? 3000, FILTER_VALIDATE_INT);
    $preferences['mode'] = filter_var($preferences['mode'] ?? 'top-n-local', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $preferences['sort'] = filter_var($preferences['sort'] ?? 'thpt', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $preferences['top'] = filter_var($preferences['top'] ?? DEFAULT_NUMBER_OF_LINES, FILTER_VALIDATE_INT);
    $autoRefresh = filter_var($preferences['refresh_interval'] ?? DEFAULT_AUTO_REFRESH, FILTER_VALIDATE_INT);
    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = DEFAULT_AUTO_REFRESH;
    }
    if ($preferences['top'] > MAX_NUMBER_OF_LINE) {
        $preferences['top'] = MAX_NUMBER_OF_LINE;
    } elseif ($preferences['top'] < 1) {
        $preferences['top'] = DEFAULT_NUMBER_OF_LINES;
    }
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => "Generic-theme",
        'dark' => "Centreon-Dark",
        default => throw new Exception('Unknown user theme : ' . $centreon->user->theme),
    };
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

$data = ['error' => 0];
if (isset($preferences['login'], $preferences['password'], $preferences['address'])) {
    require_once './functions.php';
    $preferences['base_url'] = $preferences['protocol'] . "://" . $preferences['address'] . ":" . $preferences['port'];

    try {
        $preferences['uri'] = createLink($preferences);
        $result = callProbe($preferences);
        $array = json_decode($result, true);
        if (in_array($preferences['mode'], ['top-n-local', 'top-n-remote'], true)) {
            $data['hosts'] = [];
            $i = 1;
            foreach ($array['rsp']['data'] as $traffic) {
                $data['hosts'][] = [
                    "name" => preg_replace('/\[.*\]/', '', $traffic['name']),
                    "ip" => $traffic['ip'],
                    "bandwidth" => round($traffic['thpt']['bps'] / 1000000, 2),
                    "packets_per_second" => round($traffic['thpt']['pps'], 2)
                ];
                if ($i >= $preferences['top']) {
                    break;
                }
                $i++;
            }
        } elseif ($preferences['mode'] === "top-n-flows") {
            $data['flows'] = [];
            $i = 1;
            foreach ($array['rsp']['data'] as $traffic) {
                $protocol = $traffic['protocol'][OSI_LEVEL_4] . " " . $traffic['protocol'][OSI_LEVEL_7];
                $client = $traffic['client']['name'] . ":" . $traffic['client']['port'];
                $server = $traffic['server']['name'] . ":" . $traffic['server']['port'];
                $bandwidth = round($traffic['thpt']['bps'] / 1000000, 2);
                $pps = round($traffic['thpt']['pps'], 2);
                $data['flows'][] = [
                    "protocol" => $protocol,
                    "client" => $client,
                    "server" => $server,
                    "bandwidth" => $bandwidth,
                    "packets_per_second" => $pps
                ];
                if ($i >= $preferences['top']) {
                    break;
                }
                $i++;
            }
        } elseif ($preferences['mode'] === "top-n-application") {
            $applications = [];
            $applicationList = [];
            $totalBandwidth = 0;
            foreach ($array['rsp']['data'] as $traffic) {
                $totalBandwidth += $traffic['thpt']['bps'];
                $application = $traffic['protocol'][OSI_LEVEL_4] . "-" . $traffic['protocol'][OSI_LEVEL_7];
                if (in_array($application, $applicationList)) {
                    $applications[$application]['bandwidth'] += $traffic['thpt']['bps'];
                } else {
                    $applicationList[] = $application;
                    $applications[$application] = [];
                    $applications[$application]['protocol'] = $traffic['protocol'][OSI_LEVEL_4];

                    $l7 = $traffic['protocol'][OSI_LEVEL_7] === "Unknown"
                        ? $traffic['server']['port']
                        : $traffic['protocol'][OSI_LEVEL_7];

                    $applications[$application]['protocol'] = $traffic['protocol'][OSI_LEVEL_4];
                    $applications[$application]['application'] = $l7;
                    $applications[$application]['bandwidth'] = $traffic['thpt']['bps'];
                }
            }
            $sortedApplications = [];
            foreach ($applications as $application) {
                $sortedApplications[] = [
                    "application" => $application['application'],
                    "protocol" => $application['protocol'],
                    "bandwidth" => $application['bandwidth']
                ];
            }
            usort($sortedApplications, function ($a, $b) {
                return $a['bandwidth'] < $b['bandwidth'] ? 1 : -1;
            });
            $data['applications'] = [];
            $data['total_bandwidth'] = round($totalBandwidth / 1000000, 2);

            $i = 1;
            foreach ($sortedApplications as $application) {
                $bandwidthPct = round(100 * $application['bandwidth'] / $totalBandwidth, 2);
                $data['applications'][] = [
                    "application" => $application['application'],
                    "protocol" => $application['protocol'],
                    "bandwidth" => round($application['bandwidth'] / 1000000, 2),
                    "bandwidth_pct" => $bandwidthPct
                ];
                if ($i >= $preferences['top']) {
                    break;
                }
                $i++;
            }
        }
    } catch (Exception $ex) {
        $data['error'] = 1;
        $data['message'] = str_replace('\n', '<br>', $ex->getMessage());
    }
}
$template = new Smarty();
$template = initSmartyTplForPopup(__DIR__ . "/src/", $template, "./", __DIR__ . '../../..');
$template->assign('data', $data);
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('theme', $variablesThemeCSS);
$template->display('ntopng.ihtml');
