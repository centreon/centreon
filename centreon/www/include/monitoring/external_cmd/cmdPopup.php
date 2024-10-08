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

require_once realpath(__DIR__ . "/../../../../bootstrap.php");

require_once _CENTREON_PATH_ . "www/class/centreonSession.class.php";
require_once _CENTREON_PATH_ . "www/class/centreon.class.php";
require_once _CENTREON_PATH_ . "www/class/centreonDB.class.php";
require_once _CENTREON_PATH_ . "www/class/centreonGMT.class.php";

session_start();
session_write_close();

$centreon = $_SESSION['centreon'];

global $centreon, $pearDB;

/*
 * Connect to DB
 */
$pearDB = $dependencyInjector['configuration_db'];

session_start();
session_write_close();

if (!CentreonSession::checkSession(session_id(), $pearDB)) {
    exit();
}
$centreon = $_SESSION['centreon'];

/*
 * GMT management
 */
$centreonGMT = new CentreonGMT($pearDB);
$sid = session_id();
$centreonGMT->getMyGMTFromSession($sid);

require_once _CENTREON_PATH_ . "www/include/common/common-Func.php";
require_once _CENTREON_PATH_ . "www/include/monitoring/common-Func.php";
include_once _CENTREON_PATH_ . "www/include/monitoring/external_cmd/functionsPopup.php";

const ACKNOWLEDGEMENT_ON_SERVICE = 70;
const ACKNOWLEDGEMENT_ON_HOST = 72;
const DOWNTIME_ON_SERVICE = 74;
const DOWNTIME_ON_HOST = 75;


if (
    isset($_POST['resources'])
    && isset($sid)
    && isset($_POST['cmd'])
) {
    $is_admin = isUserAdmin($sid);
    $resources = json_decode($_POST['resources'], true);
    foreach ($resources as $resource) {
        switch ((int) $_POST['cmd']) {
            case ACKNOWLEDGEMENT_ON_SERVICE:
                massiveServiceAck($resource);
                break;
            case ACKNOWLEDGEMENT_ON_HOST:
                massiveHostAck($resource);
                break;
            case DOWNTIME_ON_SERVICE:
                massiveServiceDowntime($resource);
                break;
            case DOWNTIME_ON_HOST:
                massiveHostDowntime($resource);
                break;
        }
    }
}
