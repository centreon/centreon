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

const OBJECT_TYPE_HOST = 'hosts';
const OBJECT_TYPE_SERVICE = 'services';

CentreonSession::start(1);

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

try {
    $configurationDatabase = $dependencyInjector['configuration_db'];

    $widgetObj = new CentreonWidget($centreon, $configurationDatabase);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $objectType = ! empty($preferences['object_type']) ? $preferences['object_type'] : OBJECT_TYPE_HOST;

    if (! in_array($objectType, [OBJECT_TYPE_HOST, OBJECT_TYPE_SERVICE])) {
        throw new InvalidArgumentException('Invalid object type provided in tactical-overview. Accepted: hosts or services');
    }

    $autoRefresh = (isset($preferences['refresh_interval']) && (int) $preferences['refresh_interval'] > 0)
        ? (int) $preferences['refresh_interval']
        : 30;
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
        message: 'Error fetching data for tactical-overview widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

    exit;
}

// Smarty template initialization
$path = $centreon_path . "www/widgets/tactical-overview/src/";
$template = SmartyBC::createSmartyTemplate($path, './');

$template->assign(
    'theme',
    $variablesThemeCSS === 'Generic-theme'
        ? $variablesThemeCSS . '/Variables-css'
        : $variablesThemeCSS
);

$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

$buildParameter = function (string $id, string $name) {
    return [
        'id' => $id,
        'name' => $name,
    ];
};

$objectType === OBJECT_TYPE_HOST ? require_once 'src/hosts_status.php' : require_once 'src/services_status.php';
