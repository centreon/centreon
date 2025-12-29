<?php

/*
 * Copyright 2005-2020 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\Exception\ConnectionException;

require_once '../require.php';
require_once '../widget-error-handling.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';
require_once $centreon_path . 'bootstrap.php';

CentreonSession::start(1);

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

try {
    if ($widgetId === false) {
        throw new \InvalidArgumentException('Widget ID must be an integer');
    }

    /**
     * @var CentreonDB $configurationDatabase
     */
    $configurationDatabase = $dependencyInjector['configuration_db'];

    /**
     * @var CentreonDB $realtimeDatabase
     */
    $realtimeDatabase = $dependencyInjector['realtime_db'];

    $widgetObj = new CentreonWidget($centreon, $configurationDatabase);

    /**
     * @var array{
     *     poller: string,
     *     avg-l: string,
     *     max-e: string,
     *     avg-e: string,
     *     autoRefresh: string
     * } $preferences
     */
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    if (empty($preferences['poller'])) {
        throw new InvalidArgumentException('Please select a poller');
    }

    $autoRefresh = filter_var(
        $preferences['autoRefresh'],
        FILTER_VALIDATE_INT,
    );

    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = 30;
    }

    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => "Generic-theme",
        'dark' => "Centreon-Dark",
        default => throw new \Exception('Unknown user theme : ' . $centreon->user->theme),
    };

    $theme = $variablesThemeCSS === 'Generic-theme'
        ? $variablesThemeCSS . '/Variables-css'
        : $variablesThemeCSS;
} catch (Exception $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error while using engine-status widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

    exit;
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/engine-status/src/';
$template = SmartyBC::createSmartyTemplate($path, './');

$latencyData = [];
$executionTimeData = [];
$hostStatusesData = [];
$serviceStatusesData = [];

['parameters' => $parameters, 'placeholderList' => $pollerList] = createMultipleBindParameters(
    explode(',', $preferences['poller']),
    'poller_id',
    QueryParameterTypeEnum::INTEGER,
);

$queryParameters = QueryParameters::create($parameters);

$queryLatency = <<<SQL
    SELECT
        1 AS realtime,
        MAX(h.latency) AS h_max,
        AVG(h.latency) AS h_moy,
        MAX(s.latency) AS s_max,
        AVG(s.latency) AS s_moy
    FROM hosts h
    INNER JOIN services s
        ON h.host_id = s.host_id
    WHERE
        h.instance_id IN ({$pollerList})
        AND s.enabled = '1'
        AND s.check_type = '0'
    SQL;

$queryExecutionTime = <<<SQL
        SELECT
            1 AS realtime,
            MAX(h.execution_time) AS h_max,
            AVG(h.execution_time) AS h_moy,
            MAX(s.execution_time) AS s_max,
            AVG(s.execution_time) AS s_moy
        FROM hosts h
        INNER JOIN services s
            ON h.host_id = s.host_id
        WHERE
            h.instance_id IN ({$pollerList})
            AND s.enabled = '1'
            AND s.check_type = '0';
    SQL;

try {
    foreach ($realtimeDatabase->iterateAssociative($queryLatency, $queryParameters) as $record) {
        $record['h_max'] = round($record['h_max'], 3);
        $record['h_moy'] = round($record['h_moy'], 3);
        $record['s_max'] = round($record['s_max'], 3);
        $record['s_moy'] = round($record['s_moy'], 3);
        $latencyData[] = $record;
    }
} catch (ConnectionException $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_SQL,
        message: 'Error retrieving latency data for engine-status widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme);

    exit;
}

try {
    foreach ($realtimeDatabase->iterateAssociative($queryExecutionTime, $queryParameters) as $record) {
        $record['h_max'] = round($record['h_max'], 3);
        $record['h_moy'] = round($record['h_moy'], 3);
        $record['s_max'] = round($record['s_max'], 3);
        $record['s_moy'] = round($record['s_moy'], 3);
        $executionTimeData[] = $record;
    }
} catch (ConnectionException $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_SQL,
        message: 'Error retrieving execution time data for engine-status widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme);

    exit;
}

$queryHostStatuses = <<<SQL
        SELECT
            1 AS REALTIME,
            SUM(h.state = 1) AS Dow,
            SUM(h.state = 2) AS Un,
            SUM(h.state = 0) AS Up,
            SUM(h.state = 4) AS Pend
        FROM hosts h
        WHERE h.instance_id IN ({$pollerList})
          AND h.enabled = 1
          AND h.name NOT LIKE '%Module%'
    SQL;

$queryServiceStatuses = <<<SQL
        SELECT
            1 AS REALTIME,
            SUM(s.state = 2) AS Cri,
            SUM(s.state = 1) AS Wa,
            SUM(s.state = 0) AS Ok,
            SUM(s.state = 4) AS Pend,
            SUM(s.state = 3) AS Unk
        FROM services s
        INNER JOIN hosts h
            ON h.host_id = s.host_id
        WHERE h.instance_id IN ({$pollerList})
          AND s.enabled = 1
          AND h.name NOT LIKE '%Module%'
    SQL;

try {
    $hostStatusesData = $realtimeDatabase->fetchAllAssociative($queryHostStatuses, $queryParameters);
} catch (ConnectionException $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_SQL,
        message: 'Error retrieving host statuses data for engine-status widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme);

    exit;
}

try {
    $serviceStatusesData = $realtimeDatabase->fetchAllAssociative($queryServiceStatuses, $queryParameters);
} catch (ConnectionException $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_SQL,
        message: 'Error retrieving service statuses data for engine-status widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme);

    exit;
}

$avg_l = $preferences['avg-l'];
$avg_e = $preferences['avg-e'];
$max_e = $preferences['max-e'];
$template->assign('avg_l', $avg_l);
$template->assign('avg_e', $avg_e);
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('max_e', $max_e);
$template->assign('dataSth', $hostStatusesData);
$template->assign('dataSts', $serviceStatusesData);
$template->assign('dataEx', $executionTimeData);
$template->assign('dataLat', $latencyData);
$template->assign(
    'theme',
    $theme
);
$template->display('engine-status.ihtml');
