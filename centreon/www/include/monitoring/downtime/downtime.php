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

if (!isset($centreon)) {
    exit();
}

/*
 * External Command Object
 */
$ecObj = new CentreonExternalCommand($centreon);

$form = new HTML_QuickFormCustom('Form', 'post', "?p=" . $p);

/*
 * Path to the configuration dir
 */
$path = "./include/monitoring/downtime/";

/*
 * PHP functions
 */
require_once "./include/common/common-Func.php";
require_once $path . "common-Func.php";
require_once "./include/monitoring/external_cmd/functions.php";

switch ($o) {
    case "a":
        require_once($path . "AddDowntime.php");
        break;
    case "ds":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (isset($_POST["select"])) {
                foreach ($_POST["select"] as $key => $value) {
                    $res = explode(';', urldecode($key));
                    $ishost = isDownTimeHost($res[2]);
                    if (
                        $oreon->user->access->admin
                        || ($ishost && $oreon->user->access->checkAction("host_schedule_downtime"))
                        || (!$ishost && $oreon->user->access->checkAction("service_schedule_downtime"))
                    ) {
                        $ecObj->deleteDowntime($res[0], [$res[1] . ';' . $res[2] => 'on']);
                        deleteDowntimeInDb($res[2]);
                    }
                }
            }
        } else {
            unvalidFormMessage();
        }
        try {
            require_once($path . "listDowntime.php");
        } catch (\Throwable $ex) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: 'Error while listing downtime: ' . $ex->getMessage(),
                exception: $ex
            );
            throw $ex;
        }
        break;
    case "cs":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (isset($_POST["select"])) {
                foreach ($_POST["select"] as $key => $value) {
                    $res = explode(';', urldecode($key));
                    $ishost = isDownTimeHost($res[2]);
                    if (
                        $oreon->user->access->admin
                        || ($ishost && $oreon->user->access->checkAction("host_schedule_downtime"))
                        || (!$ishost && $oreon->user->access->checkAction("service_schedule_downtime"))
                    ) {
                        $ecObj->deleteDowntime($res[0], [$res[1] . ';' . $res[2] => 'on']);
                    }
                }
            }
        } else {
            unvalidFormMessage();
        }
        // then, as all the next cases, requiring the listDowntime.php
    case "vs":
    case "vh":
    default:
        try {
            require_once($path . "listDowntime.php");
        } catch (\Throwable $ex) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: 'Error while listing downtime: ' . $ex->getMessage(),
                exception: $ex
            );
            throw $ex;
        }
        break;
}
