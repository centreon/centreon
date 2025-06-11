<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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
 */

declare(strict_types=1);

if (!isset($centreon)) {
    exit();
}

$rawEscId = $_GET['esc_id'] ?? $_POST['esc_id'] ?? null;
$esc_id   = is_scalar($rawEscId)
    ? filter_var((string) $rawEscId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
    : null;

$rawSelect = $_GET['select'] ?? $_POST['select'] ?? [];
$select    = [];
if (is_array($rawSelect)) {
    foreach ($rawSelect as $key => $val) {
        $id = filter_var((string) $key, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($id !== null) {
            // preserve the key so array_keys($select) === [123, 456, …]
            $select[$id] = true;
        }
    }
}

$rawDupNbr = $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? [];
$dupNbr    = [];
if (is_array($rawDupNbr)) {
    foreach ($rawDupNbr as $key => $val) {
        $id  = filter_var((string) $key, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $num = filter_var((string) $val, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($id !== null && $num !== null) {
            $dupNbr[$id] = $num;
        }
    }
}

/*
 * Path to the configuration dir
 */
$path = "./include/configuration/configObject/escalation/";

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
    $dbmon = $acl->getNameDBAcl('broker');

    $hgs = $acl->getHostGroupAclConf(null, 'broker');
    $hgString = CentreonUtils::toStringWithQuotes($hgs);
    $sgs = $acl->getServiceGroupAclConf(null, 'broker');
    $sgString = CentreonUtils::toStringWithQuotes($sgs);

switch ($o) {
    case "a":
        require_once($path . "formEscalation.php");
        break; #Add a Escalation
    case "w":
        require_once($path . "formEscalation.php");
        break; #Watch a Escalation
    case "c":
        require_once($path . "formEscalation.php");
        break; #Modify a Escalation
    case "m":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleEscalationInDB(isset($select) ? $select : array(), $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once($path . "listEscalation.php");
        break; #Duplicate n Escalations
    case "d":
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteEscalationInDB(isset($select) ? $select : array());
        } else {
            unvalidFormMessage();
        }
        require_once($path . "listEscalation.php");
        break; #Delete n Escalation
    default:
        require_once($path . "listEscalation.php");
        break;
}
