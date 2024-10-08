<?php

/**
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
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

$downtime_id = filter_var(
    $_GET['hg_id'] ?? $_POST['hg_id'] ?? null,
    FILTER_VALIDATE_INT
);

$cG = $_GET["select"] ?? null;
$cP = $_POST["select"] ?? null;
$select = $cG ?: $cP;

$cG = $_GET["dupNbr"] ?? null;
$cP = $_POST["dupNbr"] ?? null;
$dupNbr = $cG ?: $cP;

$path = "./include/monitoring/recurrentDowntime/";

require_once "./class/centreonDowntime.class.php";
$downtime = new CentreonDowntime($pearDB);

require_once "./include/common/common-Func.php";

if (isset($_POST["o1"]) && isset($_POST["o2"])) {
    if ($_POST["o1"] != "") {
        $o = $_POST["o1"];
    }
    if ($_POST["o2"] != "") {
        $o = $_POST["o2"];
    }
}

/*
 * Set the real page
 */
if (isset($ret) && is_array($ret) && $ret['topology_page'] != "" && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

if (isset($_GET["period_form"]) || isset($_GET["period"]) && $o == "") {
    require_once $path . "ajaxForms.php";
} else {
    switch ($o) {
        case "a":
            require_once($path . "formDowntime.php");
            break; #Add a downtime
        case "w":
            require_once($path . "formDowntime.php");
            break; #Watch a downtime
        case "c":
            require_once($path . "formDowntime.php");
            break; #Modify a downtime
        case "e":
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                if ($downtime_id) {
                    $downtime->enable($downtime_id);
                }
            } else {
                unvalidFormMessage();
            }
            require_once($path . "listDowntime.php");
            break; #Activate a service
        case "ms":
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                $downtime->multiEnable($select ?? []);
            } else {
                unvalidFormMessage();
            }
            require_once($path . "listDowntime.php");
            break;
        case "u":
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                if ($downtime_id) {
                    $downtime->disable($downtime_id);
                }
            } else {
                unvalidFormMessage();
            }
            require_once($path . "listDowntime.php");
            break; #Desactivate a service
        case "mu":
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                $downtime->multiDisable($select ?? []);
            } else {
                unvalidFormMessage();
            }
            require_once($path . "listDowntime.php");
            break;
        case "m":
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                $downtime->duplicate($select ?? [], $dupNbr);
            } else {
                unvalidFormMessage();
            }
            require_once($path . "listDowntime.php");
            break; #Duplicate n services
        case "d":
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                $downtime->multiDelete(isset($select) ? array_keys($select) : []);
            } else {
                unvalidFormMessage();
            }
            require_once($path . "listDowntime.php");
            break; #Delete n services
        default:
            require_once($path . "listDowntime.php");
            break;
    }
}
