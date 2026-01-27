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

CentreonSession::start(1);

if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];

$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }
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
        message: 'Error fetching data for live-top10-cpu-usage widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

    exit;
}

$kernel = App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    Centreon\Application\Controller\MonitoringResourceController::class
);

$accessGroups = new AccessGroupCollection();

if (! $centreon->user->admin) {
    $acls = new CentreonAclLazy($centreon->user->user_id);
    $accessGroups->mergeWith($acls->getAccessGroups());
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/live-top10-cpu-usage/src/';
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
                    AVG(m.current_value) AS current_value,
                    s.state AS status
                FROM index_data i
                INNER JOIN metrics m
                    ON i.id = m.index_id
                INNER JOIN hosts h
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
                  AND m.current_value <= 100
                  AND h.enabled = 1
                GROUP BY
                    i.host_id,
                    i.service_id,
                    i.host_name,
                    i.service_description,
                    s.state
                ORDER BY current_value DESC
                LIMIT :numberOfLines;
            SQL;

        $queryParameters[] = QueryParameter::string('serviceDescription', '%' . $preferences['service_description'] . '%');
        $queryParameters[] = QueryParameter::string('metricName', '%' . $preferences['metric_name'] . '%');
        $queryParameters[] = QueryParameter::int('numberOfLines', $preferences['nb_lin']);

        $numLine = 1;
        foreach ($realtimeDatabase->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
            $record['numLin'] = $numLine;
            $record['current_value'] = ceil($record['current_value']);
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
        message: 'Error fetching cpu usage data: ' . $exception->getMessage(),
        exception: $exception
    );

    throw $exception;
}

$template->assign('preferences', $preferences);
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('data', $data);
$template->assign('theme', $variablesThemeCSS);
$template->display('table_top10cpu.ihtml');
