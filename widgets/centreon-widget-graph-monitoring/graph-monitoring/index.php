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

require_once "../require.php";
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUser.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';

$pearDB = $dependencyInjector['configuration_db'];

CentreonSession::start(1);
if (!CentreonSession::checkSession(session_id(), $pearDB)) {
    print "Bad Session";
    exit();
}

if (!isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }

    global $pearDB;

    $pearDB = $dbAcl = $db = $dependencyInjector['configuration_db'];
    $db2 = $dependencyInjector['realtime_db'];

    $widgetObj = new CentreonWidget($centreon, $db);
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
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

if ($centreon->user->admin == 0) {
    $access = new CentreonACL($centreon->user->get_id());
    $grouplist = $access->getAccessGroups();
    $grouplistStr = $access->getAccessGroupsString();
}

$path = $centreon_path . 'www/widgets/graph-monitoring/src/';
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "/", $centreon_path);

/*
* Check ACL
*/

$acl = 1;
if (isset($tab[0]) && isset($tab[1]) && $centreon->user->admin == 0) {
    $sql = "SELECT host_id
        FROM centreon_acl
        WHERE host_id = :hostId
        AND service_id = :serviceId
        AND group_id IN (:groupList)";

    $res = $dbAcl->prepare($sql);
    $res->bindValue(':hostId', $tab[0], PDO::PARAM_INT);
    $res->bindValue(':serviceId', $tab[1], PDO::PARAM_INT);
    $res->bindValue(':groupList', $grouplistStr, PDO::PARAM_STR);
    $res->execute();

    if (!$res->rowCount()) {
        $acl = 0;
    }
}

$servicePreferences = '';

if ($acl === 0) {
    $servicePreferences = '';
} elseif (false === isset($preferences['service']) || trim($preferences['service']) === '') {
    $servicePreferences = "<div class='update' style='text-align:center;margin-left: auto;margin-right: " .
        "auto;width:350px;'>" . _("Please select a resource first") . "</div>";
} elseif (false === isset($preferences['graph_period']) || trim($preferences['graph_period']) === '') {
    $servicePreferences = "<div class='update' style='text-align:center;margin-left: auto;margin-right: " .
        "auto;width:350px;'>" . _("Please select a graph period") . "</div>";
}
$template->assign(
    'theme',
    $variablesThemeCSS === 'Generic-theme' ? $variablesThemeCSS . '/Variables-css' : $variablesThemeCSS
);
$template->assign('widgetId', $widgetId);
$template->assign('preferences', $preferences);
$template->assign('interval', $preferences['graph_period']);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('graphId', str_replace('-', '_', $preferences['service']));
$template->assign('servicePreferences', $servicePreferences);

$template->display('index.ihtml');
