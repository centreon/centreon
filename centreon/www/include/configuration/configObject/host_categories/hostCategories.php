<?php

/*
 * Copyright 2005-2009 Centreon
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

$hG = $_GET["hc_id"] ?? null;
$hP = $_POST["hc_id"] ?? null;
$hc_id = $hG ?: $hP;

$cG = $_GET["select"] ?? null;
$cP = $_POST["select"] ?? null;
$select = $cG ?: $cP;

$cG = $_GET["dupNbr"] ?? null;
$cP = $_POST["dupNbr"] ?? null;
$dupNbr = $cG ?: $cP;

/*
 * Path to the configuration dir
 */
$path = "./include/configuration/configObject/host_categories/";

/*
 * PHP functions
 */
require_once $path . "DB-Func.php";
require_once "./include/common/common-Func.php";

/* Set the real page */
if (isset($ret) && is_array($ret) && $ret['topology_page'] != "" && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $centreon->user->access;
$dbmon = new CentreonDB('centstorage');
$aclDbName = $acl->getNameDBAcl();
$hcString = $acl->getHostCategoriesString();
$hoststring = $acl->getHostsString('ID', $dbmon);

switch ($o) {
    case "a":
        require_once($path . "formHostCategories.php");
        break;
    case "w":
        require_once($path . "formHostCategories.php");
        break;
    case "c":
        require_once($path . "formHostCategories.php");
        break;
    case "s":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostCategoriesInDB($hc_id);
        } else {
            unvalidFormMessage();
        }
        require_once($path . "listHostCategories.php");
        break;
    case "ms":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostCategoriesInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once($path . "listHostCategories.php");
        break;
    case "u":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostCategoriesInDB($hc_id);
        } else {
            unvalidFormMessage();
        }
        require_once($path . "listHostCategories.php");
        break;
    case "mu":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostCategoriesInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once($path . "listHostCategories.php");
        break;
    case "m":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleHostCategoriesInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once($path . "listHostCategories.php");
        break;
    case "d":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteHostCategoriesInDB($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once($path . "listHostCategories.php");
        break;
    default:
        require_once($path . "listHostCategories.php");
        break;
}
