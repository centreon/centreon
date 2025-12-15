<?php

/*
 * Copyright 2005-2021 Centreon
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
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;

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
require_once __DIR__ . '/src/function.php';

session_start();

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);
$grouplistStr = '';

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
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
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $autoRefresh = filter_var($preferences['refresh_interval'], FILTER_VALIDATE_INT);

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
        message: 'Error fetching data for live-top10-memory-usage widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

    exit;
}

$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

$accessGroups = new AccessGroupCollection();

if (! $centreon->user->admin) {
    $acls = new CentreonAclLazy($centreon->user->user_id);
    $accessGroups->mergeWith($acls->getAccessGroups());
}

// Smarty template initialization
$path = $centreon_path . "www/widgets/live-top10-memory-usage/src/";
$template = SmartyBC::createSmartyTemplate($path, './');

$data = [];
try {
    if ($centreon->user->admin || ! $accessGroups->isEmpty()) {
        $queryParameters = [];
        $query = <<<'SQL'
                SELECT
                    1 AS REALTIME,
                    i.host_name,
                    i.service_description,
                    i.service_id,
                    i.host_id,
                    m.current_value / m.max AS ratio,
                    m.max - m.current_value AS remaining_space,
                    s.state AS status
                FROM index_data i
                JOIN metrics m
                    ON i.id = m.index_id
                JOIN hosts h
                    ON i.host_id = h.host_id
                LEFT JOIN services s
                    ON s.service_id = i.service_id
                    AND s.enabled = 1
            SQL;

        if ($preferences['host_group']) {
            $query .= <<<'SQL'
                    INNER JOIN hosts_hostgroups hg
                        ON i.host_id = hg.host_id
                        AND hg.hostgroup_id = :hostGroupId
                SQL;

            $queryParameters[] = QueryParameter::int('hostGroupId', (int) $preferences['host_group']);
        }

        if (! $centreon->user->admin) {
            ['parameters' => $accessGroupParameters, 'placeholderList' => $accessGroupList] = createMultipleBindParameters(
                $accessGroups->getIds(),
                'access_group',
                QueryParameterTypeEnum::INTEGER
            );

            $query .= <<<SQL
                    INNER JOIN centreon_acl acl
                        ON i.host_id = acl.host_id
                        AND i.service_id = acl.service_id
                        AND acl.group_id IN ({$accessGroupList})
                SQL;

            $queryParameters = [...$accessGroupParameters, ...$queryParameters];
        }

        $query .= <<<'SQL'
                WHERE i.service_description LIKE :serviceDescription
                  AND m.metric_name LIKE :metricName
                  AND h.enabled = 1
                GROUP BY
                    i.host_id,
                    i.service_id,
                    i.host_name,
                    i.service_description,
                    s.state,
                    m.current_value,
                    m.max
                ORDER BY ratio DESC
                LIMIT :numberOfLines;
            SQL;

        $queryParameters[] = QueryParameter::string('serviceDescription', '%' . $preferences['service_description'] . '%');
        $queryParameters[] = QueryParameter::string('metricName', '%' . $preferences['metric_name'] . '%');
        $queryParameters[] = QueryParameter::int('numberOfLines', $preferences['nb_lin']);

        $numLine = 1;
        $in = 0;

        foreach ($realtimeDatabase->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
            $record['numLin'] = $numLine;
            while ($record['remaining_space'] >= 1024) {
                $record['remaining_space'] = $record['remaining_space'] / 1024;
                $in = $in + 1;
            }
            $record['unit'] = getUnit($in);
            $in = 0;
            $record['remaining_space'] = round($record['remaining_space']);
            $record['ratio'] = ceil($record['ratio'] * 100);
            $record['details_uri'] = $useDeprecatedPages
                ? '../../main.php?p=20201&o=svcd&host_name='
                    . $record['host_name']
                    . '&service_description='
                    . $record['service_description']
                : $resourceController->buildServiceDetailsUri(
                    $record['host_id'],
                    $record['service_id']
                );
            $data[] = $record;
            $numLine++;
        }
    }
} catch (ConnectionException $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error fetching memory usage data: ' . $exception->getMessage(),
        exception: $exception
    );

    throw $exception;
}

$template->assign('preferences', $preferences);
$template->assign('theme', $variablesThemeCSS);
$template->assign('widgetId', $widgetId);
$template->assign('preferences', $preferences);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('data', $data);
$template->display('table_top10memory.ihtml');
