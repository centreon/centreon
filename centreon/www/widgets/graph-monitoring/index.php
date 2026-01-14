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
use Adaptation\Database\Connection\ValueObject\QueryParameter;

require_once '../require.php';
require_once '../widget-error-handling.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUser.class.php';
require_once $centreon_path . 'www/class/centreonAclLazy.class.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';

/** @var CentreonDB $configurationDatabase */
$configurationDatabase = $dependencyInjector['configuration_db'];

CentreonSession::start(1);
if (! CentreonSession::checkSession(session_id(), $configurationDatabase)) {
    echo 'Bad Session';

    exit();
}

if (!isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];

$widgetId = filter_var(
    $_REQUEST['widgetId'],
    FILTER_VALIDATE_INT,
);

try {
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => "Generic-theme",
        'dark' => "Centreon-Dark",
        default => throw new \Exception('Unknown user theme : ' . $centreon->user->theme),
    };

    $theme = $variablesThemeCSS === 'Generic-theme'
        ? $variablesThemeCSS . '/Variables-css'
        : $variablesThemeCSS;

    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }

    /** @var CentreonDB $realTimeDatabase */
    $realTimeDatabase = $dependencyInjector['realtime_db'];

    $widgetObj = new CentreonWidget($centreon, $configurationDatabase);

    /**
     * @var array{
     *     service: string,
     *     graph_period: string,
     *     refresh_interval: string,
     * } $preferences
     */
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    if (! preg_match('/^(\d+)-(\d+)$/', $preferences['service'], $matches)) {
        throw new InvalidArgumentException(_('Please select a resource first'));
    }

    [, $hostId, $serviceId] = $matches;

    if (empty($preferences['graph_period'])) {
        throw new InvalidArgumentException(_('Please select a graph period'));
    }

    $autoRefresh = filter_var(
        $preferences['refresh_interval'],
        FILTER_VALIDATE_INT,
    );

    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = 30;
    }

    if (! $centreon->user->admin) {
        $acls = new CentreonAclLazy($centreon->user->user_id);
        $accessGroups = $acls->getAccessGroups();

        if ($accessGroups->isEmpty()) {
            throw new Exception(sprintf('You are not allowed to see graphs for service identified by ID %d', $serviceId));
        }

        $queryParameters = [
            QueryParameter::int('serviceId', (int) $serviceId),
            QueryParameter::int('hostId', (int) $hostId),
        ];

        ['parameters' => $aclParameters, 'placeholderList' => $bindQuery] = createMultipleBindParameters(
            $accessGroups->getIds(),
            'access_group_id',
            QueryParameterTypeEnum::INTEGER,
        );

        $request = <<<SQL
                SELECT
                    1 AS REALTIME,
                    host_id
                FROM
                    centreon_acl
                WHERE
                    host_id = :hostId
                    AND service_id = :serviceId
                    AND group_id IN ({$bindQuery})
            SQL;

        if ($realTimeDatabase->fetchAllAssociative($request, QueryParameters::create([...$queryParameters, ...$aclParameters])) === []) {
            throw new Exception(sprintf('You are not allowed to see graphs for service identified by ID %d', $serviceId));
        }
    }
} catch (Exception $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error fetching data for graph-monitoring widget: ' . $exception->getMessage(),
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

    exit;
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/graph-monitoring/src/';
$template = SmartyBC::createSmartyTemplate($path, '/');
$template->assign('theme', $theme);
$template->assign('widgetId', $widgetId);
$template->assign('preferences', $preferences);
$template->assign('interval', $preferences['graph_period']);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('graphId', sprintf('%d_%d', (int) $hostId, (int) $serviceId));

$template->display('index.ihtml');
