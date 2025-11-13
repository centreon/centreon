<?php

/*
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

use Core\Common\Domain\Exception\RepositoryException;

if (! isset($centreon)) {
    exit();
}

$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

// Normalize select input
$selectInput = $_GET['select'] ?? $_POST['select'] ?? [];
if (! is_array($selectInput)) {
    $selectInput = [$selectInput];
}
$select = filter_var_array($selectInput, FILTER_VALIDATE_INT) ?: [];
$select = array_filter($select, fn ($value) => $value !== false);

// Normalize dupNbr input
$dupNbrInput = $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? [];
if (! is_array($dupNbrInput)) {
    $dupNbrInput = [$dupNbrInput];
}
$dupNbr = filter_var_array($dupNbrInput, FILTER_VALIDATE_INT) ?: [];
$dupNbr = array_filter($dupNbr, fn ($value) => $value !== false);
$dupNbr = array_intersect_key($dupNbr, $select);

#Path to the configuration dir
$path = "./include/configuration/configObject/traps-groups/";

#PHP functions
require_once $path . "DB-Func.php";
require_once "./include/common/common-Func.php";

/* Set the real page */
if (isset($ret) && is_array($ret) && $ret['topology_page'] != "" && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}
try {
    switch ($o) {
        case 'a':
            require_once $path . 'formGroups.php';
            break; // Add a Trap
        case 'w':
            require_once $path . 'formGroups.php';
            break; // Watch a Trap
        case 'c':
            require_once $path . 'formGroups.php';
            break; // Modify a Trap
        case 'm':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                multipleTrapGroupInDB($select, $dupNbr);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listGroups.php';
            break; // Duplicate n Traps
        case 'd':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                deleteTrapGroupInDB($select);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listGroups.php';
            break; // Delete n Traps
        default:
            require_once $path . 'listGroups.php';
            break;
    }
} catch (RepositoryException $exception) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while processing traps groups: ' . $exception->getMessage(),
        exception: $exception
    );
    $msg = new CentreonMsg();
    $msg->setImage('./img/icons/warning.png');
    $msg->setTextStyle('bold');
    $msg->setText('Error while processing traps groups');
}
