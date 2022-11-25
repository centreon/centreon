<?php

/*
 * Copyright 2005-2021 Centreon
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

if (!isset($oreon)) {
    exit();
}

/**
 * Get the list of services id for a pool
 *
 * @param int $poolId The pool id
 * @return array
 */
function getListServiceForPool($poolId)
{
    global $pearDB;

    /*
    * Get pool informations
    */
    $res = $pearDB->query('SELECT pool_host_id, pool_prefix FROM mod_dsm_pool WHERE pool_id = ' . $poolId);
    $row = $res->fetch();
    $res->closeCursor();

    if (is_null($row['pool_host_id']) || $row['pool_host_id'] == '') {
        return array();
    }

    $poolPrefix = $row['pool_prefix'];

    $res = $pearDB->query(
        'SELECT service_id, service_description
        FROM service s, host_service_relation hsr
        WHERE hsr.host_host_id = ' . $row['pool_host_id'] . '
            AND service_id = service_service_id
            AND service_description LIKE "' . $poolPrefix . '%"'
    );
    $listServices = array();
    while ($row = $res->fetch()) {
        if (preg_match('/^' . $poolPrefix . '(\d{4})$/', $row['service_description'])) {
            $listServices[] = $row['service_id'];
        }
    }
    $res->closeCursor();
    return $listServices;
}

/**
 * Return if a host is already use in DSM
 *
 * @param int $hostId The host id
 * @param string $poolPrefix The pool prefix
 * @return bool
 */
function hostPoolPrefixUsed($hostId, $poolPrefix, $poolId = null)
{
    global $pearDB;

    $query = "SELECT COUNT(pool_id) AS nb FROM mod_dsm_pool WHERE pool_host_id = '" .
        $hostId . "' AND pool_prefix = '" . $poolPrefix . "'";
    if (!is_null($poolId)) {
        $query .= " AND pool_id != " . $poolId;
    }
    $res = $pearDB->query($query);
    $row = $res->fetch();
    if ($row['nb'] > 0) {
        return true;
    }
    return false;
}

/**
 *
 * Enable a slot pool system
 * @param $pool_id
 * @param $pool_arr
 */
function enablePoolInDB($pool_id = null, $pool_arr = array())
{
    global $pearDB;

    if (!$pool_id && !count($pool_arr)) {
        return;
    }

    if ($pool_id) {
        $pool_arr = array($pool_id => "1");
    }

    /*
        * Update services in Centreon configuration
        */
    foreach ($pool_arr as $id => $values) {
        $pearDB->query("UPDATE mod_dsm_pool SET pool_activate = '1' WHERE pool_id = '" . $id . "'");
        $listServices = getListServiceForPool($id);
        if (count($listServices) > 0) {
            $pearDB->query(
                "UPDATE service SET service_activate = '1' WHERE service_id IN (" . join(', ', $listServices) . ")"
            );
        }
    }
}

/**
 *
 * Disable a slot pool system
 * @param $pool_id
 * @param $pool_arr
 */
function disablePoolInDB($pool_id = null, $pool_arr = array())
{
    global $pearDB;

    if (!$pool_id && !count($pool_arr)) {
        return;
    }

    if ($pool_id) {
        $pool_arr = array($pool_id => "1");
    }

    foreach ($pool_arr as $id => $values) {
        $pearDB->query("UPDATE mod_dsm_pool SET pool_activate = '0' WHERE pool_id = '" . $id . "'");

        /*
         * Update services in Centreon configuration
         */
        $listServices = getListServiceForPool($id);
        if (count($listServices) > 0) {
            $pearDB->query(
                "UPDATE service SET service_activate = '0' WHERE service_id IN (" . join(', ', $listServices) . ")"
            );
        }
    }
}

/**
 *
 * Delete a slot pool system
 * @param $pools
 */
function deletePoolInDB($pools = array())
{
    global $pearDB;

    foreach ($pools as $key => $value) {
        /*
         * Delete services in Centreon configuration
         */
        $listServices = getListServiceForPool($key);
        if (count($listServices) > 0) {
            $pearDB->query('DELETE FROM service WHERE service_id IN (' . join(', ', $listServices) . ')');
        }
        $pearDB->query("DELETE FROM mod_dsm_pool WHERE pool_id = '" . $key . "'");
    }
}

/**
 *
 * Update a slot pool in DB
 * @param $pool_id
 * @return bool
 */
function updatePoolInDB($pool_id = null)
{
    global $form;

    if (!$pool_id) {
        return false;
    }

    $ret = $form->getSubmitValues();

    /*
        * Global function to use
        */
    return updatePool($pool_id);
}

/**
 * Insert a slot pool in DB
 *
 * @param array The values
 * @return int $pool_id The pool id, return -1 if error
 */
function insertPoolInDB($ret = array())
{
    $pool_id = insertPool($ret);
    return ($pool_id);
}

/**
 *
 * Check Pool Existance
 * @param $pool_name
 */
function testPoolExistence($pool_name)
{
    global $pearDB;

    $dbResult = $pearDB->query("SELECT * FROM `mod_dsm_pool` WHERE `pool_name` = '" . $pool_name . "'");
    if ($dbResult->rowCount() == 0) {
        return 0;
    } else {
        return 1;
    }
}

/**
 *
 * Duplicate Pool
 * @param $select
 * @param $nbrDup
 */
function multiplePoolInDB($pool = array(), $nbrDup = array())
{
    global $pearDB;

    foreach ($pool as $key => $value) {
        $dbResult = $pearDB->query("SELECT * FROM `mod_dsm_pool` WHERE `pool_id` = '" . $key . "' LIMIT 1");

        $row = $dbResult->fetch();
        $row["pool_id"] = null;

        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = null;

            foreach ($row as $key2 => $value2) {
                $key2 == "pool_name" ? ($pool_name = $value2 = $value2 . "_" . $i) : null;
                if ($key2 == 'pool_host_id') {
                    $value2 = null;
                } elseif ($key2 == 'pool_activate') {
                    $value2 = '0';
                }
                $val ? $val .= (
                $value2 != null ? (", '" . $pearDB->escape($value2) . "'") : ", NULL"
                ) : $val .= (
                $value2 != null ? ("'" . $pearDB->escape($value2) . "'") : "NULL"
                );
                if ($key2 != "pool_id") {
                    $fields[$key2] = $pearDB->escape($value2);
                }
                if (isset($pool_name)) {
                    $fields["pool_name"] = $pool_name . "_$i";
                }
            }

            if (isset($pool_name) && !testPoolExistence($pool_name)) {
                $val ? $rq = "INSERT INTO `mod_dsm_pool` VALUES (" . $val . ")" : $rq = null;
                $dbResult = $pearDB->query($rq);
                $dbResult = $pearDB->query("SELECT MAX(pool_id) FROM `mod_dsm_pool`");
                $cmd_id = $dbResult->fetch();
            }
        }
    }
}

/**
 *
 * Generate Slot services for pool
 * @param $prefix
 * @param $number
 * @param $host_id
 * @param $template
 * @param $cmd
 * @param $args
 * @param $oldPrefix
 */
function generateServices($prefix, $number, $host_id, $template, $cmd, $args, $oldPrefix)
{
    global $pearDB;

    if (!isset($oldPrefix)) {
        $oldPrefix = "213343434334343434343";
    }

    $dbResult = $pearDB->query(
        "SELECT service_id, service_description " .
        "FROM service s, host_service_relation hsr " .
        "WHERE hsr.host_host_id = '" . $host_id . "' " .
        "AND service_id = service_service_id " .
        "AND service_description LIKE '" . $oldPrefix . "%' ORDER BY service_description ASC"
    );
    $currentNumber = $dbResult->rowCount();
    if ($currentNumber == 0) {
        for ($i = 1; $i <= $number; $i++) {
            $suffix = "";
            for ($t = $i; $t < 1000; $t *= 10) {
                $suffix .= "0";
            }
            $suffix .= $i;
            $pearDB->query(
                "INSERT INTO service (
                    service_description,
                    service_template_model_stm_id,
                    command_command_id,
                    command_command_id_arg,
                    service_activate,
                    service_register,
                    service_active_checks_enabled,
                    service_passive_checks_enabled,
                    service_parallelize_check,
                    service_obsess_over_service,
                    service_check_freshness,
                    service_event_handler_enabled,
                    service_process_perf_data,
                    service_retain_status_information,
                    service_notifications_enabled,
                    service_is_volatile
                ) VALUES ('" .
                $prefix . $suffix .
                "', '" . $template .
                "', " . ($cmd ? "'$cmd'" : "NULL") .
                ", " . ($args ? "'$args'" : "NULL") .
                ", '1', '1', '0', '1', '2', '2', '2', '2', '2', '2', '2', '2'
                )"
            );

            $dbResult = $pearDB->query(
                "SELECT MAX(service_id)
                FROM service
                WHERE service_description = '" . $prefix . $suffix . "'
                AND service_activate = '1' AND service_register = '1'"
            );
            $service = $dbResult->fetch();
            $service_id = $service["MAX(service_id)"];

            if ($service_id != 0) {
                $pearDB->query(
                    "INSERT INTO host_service_relation (
                        service_service_id, host_host_id
                    ) VALUES ('" . $service_id . "', '" . $host_id . "')"
                );
                $pearDB->query(
                    "INSERT INTO extended_service_information (service_service_id) VALUES ('" . $service_id . "')"
                );
            }
        }
    } elseif ($currentNumber <= $number) {
        for ($i = 1; $data = $dbResult->fetch(); $i++) {
            $suffix = "";
            for ($t = $i; $t < 1000; $t *= 10) {
                $suffix .= "0";
            }
            $suffix .= $i;
            $pearDB->query(
                "UPDATE service SET
                service_template_model_stm_id = '" . $template . "',
                service_description = '" . $prefix . $suffix . "',
                command_command_id = " . ($cmd ? "'$cmd'" : "NULL") . ",
                command_command_id_arg = " . ($args ? "'$args'" : "NULL") . "
                WHERE service_id = '" . $data["service_id"] . "'"
            );
            $pearDB->query(
                "DELETE FROM host_service_relation WHERE service_service_id = '" . $data["service_id"] . "'"
            );
            $pearDB->query(
                "INSERT INTO host_service_relation (
                    service_service_id, host_host_id
                ) VALUES (
                    '" . $data["service_id"] . "', '" . $host_id . "'
                )"
            );
        }
        while ($i <= $number) {
            $suffix = "";
            for ($t = $i; $t < 1000; $t *= 10) {
                $suffix .= "0";
            }
            $suffix .= $i;
            $pearDB->query(
                "INSERT INTO service (
                    service_description,
                    service_template_model_stm_id,
                    command_command_id,
                    command_command_id_arg,
                    service_activate,
                    service_register,
                    service_active_checks_enabled,
                    service_passive_checks_enabled,
                    service_parallelize_check,
                    service_obsess_over_service,
                    service_check_freshness,
                    service_event_handler_enabled,
                    service_process_perf_data,
                    service_retain_status_information,
                    service_notifications_enabled,
                    service_is_volatile
                ) VALUES (
                    '" . $prefix . $suffix . "',
                    '" . $template . "',
                    " . ($cmd ? "'$cmd'" : "NULL") . ",
                    " . ($args ? "'$args'" : "NULL") . ",
                    '1', '1', '0', '1', '2', '2', '2', '2', '2', '2', '2', '2'
                )"
            );

            $dbResult = $pearDB->query(
                "SELECT MAX(service_id)
                FROM service
                WHERE service_description = '" . $prefix . $suffix . "'
                AND service_activate = '1'
                AND service_register = '1'"
            );
            $service = $dbResult->fetch();
            $service_id = $service["MAX(service_id)"];

            if ($service_id != 0) {
                $pearDB->query(
                    "INSERT INTO host_service_relation (
                        service_service_id, host_host_id
                    ) VALUES ('" . $service_id . "', '" . $host_id . "')"
                );

                $pearDB->query(
                    "INSERT INTO extended_service_information (
                        service_service_id
                    ) VALUES ('" . $service_id . "')"
                );
            }
            $i++;
        }
    } elseif ($currentNumber > $number) {
        for ($i = 1; $data = $dbResult->fetch(); $i++) {
            if ($i > $number) {
                $pearDB->query("DELETE FROM service WHERE service_id = '" . $data["service_id"] . "'");
            }
        }
    }
}

/**
 * Insert Pool
 *
 * @param array $ret The values for new pool
 * @return int The pool id
 */
function insertPool($ret = array())
{
    global $form, $pearDB;

    if (!count($ret)) {
        $ret = $form->getSubmitValues();
    }

    if (hostPoolPrefixUsed($ret['pool_host_id'], $ret['pool_prefix'])) {
        throw new Exception(_('Hosts is already use that pool prefix'));
    }

    $rq = "INSERT INTO `mod_dsm_pool` (
        `pool_id`,
        `pool_name`,
        `pool_host_id`,
        `pool_description`,
        `pool_number`,
        `pool_prefix`,
        `pool_cmd_id`,
        `pool_args`,
        `pool_activate`,
        `pool_service_template_id`
    ) VALUES (
        NULL, ";
    isset($ret["pool_name"])
    && $ret["pool_name"] != null
        ? $rq .= "'" . $pearDB->escape($ret["pool_name"]) . "', " : $rq .= "NULL, ";
    isset($ret["pool_host_id"])
    && $ret["pool_host_id"] != null
        ? $rq .= "'" . $ret["pool_host_id"] . "', " : $rq .= "NULL, ";
    isset($ret["pool_description"])
    && $ret["pool_description"] != null
        ? $rq .= "'" . $pearDB->escape($ret["pool_description"]) . "', " : $rq .= "NULL, ";
    isset($ret["pool_number"])
    && $ret["pool_number"] != null
        ? $rq .= "'" . $ret["pool_number"] . "', " : $rq .= "NULL, ";
    isset($ret["pool_prefix"])
    && $ret["pool_prefix"] != null
        ? $rq .= "'" . $ret["pool_prefix"] . "', " : $rq .= "NULL, ";
    isset($ret["pool_cmd_id"])
    && $ret["pool_cmd_id"] != null
        ? $rq .= "'" . $ret["pool_cmd_id"] . "', " : $rq .= "NULL, ";
    isset($ret["pool_args"])
    && $ret["pool_args"] != null
        ? $rq .= "'" . $pearDB->escape($ret["pool_args"]) . "', " : $rq .= "NULL, ";
    isset($ret["pool_activate"]["pool_activate"])
    && $ret["pool_activate"]["pool_activate"] != null
        ? $rq .= "'" . $ret["pool_activate"]["pool_activate"] . "', " : $rq .= "NULL, ";
    isset($ret["pool_service_template_id"])
    && $ret["pool_service_template_id"] != null
        ? $rq .= "'" . $ret["pool_service_template_id"] . "' " : $rq .= "NULL ";
    $rq .= ")";

    /*
        * Generate all services
        */
    generateServices(
        $ret["pool_prefix"],
        $ret["pool_number"],
        $ret["pool_host_id"],
        $ret["pool_service_template_id"],
        $ret["pool_cmd_id"],
        $ret["pool_args"],
        "kjqsddlqkjdqslkjdqsldkj"
    );

    $dbResult = $pearDB->query($rq);
    $dbResult = $pearDB->query("SELECT MAX(pool_id) FROM mod_dsm_pool");
    $pool_id = $dbResult->fetch();

    if ($ret["pool_activate"]["pool_activate"] == 1) {
        enablePoolInDB($pool_id["MAX(pool_id)"]);
    } else {
        disablePoolInDB($pool_id["MAX(pool_id)"]);
    }

    return ($pool_id["MAX(pool_id)"]);
}

/**
 * Update Pool
 *
 * @param int $pool_id The pool ID
 * @return bool
 */
function updatePool($pool_id = null)
{
    global $form, $pearDB;

    if (!$pool_id) {
        return false;
    }

    /*
     * Get Old Prefix
     */
    $dbResult = $pearDB->query("SELECT pool_prefix FROM mod_dsm_pool WHERE pool_id = '" . $pool_id . "'");
    $data = $dbResult->fetch();
    $oldPrefix = $data["pool_prefix"];

    $ret = array();
    $ret = $form->getSubmitValues();

    /*
     * Validate if host is not already use
     */
    if (hostPoolPrefixUsed($ret['pool_host_id'], $ret['pool_prefix'], $pool_id)) {
        throw new Exception(_('Hosts is already use that pool prefix'));
    }

    $rq = "UPDATE mod_dsm_pool SET
        pool_name = ";
    isset($ret["pool_name"])
    && $ret["pool_name"] != null
        ? $rq .= "'" . $pearDB->escape($ret["pool_name"]) . "', " : $rq .= "NULL, ";
    $rq .= "pool_description = ";
    isset($ret["pool_description"])
    && $ret["pool_description"] != null
        ? $rq .= "'" . $pearDB->escape($ret["pool_description"]) . "', " : $rq .= "NULL, ";
    $rq .= "pool_host_id = ";
    isset($ret["pool_host_id"])
    && $ret["pool_host_id"] != null
        ? $rq .= "'" . $ret["pool_host_id"] . "', " : $rq .= "NULL, ";
    $rq .= "pool_number = ";
    isset($ret["pool_number"])
    && $ret["pool_number"] != null
        ? $rq .= "'" . $ret["pool_number"] . "', " : $rq .= "NULL, ";
    $rq .= "pool_prefix = ";
    isset($ret["pool_prefix"])
    && $ret["pool_prefix"] != null
        ? $rq .= "'" . $ret["pool_prefix"] . "', " : $rq .= "NULL, ";
    $rq .= "pool_cmd_id = ";
    isset($ret["pool_cmd_id"])
    && $ret["pool_cmd_id"] != null
        ? $rq .= "'" . $ret["pool_cmd_id"] . "', " : $rq .= "NULL, ";
    $rq .= "pool_args = ";
    isset($ret["pool_args"])
    && $ret["pool_args"] != null
        ? $rq .= "'" . $pearDB->escape($ret["pool_args"]) . "', " : $rq .= "NULL, ";
    $rq .= "pool_activate = ";
    isset($ret["pool_activate"]["pool_activate"])
    && $ret["pool_activate"]["pool_activate"] != null
        ? $rq .= "'" . $ret["pool_activate"]["pool_activate"] . "', " : $rq .= "NULL, ";
    $rq .= "pool_service_template_id = ";
    isset($ret["pool_service_template_id"])
    && $ret["pool_service_template_id"] != null
        ? $rq .= "'" . $ret["pool_service_template_id"] . "' " : $rq .= "NULL ";
    $rq .= "WHERE pool_id = '" . $pool_id . "'";
    $dbResult = $pearDB->query($rq);

    generateServices(
        $ret["pool_prefix"],
        $ret["pool_number"],
        $ret["pool_host_id"],
        $ret["pool_service_template_id"],
        $ret["pool_cmd_id"],
        $ret["pool_args"],
        $oldPrefix
    );

    if ($ret["pool_activate"]["pool_activate"] == 1) {
        enablePoolInDB($pool_id);
    } else {
        disablePoolInDB($pool_id);
    }

    return true;
}

/**
 *
 * Update Pool ContactGroups
 * @param $ret
 */
function updatePoolContactGroup($pool_id = null, $ret = array())
{
    global $form, $pearDB;

    if (!$pool_id) {
        return;
    }

    $pearDB->query("DELETE FROM mod_dsm_cg_relation WHERE pool_id = '" . $pool_id . "'");

    (isset($ret["pool_cg"])) ? $ret = $ret["pool_cg"] : $ret = $form->getSubmitValue("pool_cg");

    for ($i = 0; $i < count($ret); $i++) {
        $pearDB->query(
            "INSERT INTO mod_dsm_cg_relation (
                pool_id, cg_cg_id
            ) VALUES (
                '" . $pool_id . "', '" . $ret[$i] . "'
            )"
        );
    }
}

/**
 *
 * Update Pool Contacts
 * @param $ret
 */
function updatePoolContact($pool_id = null, $ret = array())
{
    global $form, $pearDB;

    if (!$pool_id) {
        return;
    }

    $pearDB->query("DELETE FROM mod_dsm_cct_relation WHERE pool_id = '" . $pool_id . "'");

    (isset($ret["pool_cct"])) ? $ret = $ret["pool_cct"] : $ret = $form->getSubmitValue("pool_cct");

    for ($i = 0; $i < count($ret); $i++) {
        $pearDB->query(
            "INSERT INTO mod_dsm_cct_relation (
                pool_id, cct_cct_id
            ) VALUES (
                '" . $pool_id . "', '" . $ret[$i] . "'
            )"
        );
    }
}
