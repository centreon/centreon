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

use App\Kernel;
Use Centreon\Domain\Log\Logger;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Utility\Interfaces\UUIDGeneratorInterface;

require_once _CENTREON_PATH_ . 'www/include/common/vault-functions.php';

/**
 * For ACL
 *
 * @param CentreonDB $db
 * @param int $hostId
 * @return null
 */
function setHostChangeFlag($db, $hostId = null, $hostgroupId = null)
{
    if (isset($hostId)) {
        $table = "acl_resources_host_relations";
        $field = "host_host_id";
        $val = $hostId;
    } elseif (isset($hostgroupId)) {
        $table = "acl_resources_hg_relations";
        $field = "hg_hg_id";
        $val = $hostgroupId;
    } else {
        return null;
    }
    $query = "UPDATE acl_resources SET changed = 1 " .
        "WHERE acl_res_id IN (" .
        "SELECT acl_res_id FROM $table WHERE $field = :fieldValue)";
    $statement = $db->prepare($query);
    $statement->bindValue(':fieldValue', (int) $val, \PDO::PARAM_INT);
    $statement->execute();
    return null;
}

/**
 * Quickform rule that checks whether or not reserved macro are used
 *
 * @return bool
 */
function serviceMacHandler()
{
    global $pearDB;

    $macArray = $_POST;
    $macTab = array();
    foreach ($macArray as $key => $value) {
        if (isset($value) && is_string($value) && preg_match('/^macroInput/', $key, $matches)) {
            $macTab[] = "'\$_SERVICE" . strtoupper($value) . "\$'";
        }
    }
    if (count($macTab)) {
        $sql = "SELECT count(*) as nb FROM nagios_macro WHERE macro_name IN (" . implode(',', $macTab) . ")";
        $res = $pearDB->query($sql);
        $row = $res->fetch();
        if (isset($row['nb']) && $row['nb']) {
            return false;
        }
    }
    return true;
}

/**
 * This is a quickform rule for checking if all the argument fields are filled
 *
 * @return bool
 */
function argHandler()
{
    $argArray = $_POST;
    $argTab = array();
    foreach ($argArray as $key => $value) {
        if (preg_match('/^ARG(\d+)/', $key, $matches)) {
            $argTab[$matches[1]] = $value;
        }
    }
    $fill = false;
    $nofill = false;
    foreach ($argTab as $val) {
        if ($val != "") {
            $fill = true;
        } else {
            $nofill = true;
        }
    }
    if ($fill === true && $nofill === true) {
        return false;
    }
    return true;
}

/**
 * Returns the formatted string for command arguments
 *
 * @param $argArray
 * @return string
 */
function getCommandArgs($argArray = array(), $conf = array())
{
    if (isset($conf['command_command_id_arg'])) {
        return $conf['command_command_id_arg'];
    }
    $argTab = array();
    foreach ($argArray as $key => $value) {
        if (preg_match('/^ARG(\d+)/', $key, $matches)) {
            $argTab[$matches[1]] = $value;
            $argTab[$matches[1]] = str_replace("\n", "#BR#", $argTab[$matches[1]]);
            $argTab[$matches[1]] = str_replace("\t", "#T#", $argTab[$matches[1]]);
            $argTab[$matches[1]] = str_replace("\r", "#R#", $argTab[$matches[1]]);
        }
    }
    ksort($argTab);
    $str = "";
    foreach ($argTab as $val) {
        if ($val != "") {
            $str .= "!" . $val;
        }
    }
    if (!strlen($str)) {
        return null;
    }
    return $str;
}

function getHostServiceCombo($service_id = null, $service_description = null)
{
    global $pearDB;
    if ($service_id == null || $service_description == null) {
        return;
    }

    $query = "SELECT h.host_name " .
        "FROM host h, host_service_relation hsr " .
        "WHERE h.host_id = hsr.host_host_id " .
        "AND hsr.service_service_id = '" . $service_id . "' LIMIT 1";
    $DBRES = $pearDB->query($query);

    if (!$DBRES->rowCount()) {
        $combo = "- / " . $service_description;
    } else {
        $row = $DBRES->fetch();
        $combo = $row['host_name'] . " / " . $service_description;
    }

    return $combo;
}

function serviceExists($name = null)
{
    global $pearDB, $centreon;

    $query = "SELECT service_description FROM service " .
        "WHERE service_description = '" . CentreonDB::escape($centreon->checkIllegalChar($name)) . "'";
    $dbResult = $pearDB->query($query);
    if ($dbResult->rowCount() >= 1) {
        return true;
    }
    return false;
}

/**
 * Test service template existence
 *
 * @param string $name
 * @param bool $returnId | whether function will return an id instead of boolean
 * @return mixed
 */
function testServiceTemplateExistence($name = null, $returnId = false)
{
    global $pearDB, $form, $centreon;

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('service_id');
    }

    $query = "SELECT service_description, service_id FROM service " .
        "WHERE service_register = '0' " .
        "AND service_description = '" . CentreonDB::escape($centreon->checkIllegalChar($name)) . "'";
    $dbResult = $pearDB->query($query);
    $service = $dbResult->fetch();
    $nbRows = $dbResult->rowCount();
    //Modif case
    if (isset($id)) {
        if ($nbRows >= 1 && $service["service_id"] == $id) {
            return true;
        } elseif ($nbRows >= 1 && $service["service_id"] != $id) { //Duplicate entry
            return false;
        } else {
            return true;
        }
    } else {
        if ($nbRows >= 1) {
            return false;
        } else {
            return true;
        }
    }
}

/**
 * Test service existence
 *
 * @param string $name
 * @param array $hPars
 * @param array $hgPars
 * @param bool $returnId | whether function will return an id instead of boolean
 * @param array $params
 * @return mixed
 */
function testServiceExistence($name = null, $hPars = array(), $hgPars = array(), $returnId = false, $params = array())
{
    global $pearDB, $centreon;
    global $form;

    $id = null;
    $hPars = (is_array($hPars) || $hPars instanceof Countable) ? $hPars : [];
    $hgPars = (is_array($hgPars) || $hgPars instanceof Countable) ? $hgPars : [];

    if (isset($form) && !count($hPars) && !count($hgPars)) {
        if (count($params)) {
            $arr = $params;
        } else {
            $arr = $form->getSubmitValues();
        }
        if (isset($arr["service_id"])) {
            $id = $arr["service_id"];
        }
        if (isset($arr["service_hPars"])) {
            $hPars = $arr["service_hPars"];
        } else {
            $hPars = array();
        }
        if (isset($arr["service_hgPars"])) {
            $hgPars = $arr["service_hgPars"];
        } else {
            $hgPars = array();
        }
    }

    $escapeName = CentreonDB::escape($centreon->checkIllegalChar($name));

    $statement = $pearDB->prepare(
        <<<'SQL'
                SELECT service.service_id
                FROM service
                INNER JOIN host_service_relation hsr
                    ON hsr.service_service_id = service.service_id
                WHERE hsr.host_host_id = :host_id
                    AND service.service_description = :service_description
                SQL
    );
    foreach ($hPars as $hostId) {
        $statement->bindValue(':host_id', (int) $hostId, \PDO::PARAM_INT);
        $statement->bindValue(':service_description', $escapeName);
        $statement->execute();
        $service = $statement->fetch(\PDO::FETCH_ASSOC);
        #Duplicate entry
        if ($statement->rowCount() >= 1 && $service["service_id"] != $id) {
            return (false == $returnId) ? false : $service['service_id'];
        }
    }

    $statement = $pearDB->prepare(
        <<<'SQL'
            SELECT service_id
            FROM service
            INNER JOIN host_service_relation hsr
                ON hsr.service_service_id = service_id
            WHERE hsr.hostgroup_hg_id = :hostgroup_hg_id
                AND service.service_description = :service_description
            SQL
    );
    foreach ($hgPars as $hostGroupId) {
        $statement->bindValue(':hostgroup_hg_id', (int) $hostGroupId, \PDO::PARAM_INT);
        $statement->bindValue(':service_description', $escapeName);
        $statement->execute();
        $service = $statement->fetch(\PDO::FETCH_ASSOC);
        #Duplicate entry
        if ($statement->rowCount() >= 1 && $service["service_id"] != $id) {
            return (false == $returnId) ? false : $service['service_id'];
        }
    }

    return (false == $returnId) ? true : 0;
}

/**
 * Get service id by combination of host or hostgroup relations
 *
 * @param string $serviceDescription
 * @param array $hPars
 * @param array $hgPars
 * @return int
 */
function getServiceIdByCombination($serviceDescription, $hPars = array(), $hgPars = array(), $params = array())
{
    if (!count($hPars) && !count($hgPars)) {
        return testServiceTemplateExistence($serviceDescription, true);
    }
    return testServiceExistence($serviceDescription, $hPars, $hgPars, true, $params);
}

function enableServiceInDB($service_id = null, $service_arr = array())
{
    if (!$service_id && !count($service_arr)) {
        return;
    }
    global $pearDB, $centreon;
    if ($service_id) {
        $service_arr = array($service_id => "1");
    }

    $updateStatement = $pearDB->prepare("UPDATE service SET service_activate = '1' WHERE service_id = :serviceId");
    $selectStatement = $pearDB->prepare(
        "SELECT service_description FROM `service` WHERE service_id = :serviceId LIMIT 1"
    );
    foreach (array_keys($service_arr) as $serviceId) {
        $updateStatement->bindValue(':serviceId', $serviceId, \PDO::PARAM_INT);
        $updateStatement->execute();

        $selectStatement->bindValue(':serviceId', $serviceId, \PDO::PARAM_INT);
        $selectStatement->execute();
        $serviceDescription = $selectStatement->fetchColumn();

        signalConfigurationChange('service', (int) $serviceId);
        $centreon->CentreonLogAction->insertLog("service", $serviceId, $serviceDescription, "enable");
    }
}

function disableServiceInDB($service_id = null, $service_arr = array())
{
    if (!$service_id && !count($service_arr)) {
        return;
    }
    global $pearDB, $centreon;
    if ($service_id) {
        $service_arr = array($service_id => "1");
    }
    foreach (array_keys($service_arr) as $serviceId) {
        $pearDB->query("UPDATE service SET service_activate = '0' WHERE service_id = '" . $serviceId . "'");
        $query = "SELECT service_description FROM `service` WHERE service_id = '" . $serviceId . "' LIMIT 1";
        $dbResult2 = $pearDB->query($query);
        $row = $dbResult2->fetch();

        signalConfigurationChange('service', (int) $serviceId, [], false);
        $centreon->CentreonLogAction->insertLog("service", $serviceId, $row['service_description'], "disable");
    }
}

/**
 * @param int $serviceId
 */
function removeRelationLastServiceDependency(int $serviceId): void
{
    global $pearDB;

    $request = <<<SQL
        SELECT
            COUNT(dependency_dep_id) AS nb_dependency,
            dependency_dep_id AS id
        FROM dependency_serviceParent_relation
        WHERE dependency_dep_id = (
            SELECT dependency_dep_id
            FROM dependency_serviceParent_relation
            WHERE service_service_id = {$serviceId}
        )
        GROUP BY dependency_dep_id
    SQL;

    $statement = $pearDB->query($request);
    if (false !== ($result = $statement->fetch())) {
        //is last parent
        if ($result['nb_dependency'] == 1) {
            $pearDB->query("DELETE FROM dependency WHERE dep_id = " . $result['id']);
        }
    }
}

function deleteServiceInDB($services = array())
{
    global $pearDB, $centreon;

    $serviceIds = array_keys($services);
    $kernel = Kernel::createForWeb();
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    /** @var WriteVaultRepositoryInterface $writeVaultRepository */
    $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
    if ($vaultConfiguration !== null) {
        deleteResourceSecretsInVault($writeVaultRepository, [], $serviceIds);
    }

    $query = 'UPDATE service SET service_template_model_stm_id = NULL WHERE service_id = :service_id';
    $statement = $pearDB->prepare($query);
    foreach ($serviceIds as $serviceId) {
        $previousPollerIds = getPollersForConfigChangeFlagFromServiceId($serviceId);
        removeRelationLastServiceDependency((int)$serviceId);
        $query = "SELECT service_id FROM service WHERE service_template_model_stm_id = '" . $serviceId . "'";
        $dbResult = $pearDB->query($query);
        while ($row = $dbResult->fetch()) {
            $statement->bindValue(':service_id', (int) $row["service_id"], \PDO::PARAM_INT);
            $statement->execute();
        }
        $query = "SELECT service_description FROM `service` WHERE `service_id` = '" . $serviceId . "' LIMIT 1";
        $dbResult3 = $pearDB->query($query);
        $svcname = $dbResult3->fetch();
        $centreon->CentreonLogAction->insertLog("service", $serviceId, $svcname['service_description'], "d");
        $pearDB->query("DELETE FROM service WHERE service_id = '" . $serviceId . "'");
        $pearDB->query("DELETE FROM on_demand_macro_service WHERE svc_svc_id = '" . $serviceId . "'");
        $pearDB->query("DELETE FROM contact_service_relation WHERE service_service_id = '" . $serviceId . "'");

        signalConfigurationChange('service', (int) $serviceId, $previousPollerIds);
    }
    $centreon->user->access->updateACL(array("type" => 'SERVICE', 'id' => $serviceId, "action" => "DELETE"));
}

function divideGroupedServiceInDB($service_id = null, $service_arr = array(), $toHost = null)
{
    global $pearDB, $pearDBO;

    if (!$service_id && !count($service_arr)) {
        return;
    }

    if ($service_id) {
        $service_arr = array($service_id => "1");
    }


    foreach ($service_arr as $key => $value) {
        $query = "SELECT count(host_host_id) as nbHost, count(hostgroup_hg_id) as nbHG FROM host_service_relation " .
            "WHERE service_service_id = '" . $key . "'";
        $dbResult = $pearDB->query($query);
        $res = $dbResult->fetch();

        if ($res["nbHost"] != 0 && $res["nbHG"] == 0) {
            divideHostsToHost($key);
        } else {
            if ($toHost) {
                divideHostGroupsToHost($key);
            } else {
                divideHostGroupsToHostGroup($key);
            }
        }

        /*
         * Delete old links for servicegroups
         */
        $pearDB->query('DELETE FROM servicegroup_relation WHERE service_service_id = ' . $key);

        // Flag service to delete
        $svcToDelete[$key] = 1;
    }

    // Purge Old Service
    foreach ($svcToDelete as $svc_id => $flag) {
        $pearDB->query("DELETE FROM service WHERE service_id = '" . $svc_id . "'");
        $pearDB->query("DELETE FROM host_service_relation WHERE service_service_id = '" . $svc_id . "'");
    }
}

function divideHostGroupsToHostGroup($service_id)
{
    global $pearDB, $pearDBO;

    $query = "SELECT hostgroup_hg_id FROM host_service_relation " .
        "WHERE service_service_id = '" . $service_id . "' AND hostgroup_hg_id IS NOT NULL";
    $dbResult3 = $pearDB->query($query);
    $query = "UPDATE index_data
              SET service_id = :sv_id
              WHERE host_id = :host_id AND service_id = :service_id";
    $statement = $pearDBO->prepare($query);
    while ($data = $dbResult3->fetch()) {
        $sv_id = multipleServiceInDB(
            array($service_id => "1"),
            array($service_id => "1"),
            null,
            0,
            $data["hostgroup_hg_id"],
            array(),
            array()
        );
        $hosts = getMyHostGroupHosts($data["hostgroup_hg_id"]);
        foreach ($hosts as $host_id) {
            $statement->bindValue(':sv_id', (int) $sv_id, \PDO::PARAM_INT);
            $statement->bindValue(':host_id', (int) $host_id, \PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
            $statement->execute();
            setHostChangeFlag($pearDB, $host_id, null);
        }
    }
    $dbResult3->closeCursor();
}

function divideHostGroupsToHost($service_id)
{
    global $pearDB, $pearDBO;

    $dbResult = $pearDB->query("SELECT * FROM host_service_relation WHERE service_service_id = '" . $service_id . "'");
    $query = "UPDATE index_data
              SET service_id = :sv_id
              WHERE host_id = :host_id AND service_id = :service_id";
    $statement = $pearDBO->prepare($query);
    while ($relation = $dbResult->fetch()) {
        $hosts = getMyHostGroupHosts($relation["hostgroup_hg_id"]);

        foreach ($hosts as $host_id) {
            $sv_id = multipleServiceInDB(
                array($service_id => "1"),
                array($service_id => "1"),
                $host_id,
                0,
                null,
                array(),
                array($relation["hostgroup_hg_id"] => null)
            );
            $statement->bindValue(':sv_id', (int) $sv_id, \PDO::PARAM_INT);
            $statement->bindValue(':host_id', (int) $host_id, \PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
            $statement->execute();
            setHostChangeFlag($pearDB, $host_id, null);
        }
    }
    $dbResult->closeCursor();
}

function divideHostsToHost($service_id)
{
    global $pearDB, $pearDBO;

    $dbResult = $pearDB->query("SELECT * FROM host_service_relation WHERE service_service_id = '" . $service_id . "'");
    $query = "UPDATE index_data SET service_id = :sv_id WHERE host_id = :host_id AND service_id = :service_id";
    $statement = $pearDBO->prepare($query);
    while ($relation = $dbResult->fetch()) {
        $sv_id = multipleServiceInDB(
            array($service_id => "1"),
            array($service_id => "1"),
            $relation["host_host_id"],
            0,
            null,
            array(),
            array($relation["hostgroup_hg_id"] => null)
        );
        $statement->bindValue(':sv_id', (int) $sv_id, \PDO::PARAM_INT);
        $statement->bindValue(':host_id', (int) $relation["host_host_id"], \PDO::PARAM_INT);
        $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
        $statement->execute();
        setHostChangeFlag($pearDB, $relation["host_host_id"], null);
    }
}

function multipleServiceInDB(
    $services = array(),
    $nbrDup = array(),
    $host = null,
    $descKey = 1,
    $hostgroup = null,
    $hPars = array(),
    $hgPars = array(),
    $params = array()
) {
    global $pearDB, $centreon;

    /* $descKey param is a flag. If 1, we know we have to rename description because it's a traditionnal
     duplication. If 0, we don't have to, beacause we duplicate services for an Host duplication */
    // Foreach Service
    $maxId["MAX(service_id)"] = null;

    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    foreach ($services as $key => $value) {
        // Get all information about it
        $dbResult = $pearDB->query("SELECT * FROM service WHERE service_id = '" . $key . "' LIMIT 1");
        $row = $dbResult->fetch();
        $row["service_id"] = null;

        // Loop on the number of Service we want to duplicate
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = null;

            // Create a sentence which contains all the value
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                if ($key2 == "service_description" && $descKey) {
                    $service_description = $value2 = $value2 . "_" . $i;
                } elseif ($key2 == "service_description") {
                    $service_description = null;
                }
                $val ? $val .=
                    ($value2 != null ? (", '" . $pearDB->escape($value2) . "'") : ", NULL")
                    : $val .= ($value2 != null ? ("'" . $pearDB->escape($value2) . "'") : "NULL");
                if ($key2 != "service_id") {
                    $fields[$key2] = $value2;
                }
                if (isset($service_description)) {
                    $fields["service_description"] = $service_description;
                }
            }

            if (!count($hPars)) {
                $hPars = getMyServiceHosts($key);
            }
            if (!count($hgPars)) {
                $hgPars = getMyServiceHostGroups($key);
            }

            if (
                ($row["service_register"] && testServiceExistence($service_description, $hPars, $hgPars, $params))
                || (!$row["service_register"] && testServiceTemplateExistence($service_description))
            ) {
                $hPars = array();
                $hgPars = array();
                (isset($val) && $val != "NULL" && $val)
                    ? $rq = "INSERT INTO service VALUES (" . $val . ")"
                    : $rq = null;
                if (isset($rq)) {
                    $dbResult = $pearDB->query($rq);
                    $dbResult = $pearDB->query("SELECT MAX(service_id) FROM service");
                    $maxId = $dbResult->fetch();
                    if (isset($maxId["MAX(service_id)"])) {
                        // Host duplication case -> Duplicate the Service for the Host we create
                        if ($host) {
                            $query = "INSERT INTO host_service_relation
                                      VALUES (NULL, NULL, :host_id, NULL, :service_id)";
                            $statement = $pearDB->prepare($query);
                            $statement->bindValue(':host_id', (int) $host, \PDO::PARAM_INT);
                            $statement->bindValue(':service_id', (int) $maxId["MAX(service_id)"], \PDO::PARAM_INT);
                            $statement->execute();
                            setHostChangeFlag($pearDB, $host, null);
                        } elseif ($hostgroup) {
                            $query = "INSERT INTO host_service_relation
                                      VALUES (NULL, :hostgroup_id, NULL,
                                              NULL, :service_id)";
                            $statement = $pearDB->prepare($query);
                            $statement->bindValue(':hostgroup_id', (int) $hostgroup, \PDO::PARAM_INT);
                            $statement->bindValue(':service_id', (int) $maxId["MAX(service_id)"], \PDO::PARAM_INT);
                            $statement->execute();
                            setHostChangeFlag($pearDB, null, $hostgroup);
                        } else {
                            // Service duplication case -> Duplicate the Service for each relation the base Service have
                            $query = "SELECT DISTINCT host_host_id, hostgroup_hg_id FROM host_service_relation " .
                                "WHERE service_service_id = '" . $key . "'";
                            $dbResult = $pearDB->query($query);
                            $fields["service_hPars"] = "";
                            $fields["service_hgPars"] = "";
                            $query = "INSERT INTO host_service_relation
                                      VALUES (NULL, :hostgroup_hg_id, :host_host_id,
                                              NULL, :service_id)";
                            $statement = $pearDB->prepare($query);
                            while ($service = $dbResult->fetch()) {
                                if ($service["host_host_id"]) {
                                    $statement->bindValue(
                                        ':hostgroup_hg_id',
                                        null,
                                        \PDO::PARAM_NULL
                                    );
                                    $statement->bindValue(
                                        ':host_host_id',
                                        (int) $service["host_host_id"],
                                        \PDO::PARAM_INT
                                    );
                                    $statement->bindValue(
                                        ':service_id',
                                        (int) $maxId["MAX(service_id)"],
                                        \PDO::PARAM_INT
                                    );
                                    $statement->execute();
                                    setHostChangeFlag($pearDB, $service['host_host_id'], null);
                                    $fields["service_hPars"] .= $service["host_host_id"] . ",";
                                } elseif ($service["hostgroup_hg_id"]) {
                                    $statement->bindValue(
                                        ':hostgroup_hg_id',
                                        (int) $service["hostgroup_hg_id"],
                                        \PDO::PARAM_INT
                                    );
                                    $statement->bindValue(
                                        ':host_host_id',
                                        null,
                                        \PDO::PARAM_NULL
                                    );
                                    $statement->bindValue(
                                        ':service_id',
                                        (int) $maxId["MAX(service_id)"],
                                        \PDO::PARAM_INT
                                    );
                                    $statement->execute();
                                    setHostChangeFlag($pearDB, null, $service["hostgroup_hg_id"]);
                                    $fields["service_hgPars"] .= $service["hostgroup_hg_id"] . ",";
                                }
                            }
                            $fields["service_hPars"] = trim($fields["service_hPars"], ",");
                            $fields["service_hgPars"] = trim($fields["service_hgPars"], ",");
                        }

                        /*
                         * Contact duplication
                         */
                        $query = "SELECT DISTINCT contact_id FROM contact_service_relation " .
                            "WHERE service_service_id = '" . $key . "'";
                        $dbResult = $pearDB->query($query);
                        $fields["service_cs"] = "";
                        $query = "INSERT INTO contact_service_relation VALUES (:service_id,:contact_id )";
                        $statement = $pearDB->prepare($query);
                        while ($C = $dbResult->fetch()) {
                            $statement->bindValue(':service_id', (int) $maxId["MAX(service_id)"], \PDO::PARAM_INT);
                            $statement->bindValue(':contact_id', (int) $C["contact_id"], \PDO::PARAM_INT);
                            $statement->execute();
                            $fields["service_cs"] .= $C["contact_id"] . ",";
                        }
                        $fields["service_cs"] = trim($fields["service_cs"], ",");

                        /*
                         * ContactGroup duplication
                         */
                        $query = "SELECT DISTINCT contactgroup_cg_id FROM contactgroup_service_relation " .
                            "WHERE service_service_id = '" . $key . "'";
                        $dbResult = $pearDB->query($query);
                        $fields["service_cgs"] = "";
                        $query = "INSERT INTO contactgroup_service_relation
                            VALUES (:contactgroup_cg_id,:service_id)";
                        $statement = $pearDB->prepare($query);
                        while ($Cg = $dbResult->fetch()) {
                            $statement->bindValue(
                                ':contactgroup_cg_id',
                                (int) $Cg["contactgroup_cg_id"],
                                \PDO::PARAM_INT
                            );
                            $statement->bindValue(':service_id', (int) $maxId["MAX(service_id)"], \PDO::PARAM_INT);
                            $statement->execute();
                            $fields["service_cgs"] .= $Cg["contactgroup_cg_id"] . ",";
                        }
                        $fields["service_cgs"] = trim($fields["service_cgs"], ",");

                        /*
                         * Servicegroup duplication
                         */
                        $query = "SELECT DISTINCT host_host_id, hostgroup_hg_id, servicegroup_sg_id FROM " .
                            "servicegroup_relation WHERE service_service_id = '" . $key . "'";
                        $dbResult = $pearDB->query($query);
                        $fields["service_sgs"] = "";
                        $query = "INSERT INTO servicegroup_relation (host_host_id, hostgroup_hg_id, " .
                                 "service_service_id, servicegroup_sg_id)
                                 VALUES (:host_host_id,:hostgroup_hg_id,:service_service_id,:servicegroup_sg_id)";
                        $statement = $pearDB->prepare($query);
                        while ($Sg = $dbResult->fetch()) {
                            if (isset($host) && $host) {
                                $host_id = $host;
                            } else {
                                $host_id = $Sg["host_host_id"] ?? null;
                            }
                            if (isset($hostgroup) && $hostgroup) {
                                $hg_id = $hostgroup;
                            } else {
                                $hg_id = $Sg["hostgroup_hg_id"] ?? null;
                            }
                            $statement->bindValue(
                                ':host_host_id',
                                $host_id,
                                \PDO::PARAM_INT
                            );
                            $statement->bindValue(
                                ':hostgroup_hg_id',
                                $hg_id,
                                \PDO::PARAM_INT
                            );
                            $statement->bindValue(
                                ':service_service_id',
                                (int) $maxId["MAX(service_id)"],
                                \PDO::PARAM_INT
                            );
                            $statement->bindValue(
                                ':servicegroup_sg_id',
                                $Sg["servicegroup_sg_id"],
                                \PDO::PARAM_INT
                            );
                            $statement->execute();
                            if ($Sg["host_host_id"]) {
                                $fields["service_sgs"] .= $Sg["host_host_id"] . ",";
                            }
                        }
                        $fields["service_sgs"] = trim($fields["service_sgs"], ",");


                        /*
                         * Trap link ducplication
                         */
                        $query = "SELECT DISTINCT traps_id FROM traps_service_relation " .
                            "WHERE service_id = '" . $key . "'";
                        $dbResult = $pearDB->query($query);

                        $fields["service_traps"] = "";
                        $query = "INSERT INTO traps_service_relation VALUES (:traps_id, :service_id)";
                        $statement = $pearDB->prepare($query);
                        while ($traps = $dbResult->fetch()) {
                            $statement->bindValue(':traps_id', (int) $traps["traps_id"], \PDO::PARAM_INT);
                            $statement->bindValue(':service_id', (int) $maxId["MAX(service_id)"], \PDO::PARAM_INT);
                            $statement->execute();
                            $fields["service_traps"] .= $traps["traps_id"] . ",";
                        }
                        $fields["service_traps"] = trim($fields["service_traps"], ",");

                        /*
                         * Extended information duplication
                         */
                        $query = "SELECT * FROM extended_service_information WHERE service_service_id = '" . $key . "'";
                        $dbResult = $pearDB->query($query);
                        while ($esi = $dbResult->fetch()) {
                            $val = null;
                            $esi["service_service_id"] = $maxId["MAX(service_id)"];
                            $esi["esi_id"] = null;
                            foreach ($esi as $key2 => $value2) {
                                $value2 = is_int($value2) ? (string) $value2 : $value2;
                                $val ? $val .=
                                    (
                                        $value2 != null
                                        ? (", '" . $pearDB->escape($value2) . "'")
                                        : ", NULL"
                                    ) : $val .= ($value2 != null ? ("'" . $pearDB->escape($value2) . "'") : "NULL");
                            }
                            $val ? $rq = "INSERT INTO extended_service_information VALUES (" . $val . ")" : $rq = null;
                            $pearDB->query($rq);
                            if ($key2 != "esi_id") {
                                $fields[$key2] = $value2;
                            }
                        }

                        /*
                         * On demand macros
                         */
                        $mTpRq1 = "SELECT * FROM `on_demand_macro_service` WHERE `svc_svc_id` ='" . $key . "'";
                        $dbResult3 = $pearDB->query($mTpRq1);
                        $macroPasswords = [];
                        while ($sv = $dbResult3->fetch()) {
                            $macName = str_replace("\$", "", $sv["svc_macro_name"]);
                            $macVal = $sv['svc_macro_value'];
                            if (!isset($sv["is_password"])) {
                                $sv["is_password"] = '0';
                            }
                            $mTpRq2 = "INSERT INTO `on_demand_macro_service` (`svc_svc_id`, `svc_macro_name`, " .
                                "`svc_macro_value`, `is_password`)
                                VALUES (:svc_svc_id, :svc_macro_name, :svc_macro_value , :is_password)";
                            $statement = $pearDB->prepare($mTpRq2);
                            $statement->bindValue(':svc_svc_id', $maxId["MAX(service_id)"], \PDO::PARAM_INT);
                            $statement->bindValue(':svc_macro_name', '$' . $macName . '$');
                            $statement->bindValue(':svc_macro_value', $macVal);
                            $statement->bindValue(':is_password', $sv["is_password"]);
                            $statement->execute();
                            $fields["_" . strtoupper($macName) . "_"] = $sv['svc_macro_value'];
                            if ($sv['is_password'] === 1) {
                                $maxIdStatement = $pearDB->query(
                                    "SELECT MAX(svc_macro_id) from on_demand_macro_service WHERE is_password = 1"
                                );
                                $resultMacro = $maxIdStatement->fetch();
                                $macroPasswords[$resultMacro['MAX(svc_macro_id)']] = [
                                    'macroName' => $macName,
                                    'macroValue' => $macVal
                                ];
                            }
                        }

                        if (! empty($macroPasswords) && $vaultConfiguration !== null) {
                            /** @var ReadVaultRepositoryInterface $readVaultRepository */
                            $readVaultRepository = $kernel->getContainer()->get(
                                ReadVaultRepositoryInterface::class
                            );
                            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
                            $writeVaultRepository = $kernel->getContainer()->get(
                                WriteVaultRepositoryInterface::class
                            );
                            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
                            try {
                                duplicateServiceSecretsInVault(
                                    $readVaultRepository,
                                    $writeVaultRepository,
                                    $logger,
                                    $key,
                                    $macroPasswords,
                                );
                            } catch (\Throwable $ex) {
                                error_log((string) $ex);
                            }
                        }

                        /*
                         * Service categories
                         */
                        $mTpRq1 = "SELECT * FROM `service_categories_relation` " .
                            "WHERE `service_service_id` = '" . $key . "'";
                        $dbResult3 = $pearDB->query($mTpRq1);
                        $mTpRq2 = "INSERT INTO `service_categories_relation` (`service_service_id`, `sc_id`) " .
                            "VALUES (:service_service_id, :sc_id)";
                        $statement = $pearDB->prepare($mTpRq2);
                        while ($sv = $dbResult3->fetch()) {
                            $statement->bindValue(
                                ':service_service_id',
                                (int) $maxId["MAX(service_id)"],
                                \PDO::PARAM_INT
                            );
                            $statement->bindValue(':sc_id', (int) $sv['sc_id'], \PDO::PARAM_INT);
                            $statement->execute();
                        }

                        /*
                         *  get svc desc
                         */
                        $query = "SELECT service_description FROM service " .
                            "WHERE service_id = :service_id LIMIT 1";
                        $statement = $pearDB->prepare($query);
                        $statement->bindValue(':service_id', (int) $maxId["MAX(service_id)"], \PDO::PARAM_INT);
                        $statement->execute();
                        if ($statement->rowCount()) {
                            $row2 = $statement->fetch(PDO::FETCH_ASSOC);
                            $description = $row2['service_description'];
                            $centreon->CentreonLogAction->insertLog(
                                "service",
                                $maxId["MAX(service_id)"],
                                $description,
                                "a",
                                $fields
                            );
                        }

                        signalConfigurationChange('service', (int) $maxId["MAX(service_id)"]);
                    }
                }
            }
            $centreon->user->access->updateACL(
                array(
                    "type" => 'SERVICE',
                    'id' => $maxId["MAX(service_id)"],
                    "action" => "DUP",
                    "duplicate_service" => $key
                )
            );
        }
    }
    return ($maxId["MAX(service_id)"]);
}

function updateServiceForCloud($serviceId = null, $massiveChange = false, $parameters = [])
{
    global $form, $pearDB, $centreon;

    if (! $serviceId) {
        return;
    }

    $service = new CentreonService($pearDB);

    $ret = array();
    if (count($parameters)) {
        $ret = $parameters;
    } else {
        $ret = $form->getSubmitValues();
    }


    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    //Retrieve vault path before updating values in database.
    $vaultPath = null;
    if ($vaultConfiguration !== null ){
        $vaultPath = retrieveServiceVaultPathFromDatabase($pearDB, $service_id);
    }

    $ret["service_description"] = $service->checkIllegalChar($ret["service_description"]);

    $rq = "UPDATE service SET ";
    $rq .= "service_template_model_stm_id = ";
    isset($ret["service_template_model_stm_id"]) && $ret["service_template_model_stm_id"] != null
        ? $rq .= "'" . $ret["service_template_model_stm_id"] . "', "
        : $rq .= "NULL, ";

    $rq .= "command_command_id = ";
    isset($ret["command_command_id"]) && $ret["command_command_id"] != null
        ? $rq .= "'" . $ret["command_command_id"] . "', "
        : $rq .= "NULL, ";
        $rq .= "timeperiod_tp_id = ";
    isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != null
        ? $rq .= "'" . $ret["timeperiod_tp_id"] . "', "
        : $rq .= "NULL, ";
    $rq .= "command_command_id2 = null, ";

    // If we are doing a MC, we don't have to set name and alias field
    if (!$massiveChange) {
        $rq .= "service_description = ";
        isset($ret["service_description"]) && $ret["service_description"] != null
            ? $rq .= "'" . CentreonDB::escape($ret["service_description"]) . "', "
            : $rq .= "NULL, ";
    }
    $rq .= "service_alias = ";
    isset($ret["service_alias"]) && $ret["service_alias"] != null
        ? $rq .= "'" . CentreonDB::escape($ret["service_alias"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "service_acknowledgement_timeout = null, service_is_volatile = '2', ";
    $rq .= "service_max_check_attempts = ";
    isset($ret["service_max_check_attempts"]) && $ret["service_max_check_attempts"] != null
        ? $rq .= "'" . $ret["service_max_check_attempts"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_normal_check_interval = ";
    isset($ret["service_normal_check_interval"]) && $ret["service_normal_check_interval"] != null
        ? $rq .= "'" . $ret["service_normal_check_interval"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_retry_check_interval = ";
    isset($ret["service_retry_check_interval"]) && $ret["service_retry_check_interval"] != null
        ? $rq .= "'" . $ret["service_retry_check_interval"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_passive_checks_enabled = '2', service_obsess_over_service = '2', ";
    $rq .= "service_check_freshness = '2', service_freshness_threshold = null, ";
    $rq .= "service_event_handler_enabled = '2', ";
    $rq .= "service_low_flap_threshold = null, service_high_flap_threshold = null, ";
    $rq .= "service_flap_detection_enabled = '2', service_retain_status_information = '2', ";
    $rq .= "service_retain_nonstatus_information = '2', service_notifications_enabled = '2', ";
    $rq .= 'service_recovery_notification_delay = null, service_use_only_contacts_from_host = null, ';
    $rq .= 'contact_additive_inheritance = 0, cg_additive_inheritance = 0, ';
    $rq .= 'service_stalking_options = null, service_comment = null, ';
    $rq .= "geo_coords = ";
    isset($ret["geo_coords"]) && $ret["geo_coords"] != null
        ? $rq .= "'" . CentreonDB::escape($ret["geo_coords"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "command_command_id_arg = null, ";
    $rq .= "command_command_id_arg2 = null, service_register = ";
    isset($ret["service_register"]) && $ret["service_register"] != null
        ? $rq .= "'" . $ret["service_register"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_activate = ";
    isset($ret["service_activate"]["service_activate"]) && $ret["service_activate"]["service_activate"] != null
        ? $rq .= "'" . $ret["service_activate"]["service_activate"] . "' "
        : $rq .= "'1' ";
    $rq .= "WHERE service_id = '" . $serviceId . "'";
    $dbResult = $pearDB->query($rq);

    /*
     * Update demand macros
     */
    if (isset($_REQUEST['macroInput']) && isset($_REQUEST['macroValue'])) {
        $macroDescription = array();
        foreach ($_REQUEST as $nam => $ele) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $ele;
                }
            }
        }
        $service->insertMacro(
            $serviceId,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            (!isset($_REQUEST['macroPassword']) ? 0 : $_REQUEST['macroPassword']),
            $macroDescription,
            $massiveChange
        );
    } else {
        $query = "DELETE FROM on_demand_macro_service WHERE svc_svc_id = '" . CentreonDB::escape($serviceId) . "'";
        $pearDB->query($query);
    }

    if ($vaultConfiguration !== null) {
        /** @var ReadVaultRepositoryInterface $readVaultRepository */
        $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);

        /** @var WriteVaultRepositoryInterface $writeVaultRepository */
        $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
        $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
        try {
            updateServiceSecretsInVault(
                $readVaultRepository,
                $writeVaultRepository,
                $logger,
                $vaultPath,
                (int) $service_id,
                $service->getFormattedMacros(),
            );
        } catch (\Throwable $ex) {
            error_log((string) $ex);
        }
    }

    if (isset($ret['criticality_id'])) {
        setServiceCriticality($serviceId, $ret['criticality_id']);
    }

    $centreon->user->access->updateACL(array("type" => 'SERVICE', 'id' => $serviceId, "action" => "UPDATE"));

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        "service",
        $serviceId,
        CentreonDB::escape($ret["service_description"]),
        "c",
        $fields
    );
}

function updateService_MCForCloud($serviceId = null, $parameters = [])
{
    if (!$serviceId) {
        return;
    }
    global $form, $pearDB, $centreon;

    $service = new CentreonService($pearDB);

    $ret = array();
    if (count($parameters)) {
        $ret = $parameters;
    } else {
        $ret = $form->getSubmitValues();
    }

    $kernel = Kernel::createForWeb();
    /** @var UUIDGeneratorInterface $uuidGenerator */
    $uuidGenerator = $kernel->getContainer()->get(UUIDGeneratorInterface::class);
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    //Retrieve UUID for vault path before updating values in database.
    $uuid = null;
    if ($vaultConfiguration !== null ){
        $uuid = retrieveServiceSecretUuidFromDatabase($pearDB, $service_id, $vaultConfiguration->getName());
    }

    if (isset($ret["sg_name"])) {
        $ret["sg_name"] = $centreon->checkIllegalChar($ret["sg_name"]);
    }

    $rq = "UPDATE service SET ";
    if (isset($ret["service_template_model_stm_id"]) && $ret["service_template_model_stm_id"] != null) {
        $rq .= "service_template_model_stm_id = '" . $ret["service_template_model_stm_id"] . "', ";
    }
    if (isset($ret["command_command_id"]) && $ret["command_command_id"] != null) {
        $rq .= "command_command_id = '" . $ret["command_command_id"] . "', ";
    }
    if (isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != null) {
        $rq .= "timeperiod_tp_id = '" . $ret["timeperiod_tp_id"] . "', ";
    }
    $rq .= 'command_command_id2 = null, ';

    if (isset($ret["service_alias"]) && $ret["service_alias"] != null) {
        $rq .= "service_alias = '" . $ret["service_alias"] . "', ";
    }

    if (isset($ret["service_max_check_attempts"]) && $ret["service_max_check_attempts"] != null) {
        $rq .= "service_max_check_attempts = '" . $ret["service_max_check_attempts"] . "', ";
    }

    if (isset($ret["service_normal_check_interval"]) && $ret["service_normal_check_interval"] != null) {
        $rq .= "service_normal_check_interval = '" . $ret["service_normal_check_interval"] . "', ";
    }
    if (isset($ret["service_retry_check_interval"]) && $ret["service_retry_check_interval"] != null) {
        $rq .= "service_retry_check_interval = '" . $ret["service_retry_check_interval"] . "', ";
    }

    $rq .= "service_acknowledgement_timeout = null, service_is_volatile = '2', ";
    $rq .= "service_active_checks_enabled = '2', service_passive_checks_enabled = '2', ";
    $rq .= "service_obsess_over_service = '2', service_check_freshness = '2', ";
    $rq .= "service_freshness_threshold = null, service_event_handler_enabled = '2', ";
    $rq .= 'service_low_flap_threshold = null, service_high_flap_threshold = null, ';
    $rq .= "service_flap_detection_enabled = '2', service_retain_status_information = '2', ";
    $rq .= "service_retain_nonstatus_information = '2', ";
    $rq .= "service_notifications_enabled = '2', service_recovery_notification_delay = null, ";
    $rq .= 'cg_additive_inheritance = 0, service_use_only_contacts_from_host = null, ';
    $rq .= 'service_stalking_options = null, service_comment = null, ';


    $rq .= 'command_command_id_arg = null, command_command_id_arg2 = null, ';
    if (isset($ret["service_register"]) && $ret["service_register"] != null) {
        $rq .= "service_register = '" . $ret["service_register"] . "', ";
    }
    if (isset($ret["geo_coords"]) && $ret["geo_coords"] != null) {
        $rq .= "geo_coords = '" . $ret["geo_coords"] . "', ";
    }
    if (isset($ret["service_activate"]["service_activate"]) && $ret["service_activate"]["service_activate"] != null) {
        $rq .= "service_activate = '" . $ret["service_activate"]["service_activate"] . "', ";
    }

    if (strcmp("UPDATE service SET ", $rq)) {
        // Delete last ',' in request
        $rq[strlen($rq) - 2] = " ";
        $rq .= "WHERE service_id = '" . $serviceId . "'";
        $dbResult = $pearDB->query($rq);
    }

    /*
     *  Update on demand macros
     */
    $macroDescription = array();
    foreach ($_REQUEST as $nam => $ele) {
        if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $macroDescription[$match[1]] = $ele;
            }
        }
    }
    if (isset($_REQUEST['macroInput']) && isset($_REQUEST['macroValue'])) {
        $service->insertMacro(
            $serviceId,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            $_REQUEST['macroPassword'] ?? [],
            $macroDescription,
            true,
            false,
            $_REQUEST['macroFrom']
        );
    }
    if (isset($ret['criticality_id']) && $ret['criticality_id']) {
        setServiceCriticality($serviceId, $ret['criticality_id']);
    }

    //If there is a vault configuration write into vault
    if ($vaultConfiguration !== null) {
        try {
            updateServiceSecretsInVaultFromMC(
                $vaultConfiguration,
                $logger,
                $uuidGenerator,
                $uuid,
                (int) $service_id,
                $service->getFormattedMacros()
            );
        } catch (\Throwable $ex) {
            error_log((string) $ex);
        }
    }

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        "service",
        $serviceId,
        CentreonDB::escape($ret["service_description"] ?? ""),
        "mc",
        $fields
    );
}

function updateServiceHostForCloud($serviceId = null, $submittedValues = [], $isMassiveChange = false)
{
    global $form, $pearDB;

    if (!$serviceId) {
        return;
    }

    $ret1 = array();
    $ret2 = array();
    if (isset($submittedValues["service_hPars"])) {
        $ret1 = $submittedValues["service_hPars"];
    } else {
        $ret1 = CentreonUtils::mergeWithInitialValues($form, 'service_hPars');
    }
    if (isset($submittedValues["service_hgPars"])) {
        $ret2 = $submittedValues["service_hgPars"];
    } else {
        $ret2 = CentreonUtils::mergeWithInitialValues($form, 'service_hgPars');
    }

    /*
     * Get actual config
     */
    $rq = "SELECT host_host_id FROM escalation_service_relation " .
        " WHERE service_service_id = :service_id";
    $statement = $pearDB->prepare($rq);
    $statement->bindValue(':service_id', (int) $serviceId, \PDO::PARAM_INT);
    $statement->execute();
    $cacheEsc = array();
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $cacheEsc[$data['host_host_id']] = 1;
    }

    /*
     * Get actual config
     */
    $rq = "SELECT host_host_id FROM host_service_relation " .
        " WHERE service_service_id = :service_id ";
    $statement = $pearDB->prepare($rq);
    $statement->bindValue(':service_id', (int) $serviceId, \PDO::PARAM_INT);
    $statement->execute();
    $cache = array();
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $cache[$data['host_host_id']] = 1;
    }

    if (count($ret1) == 1) {
        foreach ($cache as $host_id => $flag) {
            if (!isset($cacheEsc[$host_id]) && count($cacheEsc)) {
                $query = "UPDATE escalation_service_relation
                          SET host_host_id = :host_host_id
                          WHERE service_service_id = :service_id";
                $statement = $pearDB->prepare($query);
                $statement->bindValue(':host_host_id', (int) $ret1[0], \PDO::PARAM_INT);
                $statement->bindValue(':service_id', (int) $serviceId, \PDO::PARAM_INT);
                $statement->execute();
            }
        }
    } else {
        foreach ($cache as $host_id) {
            if (!isset($cache[$host_id]) && count($cacheEsc)) {
                $query = "DELETE FROM escalation_service_relation
                    WHERE host_host_id = :host_host_id AND service_service_id = :service_id";
                $statement = $pearDB->prepare($query);
                $statement->bindValue(':service_id', (int) $serviceId, \PDO::PARAM_INT);
                $statement->bindValue(':host_host_id', (int) $ret1[0], \PDO::PARAM_INT);
                $statement->execute();
            }
        }
    }

    if (!$isMassiveChange) {
        $rq = "DELETE FROM host_service_relation "
            . "WHERE service_service_id = :service_id ";
        $statement = $pearDB->prepare($rq);
        $statement->bindValue(':service_id', (int) $serviceId, \PDO::PARAM_INT);
        $statement->execute();
    } else {
        # Purge service to host relations
        if (count($ret1)) {
            $rq = "DELETE FROM host_service_relation "
                . "WHERE service_service_id = :service_id "
                . "AND host_host_id IS NOT NULL ";
            $statement = $pearDB->prepare($rq);
            $statement->bindValue(':service_id', (int) $serviceId, \PDO::PARAM_INT);
            $statement->execute();
        }
        # Purge service to hostgroup relations
        if (count($ret2)) {
            $rq = "DELETE FROM host_service_relation "
                . "WHERE service_service_id = :service_id "
                . "AND hostgroup_hg_id IS NOT NULL ";
            $statement = $pearDB->prepare($rq);
            $statement->bindValue(':service_id', (int) $serviceId, \PDO::PARAM_INT);
            $statement->execute();
        }
    }

    if (count($ret2)) {
        for ($i = 0; $i < count($ret2); $i++) {
            $rq = "INSERT INTO host_service_relation ";
            $rq .= "(hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) ";
            $rq .= "VALUES ";
            $rq .= "('" . $ret2[$i] . "', NULL, NULL, '" . $serviceId . "')";
            $dbResult = $pearDB->query($rq);
            setHostChangeFlag($pearDB, null, $ret2[$i]);
        }
    } elseif (count($ret1)) {
        for ($i = 0; $i < count($ret1); $i++) {
            $rq = "INSERT INTO host_service_relation ";
            $rq .= "(hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) ";
            $rq .= "VALUES ";
            $rq .= "(NULL, '" . $ret1[$i] . "', NULL, '" . $serviceId . "')";
            $dbResult = $pearDB->query($rq);
            setHostChangeFlag($pearDB, $ret1[$i], null);
        }
    }
}

function updateServiceHost_MCForCloud($serviceId = null)
{
    global $form, $pearDB;

    if (! $serviceId) {
        return;
    }

    $rq = "SELECT * FROM host_service_relation ";
    $rq .= "WHERE service_service_id = '" . $serviceId . "'";
    $dbResult = $pearDB->query($rq);
    $hsvs = array();
    $hgsvs = array();
    while ($arr = $dbResult->fetch()) {
        if ($arr["host_host_id"]) {
            $hsvs[$arr["host_host_id"]] = $arr["host_host_id"];
        }
        if ($arr["hostgroup_hg_id"]) {
            $hgsvs[$arr["hostgroup_hg_id"]] = $arr["hostgroup_hg_id"];
        }
    }
    $ret1 = array();
    $ret2 = array();
    $ret1 = $form->getSubmitValue("service_hPars");
    $ret2 = $form->getSubmitValue("service_hgPars");
    if (is_array($ret2)) {
        for ($i = 0; $i < count($ret2); $i++) {
            if (!isset($hgsvs[$ret2[$i]])) {
                $rq = "DELETE FROM host_service_relation ";
                $rq .= "WHERE service_service_id = '" . $serviceId . "' AND host_host_id IS NOT NULL";
                $dbResult = $pearDB->query($rq);
                $rq = "INSERT INTO host_service_relation ";
                $rq .= "(hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) ";
                $rq .= "VALUES ";
                $rq .= "('" . $ret2[$i] . "', NULL, NULL, '" . $serviceId . "')";
                $dbResult = $pearDB->query($rq);
                setHostChangeFlag($pearDB, null, $ret2[$i]);
            }
        }
    } elseif (is_array($ret1)) {
        for ($i = 0; $i < count($ret1); $i++) {
            if (!isset($hsvs[$ret1[$i]])) {
                $rq = "DELETE FROM host_service_relation ";
                $rq .= "WHERE service_service_id = '" . $serviceId . "' AND hostgroup_hg_id IS NOT NULL";
                $pearDB->query($rq);
                $rq = "INSERT INTO host_service_relation ";
                $rq .= "(hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) ";
                $rq .= "VALUES ";
                $rq .= "(NULL, '" . $ret1[$i] . "', NULL, '" . $serviceId . "')";
                $pearDB->query($rq);
                setHostChangeFlag($pearDB, $ret1[$i], null);
            }
        }
    }
}

function updateServiceInDB($serviceId = null, $massiveChange = false, $parameters = [])
{
    global $isCloudPlatform;

    $isCloudPlatform
        ? updateServiceInDBForCloud($serviceId, $massiveChange, $parameters)
        : updateServiceInDBForOnPrem($serviceId, $massiveChange, $parameters);
}

function updateServiceInDBForCloud($serviceId = null, $massiveChange = false, $parameters = [])
{
    global $form;

    if (! $serviceId) {
        return;
    }

    if (count($parameters)) {
        $ret = $parameters;
    } else {
        $ret = $form->getSubmitValues();
    }

    $isServiceTemplate = isset($ret['service_register']) && $ret['service_register'] === '0';

    $previousPollerIds = getPollersForConfigChangeFlagFromServiceId($serviceId);

    if ($massiveChange) {
        updateService_MCForCloud($serviceId);
    } else {
        updateServiceForCloud($serviceId, $massiveChange, $parameters);
    }

    // Function for updating host/hg parent
    if ($massiveChange) {
        updateServiceHost_MCForCloud($serviceId);
    } else {
        updateServiceHostForCloud($serviceId, $parameters);
    }

    if (!$isServiceTemplate) {
        if ($massiveChange) {
            updateServiceServiceGroup_MC($serviceId);
        } else {
            updateServiceServiceGroup($serviceId);
        }
    }

    if ($massiveChange) {
        updateServiceExtInfos_MC($serviceId);
    } else {
        updateServiceExtInfos($serviceId);
    }

    if ($massiveChange) {
        updateServiceCategories_MC($serviceId);
    } else {
        updateServiceCategories($serviceId);
    }

    signalConfigurationChange('service', $serviceId, $previousPollerIds);
}

function updateServiceInDBForOnPrem($serviceId = null, $massiveChange = false, $parameters = [])
{
    global $form;

    if (!$serviceId) {
        return;
    }

    if (count($parameters)) {
        $ret = $parameters;
    } else {
        $ret = $form->getSubmitValues();
    }

    $isServiceTemplate = isset($ret['service_register']) && $ret['service_register'] === '0';

    $previousPollerIds = getPollersForConfigChangeFlagFromServiceId($serviceId);

    if ($massiveChange) {
        updateService_MC($serviceId);
    } else {
        updateService($serviceId, $massiveChange, $parameters);
    }
    // Function for updating cg
    // 1 - MC with deletion of existing cg
    // 2 - MC with addition of new cg
    // 3 - Normal update
    if (isset($ret["mc_mod_cgs"]["mc_mod_cgs"]) && $ret["mc_mod_cgs"]["mc_mod_cgs"]) {
        updateServiceContactGroup($serviceId, $parameters);
        updateServiceContact($serviceId, $parameters);
    } elseif (isset($ret["mc_mod_cgs"]["mc_mod_cgs"]) && !$ret["mc_mod_cgs"]["mc_mod_cgs"]) {
        updateServiceContactGroup_MC($serviceId, $parameters);
        updateServiceContact_MC($serviceId, $parameters);
    } else {
        updateServiceContactGroup($serviceId, $parameters);
        updateServiceContact($serviceId, $parameters);
    }

    // Function for updating notification options
    // 1 - MC with deletion of existing options (Replacement)
    // 2 - MC with addition of new options (incremental)
    // 3 - Normal update
    if (isset($ret["mc_mod_notifopts"]["mc_mod_notifopts"]) && $ret["mc_mod_notifopts"]["mc_mod_notifopts"]) {
        updateServiceNotifs($serviceId);
    } elseif (isset($ret["mc_mod_notifopts"]["mc_mod_notifopts"]) && !$ret["mc_mod_notifopts"]["mc_mod_notifopts"]) {
        updateServiceNotifs_MC($serviceId);
    } else {
        updateServiceNotifs($serviceId);
    }

    // Function for updating notification interval options
    // 1 - MC with deletion of existing options (Replacement)
    // 2 - MC with addition of new options (incremental)
    // 3 - Normal update
    if (
        isset($ret["mc_mod_notifopt_notification_interval"]["mc_mod_notifopt_notification_interval"])
        && $ret["mc_mod_notifopt_notification_interval"]["mc_mod_notifopt_notification_interval"]
    ) {
        updateServiceNotifOptionInterval($serviceId);
    } elseif (
        isset($ret["mc_mod_notifopt_notification_interval"]["mc_mod_notifopt_notification_interval"])
        && !$ret["mc_mod_notifopt_notification_interval"]["mc_mod_notifopt_notification_interval"]
    ) {
        updateServiceNotifOptionInterval_MC($serviceId);
    } else {
        updateServiceNotifOptionInterval($serviceId);
    }

    // Function for updating first notification delay options
    // 1 - MC with deletion of existing options (Replacement)
    // 2 - MC with addition of new options (incremental)
    // 3 - Normal update, default behavior
    if (
        isset($ret["mc_mod_notifopt_first_notification_delay"]["mc_mod_notifopt_first_notification_delay"])
        && $ret["mc_mod_notifopt_first_notification_delay"]["mc_mod_notifopt_first_notification_delay"]
    ) {
        updateServiceNotifOptionFirstNotificationDelay($serviceId);
    } elseif (
        isset($ret["mc_mod_notifopt_first_notification_delay"]["mc_mod_notifopt_first_notification_delay"])
        && !$ret["mc_mod_notifopt_first_notification_delay"]["mc_mod_notifopt_first_notification_delay"]
    ) {
        updateServiceNotifOptionFirstNotificationDelay_MC($serviceId);
    } else {
        updateServiceNotifOptionFirstNotificationDelay($serviceId);
    }


    // Function for updating notification timeperiod options
    // 1 - MC with deletion of existing options (Replacement)
    // 2 - MC with addition of new options (incremental)
    // 3 - Normal update
    if (
        isset($ret["mc_mod_notifopt_timeperiod"]["mc_mod_notifopt_timeperiod"])
        && $ret["mc_mod_notifopt_timeperiod"]["mc_mod_notifopt_timeperiod"]
    ) {
        updateServiceNotifOptionTimeperiod($serviceId);
    } elseif (
        isset($ret["mc_mod_notifopt_timeperiod"]["mc_mod_notifopt_timeperiod"])
        && !$ret["mc_mod_notifopt_timeperiod"]["mc_mod_notifopt_timeperiod"]
    ) {
        updateServiceNotifOptionTimeperiod_MC($serviceId);
    } else {
        updateServiceNotifOptionTimeperiod($serviceId);
    }


    // Function for updating host/hg parent
    // 1 - MC with deletion of existing host/hg parent
    // 2 - MC with addition of new host/hg parent
    // 3 - Normal update
    if (isset($ret["mc_mod_Pars"]["mc_mod_Pars"]) && $ret["mc_mod_Pars"]["mc_mod_Pars"]) {
        updateServiceHost($serviceId, $parameters, true);
    } elseif (isset($ret["mc_mod_Pars"]["mc_mod_Pars"]) && !$ret["mc_mod_Pars"]["mc_mod_Pars"]) {
        updateServiceHost_MC($serviceId);
    } else {
        updateServiceHost($serviceId, $parameters);
    }

    // Function for updating sg
    // 1 - MC with deletion of existing sg
    // 2 - MC with addition of new sg
    // 3 - Normal update
    if (!$isServiceTemplate) {
        if (isset($ret["mc_mod_sgs"]["mc_mod_sgs"]) && $ret["mc_mod_sgs"]["mc_mod_sgs"]) {
            updateServiceServiceGroup($serviceId);
        } elseif (isset($ret["mc_mod_sgs"]["mc_mod_sgs"]) && !$ret["mc_mod_sgs"]["mc_mod_sgs"]) {
            updateServiceServiceGroup_MC($serviceId);
        } else {
            updateServiceServiceGroup($serviceId);
        }
    }

    if ($massiveChange) {
        updateServiceExtInfos_MC($serviceId);
    } else {
        updateServiceExtInfos($serviceId);
    }
    // Function for updating traps
    // 1 - MC with deletion of existing traps
    // 2 - MC with addition of new traps
    // 3 - Normal update
    if (isset($ret["mc_mod_traps"]["mc_mod_traps"]) && $ret["mc_mod_traps"]["mc_mod_traps"]) {
        updateServiceTrap($serviceId);
    } elseif (isset($ret["mc_mod_traps"]["mc_mod_traps"]) && !$ret["mc_mod_traps"]["mc_mod_traps"]) {
        updateServiceTrap_MC($serviceId);
    } else {
        updateServiceTrap($serviceId);
    }
    // Function for updating categories
    // 1 - MC with deletion of existing categories
    // 2 - MC with addition of new categories
    // 3 - Normal update
    if (isset($ret["mc_mod_sc"]["mc_mod_sc"]) && $ret["mc_mod_sc"]["mc_mod_sc"]) {
        updateServiceCategories($serviceId);
    } elseif (isset($ret["mc_mod_sc"]["mc_mod_sc"]) && !$ret["mc_mod_sc"]["mc_mod_sc"]) {
        updateServiceCategories_MC($serviceId);
    } else {
        updateServiceCategories($serviceId);
    }

    signalConfigurationChange('service', $serviceId, $previousPollerIds);
}

function insertServiceInDB($submittedValues = [], $onDemandMacro = null)
{
    global $isCloudPlatform;

    return $isCloudPlatform
        ? insertServiceInDBForCloud($submittedValues, $onDemandMacro)
        : insertServiceInDBForOnPremise($submittedValues, $onDemandMacro);
}

function insertServiceInDBForCloud($submittedValues = [], $onDemandMacro = null)
{
    global $centreon;

    $tmp_fields = insertServiceForCloud($submittedValues, $onDemandMacro);
    if (! isset($tmp_fields['service_id'])) {
        return null;
    }

    $serviceId = (int) $tmp_fields['service_id'];
    updateServiceHost($serviceId, $submittedValues);
    updateServiceServiceGroup($serviceId, $submittedValues);
    insertServiceExtInfos($serviceId, $submittedValues);
    updateServiceCategories($serviceId, $submittedValues);

    signalConfigurationChange('service', $serviceId);
    $centreon->user->access->updateACL(
        [
            'type' => 'SERVICE',
            'id' => $serviceId,
            'action' => 'ADD'
        ]
    );
    return ($serviceId);
}

function insertServiceInDBForOnPremise($submittedValues = [], $onDemandMacro = null)
{
    global $centreon;

    $tmp_fields = insertServiceForOnPremise($submittedValues, $onDemandMacro);
    if (! isset($tmp_fields['service_id'])) {
        return null;
    }

    $serviceId = (int) $tmp_fields['service_id'];
    updateServiceContactGroup($serviceId, $submittedValues);
    updateServiceContact($serviceId, $submittedValues);
    updateServiceNotifs($serviceId, $submittedValues);
    updateServiceNotifOptionInterval($serviceId, $submittedValues);
    updateServiceNotifOptionTimeperiod($serviceId, $submittedValues);
    updateServiceNotifOptionFirstNotificationDelay($serviceId, $submittedValues);
    updateServiceHost($serviceId, $submittedValues);
    updateServiceServiceGroup($serviceId, $submittedValues);
    insertServiceExtInfos($serviceId, $submittedValues);
    updateServiceTrap($serviceId, $submittedValues);
    updateServiceCategories($serviceId, $submittedValues);

    signalConfigurationChange('service', $serviceId);
    $centreon->user->access->updateACL(
        [
            'type' => 'SERVICE',
            'id' => $serviceId,
            'action' => 'ADD'
        ]
    );
    return ($serviceId);
}

function insertServiceForCloud($submittedValues = [], $onDemandMacro = null)
{
    global $form, $pearDB, $centreon;

    $service = new CentreonService($pearDB);

    if (! count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }

    $submittedValues["service_description"] = $service->checkIllegalChar($submittedValues["service_description"]);
    $find = '/\s{2,}/';
    $submittedValues["service_description"] = preg_replace($find, ' ', $submittedValues["service_description"]);

    $request = "INSERT INTO service " .
        "(service_template_model_stm_id, command_command_id, timeperiod_tp_id, command_command_id2, " .
        "timeperiod_tp_id2, service_description, service_alias, service_is_volatile, service_max_check_attempts, " .
        "service_normal_check_interval, service_retry_check_interval, service_active_checks_enabled, " .
        "service_passive_checks_enabled, service_obsess_over_service, service_check_freshness, " .
        "service_freshness_threshold, service_event_handler_enabled, service_low_flap_threshold, " .
        "service_high_flap_threshold, service_flap_detection_enabled, service_retain_status_information, " .
        "service_retain_nonstatus_information, service_notification_interval, service_notification_options, " .
        "service_notifications_enabled, contact_additive_inheritance, cg_additive_inheritance, " .
        "service_use_only_contacts_from_host, service_stalking_options, " .
        "service_first_notification_delay, service_recovery_notification_delay," .
        "service_comment, geo_coords, command_command_id_arg, command_command_id_arg2, " .
        "service_register, service_activate, service_acknowledgement_timeout) " .
        "VALUES ( ";
    isset($submittedValues["service_template_model_stm_id"]) && $submittedValues["service_template_model_stm_id"] != null
        ? $request .= "'" . $submittedValues["service_template_model_stm_id"] . "', "
        : $request .= "NULL, ";

    isset($submittedValues["command_command_id"]) && $submittedValues["command_command_id"] != null
        ? $request .= "'" . $submittedValues["command_command_id"] . "', "
        : $request .= "NULL, ";

    isset($submittedValues["timeperiod_tp_id"]) && $submittedValues["timeperiod_tp_id"] != null
        ? $request .= "'" . $submittedValues["timeperiod_tp_id"] . "', "
        : $request .= "NULL, ";
    $request .= 'null, '; // command_command_id2 = null
    $request .= 'null, '; // timeperiod_tp_id2 => null
    isset($submittedValues["service_description"]) && $submittedValues["service_description"] != null
        ? $request .= "'" . CentreonDB::escape($submittedValues["service_description"]) . "', "
        : $request .= "NULL, ";
    isset($submittedValues["service_alias"]) && $submittedValues["service_alias"] != null
        ? $request .= "'" . CentreonDB::escape($submittedValues["service_alias"]) . "', "
        : $request .= "NULL, ";

    $request .= "'2', ";  // service_is_volatile = '2' (default)

    isset($submittedValues["service_max_check_attempts"]) && $submittedValues["service_max_check_attempts"] != null
        ? $request .= "'" . $submittedValues["service_max_check_attempts"] . "', "
        : $request .= "NULL, ";
    isset($submittedValues["service_normal_check_interval"]) && $submittedValues["service_normal_check_interval"] != null
        ? $request .= "'" . $submittedValues["service_normal_check_interval"] . "', "
        : $request .= "NULL, ";
    isset($submittedValues["service_retry_check_interval"]) && $submittedValues["service_retry_check_interval"] != null
        ? $request .= "'" . $submittedValues["service_retry_check_interval"] . "', "
        : $request .= "NULL, ";

    $request .= "'2', ";  // service_active_checks_enabled = '2' (default)
    $request .= "'2', ";  // service_passive_checks_enabled = '2' (default)
    $request .= "'2', ";  // service_obsess_over_service = '2' (default)
    $request .= "'2', ";  // service_check_freshness = '2' (default)
    $request .= 'null, '; // service_freshness_threshold = null
    $request .= "'2', ";  // service_event_handler_enabled = '2' (default)
    $request .= 'null, '; // service_low_flap_threshold = null
    $request .= 'null, '; // service_high_flap_threshold = null
    $request .= "'2', ";  // service_flap_detection_enabled = '2' (default)
    $request .= "'2', ";  // service_retain_status_information = '2' (default)
    $request .= "'2', ";  // service_retain_nonstatus_information = '2' (default)
    $request .= 'null, '; // service_notification_interval => null
    $request .= 'null, '; // service_notification_options => null
    $request .= "'2', ";  // service_notifications_enabled => '2' (default)
    $request .= '0, ';    // contact_additive_inheritance => 0 (default)
    $request .= '0, ';    // cg_additive_inheritance => 0 (default)
    $request .= 'null, '; // service_use_only_contacts_from_host = 0
    $request .= 'null, '; // service_stalking_options = null
    $request .= 'null, '; // service_first_notification_delay => null
    $request .= 'null, '; // service_recovery_notification_delay => null
    $request .= 'null, '; // service_comment = null

    isset($submittedValues["geo_coords"]) && $submittedValues["geo_coords"] != null
        ? $request .= "'" . CentreonDB::escape($submittedValues["geo_coords"]) . "', "
        : $request .= "NULL, ";
    $request .= "null, "; // command_command_id_arg = null
    $request .= 'null, '; // command_command_id_arg2 = null
    isset($submittedValues["service_register"]) && $submittedValues["service_register"] != null
        ? $request .= "'" . $submittedValues["service_register"] . "', "
        : $request .= "NULL, ";
    isset($submittedValues["service_activate"]["service_activate"]) && $submittedValues["service_activate"]["service_activate"] != null
        ? $request .= "'" . $submittedValues["service_activate"]["service_activate"] . "',"
        : $request .= "'1',";
    $request .= "NULL)"; // service_acknowledgement_timeout = null
    $pearDB->query($request);
    $dbResult = $pearDB->query("SELECT MAX(service_id) FROM service");
    $service_id = $dbResult->fetch();

    /*
     *  Insert on demand macros
     */
    if (isset($onDemandMacro)) {
        $my_tab = $onDemandMacro;
        if (isset($my_tab['nbOfMacro'])) {
            $already_stored = array();
            for ($i = 0; $i <= $my_tab['nbOfMacro']; $i++) {
                $macInput = "macroInput_" . $i;
                $macValue = "macroValue_" . $i;
                if (
                    isset($my_tab[$macInput])
                    && !isset($already_stored[strtolower($my_tab[$macInput])]) && $my_tab[$macInput]
                ) {
                    $my_tab[$macInput] = str_replace("\$_SERVICE", "", $my_tab[$macInput]);
                    $my_tab[$macInput] = str_replace("\$", "", $my_tab[$macInput]);
                    $macName = $my_tab[$macInput];
                    $macVal = $my_tab[$macValue];
                    $request = "INSERT INTO on_demand_macro_service (`svc_macro_name`, `svc_macro_value`, `svc_svc_id`, " .
                        "`macro_order` ) VALUES (:svc_macro_name, :svc_macro_value, :svc_svc_id, :macro_order)";
                    $statement = $pearDB->prepare($request);
                    $statement->bindValue(':svc_macro_name', '$_SERVICE' . strtoupper($macName) . '$', \PDO::PARAM_STR);
                    $statement->bindValue(':svc_macro_value', $macVal, \PDO::PARAM_STR);
                    $statement->bindValue(':svc_svc_id', (int) $service_id["MAX(service_id)"], \PDO::PARAM_INT);
                    $statement->bindValue(':macro_order', $i, \PDO::PARAM_INT);
                    $statement->execute();
                    $fields["_" . strtoupper($my_tab[$macInput]) . "_"] = $my_tab[$macValue];
                    $already_stored[strtolower($my_tab[$macInput])] = 1;
                }
            }
        }
    } elseif (isset($_REQUEST['macroInput']) && isset($_REQUEST['macroValue'])) {
        $macroDescription = array();
        foreach ($_REQUEST as $nam => $ele) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $ele;
                }
            }
        }
        $service->insertMacro(
            $service_id["MAX(service_id)"],
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            isset($_REQUEST['macroPassword']) ? $_REQUEST['macroPassword'] : null,
            $macroDescription,
            false
        );
    }

    $passwordMacros = array_filter($service->getFormattedMacros(), function ($macro) {
        return $macro['macro_password'] === '1';
    });
    $kernel = Kernel::createForWeb();
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    //If there is a vault configuration  and macros write into vault
    if ($vaultConfiguration !== null && ! empty($passwordMacros)) {
        try {
            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            insertServiceSecretsInVault($writeVaultRepository, $passwordMacros);
        } catch (\Throwable $ex) {
            error_log((string) $ex);
        }
    }

    if (isset($submittedValues['criticality_id'])) {
        setServiceCriticality($service_id['MAX(service_id)'], $submittedValues['criticality_id']);
    }

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($submittedValues);
    $centreon->CentreonLogAction->insertLog(
        "service",
        $service_id["MAX(service_id)"],
        CentreonDB::escape($submittedValues["service_description"]),
        "a",
        $fields
    );

    return (array("service_id" => $service_id["MAX(service_id)"], "fields" => $fields));
}

function insertServiceForOnPremise($submittedValues = [], $onDemandMacro = null)
{
    global $form, $pearDB, $centreon;

    $service = new CentreonService($pearDB);

    if (!count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }

    $submittedValues["service_description"] = $service->checkIllegalChar($submittedValues["service_description"]);
    $find = '/\s{2,}/';
    $submittedValues["service_description"] = preg_replace($find, ' ', $submittedValues["service_description"]);

    if (isset($submittedValues["command_command_id_arg2"]) && $submittedValues["command_command_id_arg2"] != null) {
        $submittedValues["command_command_id_arg2"] = str_replace("\n", "//BR//", $submittedValues["command_command_id_arg2"]);
        $submittedValues["command_command_id_arg2"] = str_replace("\t", "//T//", $submittedValues["command_command_id_arg2"]);
        $submittedValues["command_command_id_arg2"] = str_replace("\r", "//R//", $submittedValues["command_command_id_arg2"]);
    }
    $rq = "INSERT INTO service " .
        "(service_template_model_stm_id, command_command_id, timeperiod_tp_id, command_command_id2, " .
        "timeperiod_tp_id2, service_description, service_alias, service_is_volatile, service_max_check_attempts, " .
        "service_normal_check_interval, service_retry_check_interval, service_active_checks_enabled, " .
        "service_passive_checks_enabled, service_obsess_over_service, service_check_freshness, " .
        "service_freshness_threshold, service_event_handler_enabled, service_low_flap_threshold, " .
        "service_high_flap_threshold, service_flap_detection_enabled, service_retain_status_information, " .
        "service_retain_nonstatus_information, service_notification_interval, service_notification_options, " .
        "service_notifications_enabled, contact_additive_inheritance, cg_additive_inheritance, " .
        "service_use_only_contacts_from_host, service_stalking_options, " .
        "service_first_notification_delay, service_recovery_notification_delay," .
        "service_comment, geo_coords, command_command_id_arg, command_command_id_arg2, " .
        "service_register, service_activate, service_acknowledgement_timeout) " .
        "VALUES ( ";
    isset($submittedValues["service_template_model_stm_id"]) && $submittedValues["service_template_model_stm_id"] != null
        ? $rq .= "'" . $submittedValues["service_template_model_stm_id"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["command_command_id"]) && $submittedValues["command_command_id"] != null
        ? $rq .= "'" . $submittedValues["command_command_id"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["timeperiod_tp_id"]) && $submittedValues["timeperiod_tp_id"] != null
        ? $rq .= "'" . $submittedValues["timeperiod_tp_id"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["command_command_id2"]) && $submittedValues["command_command_id2"] != null
        ? $rq .= "'" . $submittedValues["command_command_id2"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["timeperiod_tp_id2"]) && $submittedValues["timeperiod_tp_id2"] != null
        ? $rq .= "'" . $submittedValues["timeperiod_tp_id2"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_description"]) && $submittedValues["service_description"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["service_description"]) . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_alias"]) && $submittedValues["service_alias"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["service_alias"]) . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_is_volatile"]) && $submittedValues["service_is_volatile"]["service_is_volatile"] != 2
        ? $rq .= "'" . $submittedValues["service_is_volatile"]["service_is_volatile"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_max_check_attempts"]) && $submittedValues["service_max_check_attempts"] != null
        ? $rq .= "'" . $submittedValues["service_max_check_attempts"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_normal_check_interval"]) && $submittedValues["service_normal_check_interval"] != null
        ? $rq .= "'" . $submittedValues["service_normal_check_interval"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_retry_check_interval"]) && $submittedValues["service_retry_check_interval"] != null
        ? $rq .= "'" . $submittedValues["service_retry_check_interval"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_active_checks_enabled"]["service_active_checks_enabled"])
    && $submittedValues["service_active_checks_enabled"]["service_active_checks_enabled"] != 2
        ? $rq .= "'" . $submittedValues["service_active_checks_enabled"]["service_active_checks_enabled"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_passive_checks_enabled"]["service_passive_checks_enabled"])
    && $submittedValues["service_passive_checks_enabled"]["service_passive_checks_enabled"] != 2
        ? $rq .= "'" . $submittedValues["service_passive_checks_enabled"]["service_passive_checks_enabled"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_obsess_over_service"]["service_obsess_over_service"])
    && $submittedValues["service_obsess_over_service"]["service_obsess_over_service"] != 2
        ? $rq .= "'" . $submittedValues["service_obsess_over_service"]["service_obsess_over_service"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_check_freshness"]["service_check_freshness"])
    && $submittedValues["service_check_freshness"]["service_check_freshness"] != 2
        ? $rq .= "'" . $submittedValues["service_check_freshness"]["service_check_freshness"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_freshness_threshold"]) && $submittedValues["service_freshness_threshold"] != null
        ? $rq .= "'" . $submittedValues["service_freshness_threshold"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_event_handler_enabled"]["service_event_handler_enabled"])
    && $submittedValues["service_event_handler_enabled"]["service_event_handler_enabled"] != 2
        ? $rq .= "'" . $submittedValues["service_event_handler_enabled"]["service_event_handler_enabled"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_low_flap_threshold"]) && $submittedValues["service_low_flap_threshold"] != null
        ? $rq .= "'" . $submittedValues["service_low_flap_threshold"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_high_flap_threshold"]) && $submittedValues["service_high_flap_threshold"] != null
        ? $rq .= "'" . $submittedValues["service_high_flap_threshold"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_flap_detection_enabled"]["service_flap_detection_enabled"])
    && $submittedValues["service_flap_detection_enabled"]["service_flap_detection_enabled"] != 2
        ? $rq .= "'" . $submittedValues["service_flap_detection_enabled"]["service_flap_detection_enabled"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_retain_status_information"]["service_retain_status_information"])
    && $submittedValues["service_retain_status_information"]["service_retain_status_information"] != 2
        ? $rq .= "'" . $submittedValues["service_retain_status_information"]["service_retain_status_information"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_retain_nonstatus_information"]["service_retain_nonstatus_information"])
    && $submittedValues["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] != 2
        ? $rq .= "'" . $submittedValues["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] . "', "
        : $rq .= "'2', ";
    isset($submittedValues["service_notification_interval"]) && $submittedValues["service_notification_interval"] != null
        ? $rq .= "'" . $submittedValues["service_notification_interval"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_notifOpts"]) && $submittedValues["service_notifOpts"] != null
        ? $rq .= "'" . implode(",", array_keys($submittedValues["service_notifOpts"])) . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_notifications_enabled"]["service_notifications_enabled"])
    && $submittedValues["service_notifications_enabled"]["service_notifications_enabled"] != 2
        ? $rq .= "'" . $submittedValues["service_notifications_enabled"]["service_notifications_enabled"] . "', "
        : $rq .= "'2', ";
    $rq .= (isset($submittedValues["contact_additive_inheritance"]) ? 1 : 0) . ', ';
    $rq .= (isset($submittedValues["cg_additive_inheritance"]) ? 1 : 0) . ', ';
    isset($submittedValues["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"])
    && $submittedValues["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"] != null
        ? $rq .= "'" . $submittedValues["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_stalOpts"]) && $submittedValues["service_stalOpts"] != null
        ? $rq .= "'" . implode(",", array_keys($submittedValues["service_stalOpts"])) . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_first_notification_delay"]) && $submittedValues["service_first_notification_delay"] != null
        ? $rq .= "'" . $submittedValues["service_first_notification_delay"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_recovery_notification_delay"]) && $submittedValues["service_recovery_notification_delay"] != null
        ? $rq .= $submittedValues["service_recovery_notification_delay"] . ", "
        : $rq .= "NULL, ";
    isset($submittedValues["service_comment"]) && $submittedValues["service_comment"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["service_comment"]) . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["geo_coords"]) && $submittedValues["geo_coords"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["geo_coords"]) . "', "
        : $rq .= "NULL, ";
    $submittedValues['command_command_id_arg'] = getCommandArgs($_POST, $submittedValues);
    isset($submittedValues["command_command_id_arg"]) && $submittedValues["command_command_id_arg"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["command_command_id_arg"]) . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["command_command_id_arg2"]) && $submittedValues["command_command_id_arg2"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["command_command_id_arg2"]) . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_register"]) && $submittedValues["service_register"] != null
        ? $rq .= "'" . $submittedValues["service_register"] . "', "
        : $rq .= "NULL, ";
    isset($submittedValues["service_activate"]["service_activate"]) && $submittedValues["service_activate"]["service_activate"] != null
        ? $rq .= "'" . $submittedValues["service_activate"]["service_activate"] . "',"
        : $rq .= "'1',";
    isset($submittedValues["service_acknowledgement_timeout"]) && $submittedValues["service_acknowledgement_timeout"] != null
        ? $rq .= "'" . $submittedValues["service_acknowledgement_timeout"] . "'"
        : $rq .= "NULL";
    $rq .= ")";
    $dbResult = $pearDB->query($rq);
    $dbResult = $pearDB->query("SELECT MAX(service_id) FROM service");
    $service_id = $dbResult->fetch();

    /*
     *  Insert on demand macros
     */
    if (isset($onDemandMacro)) {
        $my_tab = $onDemandMacro;
        if (isset($my_tab['nbOfMacro'])) {
            $already_stored = array();
            for ($i = 0; $i <= $my_tab['nbOfMacro']; $i++) {
                $macInput = "macroInput_" . $i;
                $macValue = "macroValue_" . $i;
                if (
                    isset($my_tab[$macInput])
                    && !isset($already_stored[strtolower($my_tab[$macInput])]) && $my_tab[$macInput]
                ) {
                    $my_tab[$macInput] = str_replace("\$_SERVICE", "", $my_tab[$macInput]);
                    $my_tab[$macInput] = str_replace("\$", "", $my_tab[$macInput]);
                    $macName = $my_tab[$macInput];
                    $macVal = $my_tab[$macValue];
                    $rq = "INSERT INTO on_demand_macro_service (`svc_macro_name`, `svc_macro_value`, `svc_svc_id`, " .
                        "`macro_order` ) VALUES (:svc_macro_name, :svc_macro_value, :svc_svc_id, :macro_order)";
                    $statement = $pearDB->prepare($rq);
                    $statement->bindValue(':svc_macro_name', '$_SERVICE' . strtoupper($macName) . '$', \PDO::PARAM_STR);
                    $statement->bindValue(':svc_macro_value', $macVal, \PDO::PARAM_STR);
                    $statement->bindValue(':svc_svc_id', (int) $service_id["MAX(service_id)"], \PDO::PARAM_INT);
                    $statement->bindValue(':macro_order', $i, \PDO::PARAM_INT);
                    $statement->execute();
                    $fields["_" . strtoupper($my_tab[$macInput]) . "_"] = $my_tab[$macValue];
                    $already_stored[strtolower($my_tab[$macInput])] = 1;
                }
            }
        }
    } elseif (isset($_REQUEST['macroInput']) && isset($_REQUEST['macroValue'])) {
        $macroDescription = array();
        foreach ($_REQUEST as $nam => $ele) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $ele;
                }
            }
        }
        $service->insertMacro(
            $service_id["MAX(service_id)"],
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            isset($_REQUEST['macroPassword']) ? $_REQUEST['macroPassword'] : null,
            $macroDescription,
            false,
            $submittedValues["command_command_id"]
        );
    }
    $passwordMacros = array_filter($service->getFormattedMacros(), function ($macro) {
        return $macro['macroPassword'] === '1';
    });
    $kernel = Kernel::createForWeb();
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    //If there is a vault configuration  and macros write into vault
    if ($vaultConfiguration !== null && ! empty($passwordMacros)) {
        try {
            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
            insertServiceSecretsInVault($writeVaultRepository, $passwordMacros);
        } catch (\Throwable $ex) {
            error_log((string) $ex);
        }
    }

    if (isset($submittedValues['criticality_id'])) {
        setServiceCriticality($service_id['MAX(service_id)'], $submittedValues['criticality_id']);
    }

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($submittedValues);
    $centreon->CentreonLogAction->insertLog(
        "service",
        $service_id["MAX(service_id)"],
        CentreonDB::escape($submittedValues["service_description"]),
        "a",
        $fields
    );

    return (array("service_id" => $service_id["MAX(service_id)"], "fields" => $fields));
}

function insertServiceExtInfos($serviceId = null, $submittedValues = [])
{
    if (! $serviceId) {
        return;
    }
    global $form, $pearDB, $isCloudPlatform;

    if (! count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }
    /*
     * Check if image selected isn't a directory
     */
    if (isset($submittedValues["esi_icon_image"]) && strrchr("REP_", $submittedValues["esi_icon_image"])) {
        $submittedValues["esi_icon_image"] = null;
    }
    /*
     *
     */
    $request = "INSERT INTO `extended_service_information` " .
        "( `esi_id` , `service_service_id`, `esi_notes` , `esi_notes_url` , " .
        "`esi_action_url` , `esi_icon_image` , `esi_icon_image_alt`, `graph_id` )" .
        "VALUES ( ";
    $request .= "NULL, " . $serviceId . ", ";
    isset($submittedValues["esi_notes"]) && $submittedValues["esi_notes"] != null
        ? $request .= "'" . CentreonDB::escape($submittedValues["esi_notes"]) . "', "
        : $request .= "NULL, ";
    isset($submittedValues["esi_notes_url"]) && $submittedValues["esi_notes_url"] != null
        ? $request .= "'" . CentreonDB::escape($submittedValues["esi_notes_url"]) . "', "
        : $request .= "NULL, ";
    isset($submittedValues["esi_action_url"]) && $submittedValues["esi_action_url"] != null
        ? $request .= "'" . CentreonDB::escape($submittedValues["esi_action_url"]) . "', "
        : $request .= "NULL, ";
    isset($submittedValues["esi_icon_image"]) && $submittedValues["esi_icon_image"] != null
        ? $request .= "'" . CentreonDB::escape($submittedValues["esi_icon_image"]) . "', "
        : $request .= "NULL, ";
    if (! $isCloudPlatform) {
        isset($submittedValues["esi_icon_image_alt"]) && $submittedValues["esi_icon_image_alt"] != null
            ? $request .= "'" . CentreonDB::escape($submittedValues["esi_icon_image_alt"]) . "', "
            : $request .= "NULL, ";
        isset($submittedValues["graph_id"]) && $submittedValues["graph_id"] != null ? $request .= "'" . $submittedValues["graph_id"] . "'" : $request .= "NULL";
    } else {
        $request .= 'NULL, NULL';
    }
    $request .= ")";
    $pearDB->query($request);
}

/** *************************************
 *
 * Update service informations
 * @param $service_id
 * @param $from_MC
 * @param array $params
 */
function updateService($service_id = null, $from_MC = false, $params = array())
{
    global $form, $pearDB, $centreon;

    if (!$service_id) {
        return;
    }

    $service = new CentreonService($pearDB);

    $ret = array();
    if (count($params)) {
        $ret = $params;
    } else {
        $ret = $form->getSubmitValues();
    }

    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    //Retrieve vault path before updating values in database.
    $vaultPath = null;
    if ($vaultConfiguration !== null ){
        $vaultPath = retrieveServiceVaultPathFromDatabase($pearDB, $service_id);
    }

    $ret["service_description"] = $service->checkIllegalChar($ret["service_description"]);

    if (isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != null) {
        $ret["command_command_id_arg2"] = str_replace("\n", "//BR//", $ret["command_command_id_arg2"]);
        $ret["command_command_id_arg2"] = str_replace("\t", "//T//", $ret["command_command_id_arg2"]);
        $ret["command_command_id_arg2"] = str_replace("\r", "//R//", $ret["command_command_id_arg2"]);
    }
    $rq = "UPDATE service SET ";
    $rq .= "service_template_model_stm_id = ";
    isset($ret["service_template_model_stm_id"]) && $ret["service_template_model_stm_id"] != null
        ? $rq .= "'" . $ret["service_template_model_stm_id"] . "', "
        : $rq .= "NULL, ";
    $rq .= "command_command_id = ";
    isset($ret["command_command_id"]) && $ret["command_command_id"] != null
        ? $rq .= "'" . $ret["command_command_id"] . "', "
        : $rq .= "NULL, ";
    $rq .= "timeperiod_tp_id = ";
    isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != null
        ? $rq .= "'" . $ret["timeperiod_tp_id"] . "', "
        : $rq .= "NULL, ";
    $rq .= "command_command_id2 = ";
    isset($ret["command_command_id2"]) && $ret["command_command_id2"] != null
        ? $rq .= "'" . $ret["command_command_id2"] . "', "
        : $rq .= "NULL, ";
    /*$rq .= "timeperiod_tp_id2 = ";
      isset($ret["timeperiod_tp_id2"]) && $ret["timeperiod_tp_id2"] != NULL
    ? $rq .= "'".$ret["timeperiod_tp_id2"]."', "
    : $rq .= "NULL, ";*/
    // If we are doing a MC, we don't have to set name and alias field
    if (!$from_MC) {
        $rq .= "service_description = ";
        isset($ret["service_description"]) && $ret["service_description"] != null
            ? $rq .= "'" . CentreonDB::escape($ret["service_description"]) . "', "
            : $rq .= "NULL, ";
    }
    $rq .= "service_alias = ";
    isset($ret["service_alias"]) && $ret["service_alias"] != null
        ? $rq .= "'" . CentreonDB::escape($ret["service_alias"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "service_acknowledgement_timeout = ";
    isset($ret["service_acknowledgement_timeout"]) && $ret["service_acknowledgement_timeout"] != null
        ? $rq .= "'" . $ret["service_acknowledgement_timeout"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_is_volatile = ";
    isset($ret["service_is_volatile"]["service_is_volatile"])
    && $ret["service_is_volatile"]["service_is_volatile"] != 2
        ? $rq .= "'" . $ret["service_is_volatile"]["service_is_volatile"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_max_check_attempts = ";
    isset($ret["service_max_check_attempts"]) && $ret["service_max_check_attempts"] != null
        ? $rq .= "'" . $ret["service_max_check_attempts"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_normal_check_interval = ";
    isset($ret["service_normal_check_interval"]) && $ret["service_normal_check_interval"] != null
        ? $rq .= "'" . $ret["service_normal_check_interval"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_retry_check_interval = ";
    isset($ret["service_retry_check_interval"]) && $ret["service_retry_check_interval"] != null
        ? $rq .= "'" . $ret["service_retry_check_interval"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_active_checks_enabled = ";
    isset($ret["service_active_checks_enabled"]["service_active_checks_enabled"])
    && $ret["service_active_checks_enabled"]["service_active_checks_enabled"] != 2
        ? $rq .= "'" . $ret["service_active_checks_enabled"]["service_active_checks_enabled"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_passive_checks_enabled = ";
    isset($ret["service_passive_checks_enabled"]["service_passive_checks_enabled"])
    && $ret["service_passive_checks_enabled"]["service_passive_checks_enabled"] != 2
        ? $rq .= "'" . $ret["service_passive_checks_enabled"]["service_passive_checks_enabled"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_obsess_over_service = ";
    isset($ret["service_obsess_over_service"]["service_obsess_over_service"])
    && $ret["service_obsess_over_service"]["service_obsess_over_service"] != 2
        ? $rq .= "'" . $ret["service_obsess_over_service"]["service_obsess_over_service"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_check_freshness = ";
    isset($ret["service_check_freshness"]["service_check_freshness"])
    && $ret["service_check_freshness"]["service_check_freshness"] != 2
        ? $rq .= "'" . $ret["service_check_freshness"]["service_check_freshness"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_freshness_threshold = ";
    isset($ret["service_freshness_threshold"]) && $ret["service_freshness_threshold"] != null
        ? $rq .= "'" . $ret["service_freshness_threshold"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_event_handler_enabled = ";
    isset($ret["service_event_handler_enabled"]["service_event_handler_enabled"])
    && $ret["service_event_handler_enabled"]["service_event_handler_enabled"] != 2
        ? $rq .= "'" . $ret["service_event_handler_enabled"]["service_event_handler_enabled"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_low_flap_threshold = ";
    isset($ret["service_low_flap_threshold"]) && $ret["service_low_flap_threshold"] != null
        ? $rq .= "'" . $ret["service_low_flap_threshold"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_high_flap_threshold = ";
    isset($ret["service_high_flap_threshold"]) && $ret["service_high_flap_threshold"] != null
        ? $rq .= "'" . $ret["service_high_flap_threshold"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_flap_detection_enabled = ";
    isset($ret["service_flap_detection_enabled"]["service_flap_detection_enabled"])
    && $ret["service_flap_detection_enabled"]["service_flap_detection_enabled"] != 2
        ? $rq .= "'" . $ret["service_flap_detection_enabled"]["service_flap_detection_enabled"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_retain_status_information = ";
    isset($ret["service_retain_status_information"]["service_retain_status_information"])
    && $ret["service_retain_status_information"]["service_retain_status_information"] != 2
        ? $rq .= "'" . $ret["service_retain_status_information"]["service_retain_status_information"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_retain_nonstatus_information = ";
    isset($ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"])
    && $ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] != 2
        ? $rq .= "'" . $ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_notifications_enabled = ";
    isset($ret["service_notifications_enabled"]["service_notifications_enabled"])
    && $ret["service_notifications_enabled"]["service_notifications_enabled"] != 2
        ? $rq .= "'" . $ret["service_notifications_enabled"]["service_notifications_enabled"] . "', "
        : $rq .= "'2', ";
    $rq .= "service_recovery_notification_delay = ";
    isset($ret['service_recovery_notification_delay']) && $ret['service_recovery_notification_delay'] != null
        ? $rq .= $ret['service_recovery_notification_delay'] . ', '
        : $rq .= 'NULL, ';
    $rq .= "service_use_only_contacts_from_host = ";
    isset($ret["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"])
    && $ret["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"] != null
        ? $rq .= "'" . $ret["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"] . "', "
        : $rq .= "NULL, ";

    $rq .= "contact_additive_inheritance = ";
    $rq .= (isset($ret['contact_additive_inheritance']) ? 1 : 0) . ', ';
    $rq .= "cg_additive_inheritance = ";
    $rq .= (isset($ret['cg_additive_inheritance']) ? 1 : 0) . ', ';

    $rq .= "service_stalking_options = ";
    isset($ret["service_stalOpts"]) && $ret["service_stalOpts"] != null
        ? $rq .= "'" . implode(",", array_keys($ret["service_stalOpts"])) . "', "
        : $rq .= "NULL, ";
    $rq .= "service_comment = ";
    isset($ret["service_comment"]) && $ret["service_comment"] != null
        ? $rq .= "'" . CentreonDB::escape($ret["service_comment"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "geo_coords = ";
    isset($ret["geo_coords"]) && $ret["geo_coords"] != null
        ? $rq .= "'" . CentreonDB::escape($ret["geo_coords"]) . "', "
        : $rq .= "NULL, ";
    $ret["command_command_id_arg"] = getCommandArgs($_POST, $ret);
    $rq .= "command_command_id_arg = ";
    isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != null
        ? $rq .= "'" . CentreonDB::escape($ret["command_command_id_arg"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "command_command_id_arg2 = ";
    isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != null
        ? $rq .= "'" . CentreonDB::escape($ret["command_command_id_arg2"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "service_register = ";
    isset($ret["service_register"]) && $ret["service_register"] != null
        ? $rq .= "'" . $ret["service_register"] . "', "
        : $rq .= "NULL, ";
    $rq .= "service_activate = ";
    isset($ret["service_activate"]["service_activate"]) && $ret["service_activate"]["service_activate"] != null
        ? $rq .= "'" . $ret["service_activate"]["service_activate"] . "' "
        : $rq .= "'1' ";
    $rq .= "WHERE service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);

    /*
     * Update demand macros
     */
    if (isset($_REQUEST['macroInput']) && isset($_REQUEST['macroValue'])) {
        $macroDescription = array();
        foreach ($_REQUEST as $nam => $ele) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $ele;
                }
            }
        }
        $service->insertMacro(
            $service_id,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            (!isset($_REQUEST['macroPassword']) ? 0 : $_REQUEST['macroPassword']),
            $macroDescription,
            $from_MC,
            $ret["command_command_id"]
        );
    } else {
        $query = "DELETE FROM on_demand_macro_service WHERE svc_svc_id = '" . CentreonDB::escape($service_id) . "'";
        $pearDB->query($query);
    }

    if ($vaultConfiguration !== null) {
        /** @var ReadVaultRepositoryInterface $readVaultRepository */
        $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);

        /** @var WriteVaultRepositoryInterface $writeVaultRepository */
        $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
        $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
        $updatedPasswordMacros = array_filter($service->getFormattedMacros(), function ($macro) {
            return $macro['macroPassword'] === '1'
                && !str_starts_with($macro['macroValue'], VaultConfiguration::VAULT_PATH_PATTERN);
        });
        try {
            updateServiceSecretsInVault(
                $readVaultRepository,
                $writeVaultRepository,
                $logger,
                $vaultPath,
                (int) $service_id,
                $updatedPasswordMacros,
            );
        } catch (\Throwable $ex) {
            error_log((string) $ex);
        }
    }

    if (isset($ret['criticality_id'])) {
        setServiceCriticality($service_id, $ret['criticality_id']);
    }

    $centreon->user->access->updateACL(array("type" => 'SERVICE', 'id' => $service_id, "action" => "UPDATE"));

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        "service",
        $service_id,
        CentreonDB::escape($ret["service_description"]),
        "c",
        $fields
    );
}

function updateService_MC($service_id = null, $params = array())
{
    if (!$service_id) {
        return;
    }
    global $form, $pearDB, $centreon;

    $service = new CentreonService($pearDB);

    $ret = array();
    if (count($params)) {
        $ret = $params;
    } else {
        $ret = $form->getSubmitValues();
    }

    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    $isServiceTemplate = isset($ret['service_register']) && $ret['service_register'] === '0';
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();

    //Retrieve UUID for vault path before updating values in database.
    $vaultPath = null;
    if ($vaultConfiguration !== null ){
        $vaultPath = retrieveServiceVaultPathFromDatabase($pearDB, $service_id);
    }

    if (isset($ret["sg_name"])) {
        $ret["sg_name"] = $centreon->checkIllegalChar($ret["sg_name"]);
    }

    if (isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != null) {
        $ret["command_command_id_arg"] = str_replace("\n", "//BR//", $ret["command_command_id_arg"]);
        $ret["command_command_id_arg"] = str_replace("\t", "//T//", $ret["command_command_id_arg"]);
        $ret["command_command_id_arg"] = str_replace("\r", "//R//", $ret["command_command_id_arg"]);
    }
    if (isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != null) {
        $ret["command_command_id_arg2"] = str_replace("\n", "//BR//", $ret["command_command_id_arg2"]);
        $ret["command_command_id_arg2"] = str_replace("\t", "//T//", $ret["command_command_id_arg2"]);
        $ret["command_command_id_arg2"] = str_replace("\r", "//R//", $ret["command_command_id_arg2"]);
        "', ";
    }

    $rq = "UPDATE service SET ";
    if (isset($ret["service_template_model_stm_id"]) && $ret["service_template_model_stm_id"] != null) {
        $rq .= "service_template_model_stm_id = '" . $ret["service_template_model_stm_id"] . "', ";
    }
    if (isset($ret["command_command_id"]) && $ret["command_command_id"] != null) {
        $rq .= "command_command_id = '" . $ret["command_command_id"] . "', ";
    }
    if (isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != null) {
        $rq .= "timeperiod_tp_id = '" . $ret["timeperiod_tp_id"] . "', ";
    }
    if (isset($ret["command_command_id2"]) && $ret["command_command_id2"] != null) {
        $rq .= "command_command_id2 = '" . $ret["command_command_id2"] . "', ";
    }
    if (isset($ret["service_alias"]) && $ret["service_alias"] != null) {
        $rq .= "service_alias = '" . $ret["service_alias"] . "', ";
    }
    if (
        isset($ret["service_is_volatile"]["service_is_volatile"])
        && $ret["service_is_volatile"]["service_is_volatile"] != 2
    ) {
        $rq .= "service_is_volatile = '" . $ret["service_is_volatile"]["service_is_volatile"] . "', ";
    }
    if (isset($ret["service_max_check_attempts"]) && $ret["service_max_check_attempts"] != null) {
        $rq .= "service_max_check_attempts = '" . $ret["service_max_check_attempts"] . "', ";
    }
    if (isset($ret["service_acknowledgement_timeout"]) && $ret["service_acknowledgement_timeout"] != null) {
        $rq .= "service_acknowledgement_timeout = '" . $ret["service_acknowledgement_timeout"] . "', ";
    }
    if (isset($ret["service_normal_check_interval"]) && $ret["service_normal_check_interval"] != null) {
        $rq .= "service_normal_check_interval = '" . $ret["service_normal_check_interval"] . "', ";
    }
    if (isset($ret["service_retry_check_interval"]) && $ret["service_retry_check_interval"] != null) {
        $rq .= "service_retry_check_interval = '" . $ret["service_retry_check_interval"] . "', ";
    }
    if (isset($ret["service_active_checks_enabled"]["service_active_checks_enabled"])) {
        $rq .= "service_active_checks_enabled = '" .
            $ret["service_active_checks_enabled"]["service_active_checks_enabled"] . "', ";
    }
    if (isset($ret["service_passive_checks_enabled"]["service_passive_checks_enabled"])) {
        $rq .= "service_passive_checks_enabled = '" .
            $ret["service_passive_checks_enabled"]["service_passive_checks_enabled"] . "', ";
    }
    if (isset($ret["service_obsess_over_service"]["service_obsess_over_service"])) {
        $rq .= "service_obsess_over_service = '" .
            $ret["service_obsess_over_service"]["service_obsess_over_service"] . "', ";
    }
    if (isset($ret["service_check_freshness"]["service_check_freshness"])) {
        $rq .= "service_check_freshness = '" . $ret["service_check_freshness"]["service_check_freshness"] . "', ";
    }
    if (isset($ret["service_freshness_threshold"]) && $ret["service_freshness_threshold"] != null) {
        $rq .= "service_freshness_threshold = '" . $ret["service_freshness_threshold"] . "', ";
    }
    if (isset($ret["service_event_handler_enabled"]["service_event_handler_enabled"])) {
        $rq .= "service_event_handler_enabled = '" .
            $ret["service_event_handler_enabled"]["service_event_handler_enabled"] . "', ";
    }
    if (isset($ret["service_low_flap_threshold"]) && $ret["service_low_flap_threshold"] != null) {
        $rq .= "service_low_flap_threshold = '" . $ret["service_low_flap_threshold"] . "', ";
    }
    if (isset($ret["service_high_flap_threshold"]) && $ret["service_high_flap_threshold"] != null) {
        $rq .= "service_high_flap_threshold = '" . $ret["service_high_flap_threshold"] . "', ";
    }
    if (isset($ret["service_flap_detection_enabled"]["service_flap_detection_enabled"])) {
        $rq .= "service_flap_detection_enabled = '" .
            $ret["service_flap_detection_enabled"]["service_flap_detection_enabled"] . "', ";
    }
    if (isset($ret["service_retain_status_information"]["service_retain_status_information"])) {
        $rq .= "service_retain_status_information = '" .
            $ret["service_retain_status_information"]["service_retain_status_information"] . "', ";
    }
    if (isset($ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"])) {
        $rq .= "service_retain_nonstatus_information = '" .
            $ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] . "', ";
    }
    if (isset($ret["service_notifications_enabled"]["service_notifications_enabled"])) {
        $rq .= "service_notifications_enabled = '" .
            $ret["service_notifications_enabled"]["service_notifications_enabled"] . "', ";
    }
    if (isset($ret["service_recovery_notification_delay"]) && $ret["service_recovery_notification_delay"] != null) {
        $rq .= "service_recovery_notification_delay = '" . $ret["service_recovery_notification_delay"] . "', ";
    }
    if (
        isset($ret["mc_contact_additive_inheritance"]["mc_contact_additive_inheritance"])
        && in_array($ret["mc_contact_additive_inheritance"]["mc_contact_additive_inheritance"], array('0', '1'))
    ) {
        $rq .= "contact_additive_inheritance = '" .
            $ret["mc_contact_additive_inheritance"]["mc_contact_additive_inheritance"] . "', ";
    }
    if (
        isset($ret["mc_cg_additive_inheritance"]["mc_cg_additive_inheritance"])
        && in_array($ret["mc_cg_additive_inheritance"]["mc_cg_additive_inheritance"], array('0', '1'))
    ) {
        $rq .= "cg_additive_inheritance = '" . $ret["mc_cg_additive_inheritance"]["mc_cg_additive_inheritance"] . "', ";
    }
    if (isset($ret["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"])) {
        $rq .= "service_use_only_contacts_from_host = '" .
            $ret["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"] . "', ";
    }
    if (isset($ret["service_stalOpts"]) && $ret["service_stalOpts"] != null) {
        $rq .= "service_stalking_options = '" . implode(",", array_keys($ret["service_stalOpts"])) . "', ";
    }
    if (isset($ret["service_comment"]) && $ret["service_comment"] != null) {
        $rq .= "service_comment = '" . CentreonDB::escape($ret["service_comment"]) . "', ";
    }
    $ret["command_command_id_arg"] = getCommandArgs($_POST, $ret);
    if (isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != null) {
        $rq .= "command_command_id_arg = '" . CentreonDB::escape($ret["command_command_id_arg"]) . "', ";
    }
    if (isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != null) {
        $rq .= "command_command_id_arg2 = '" . CentreonDB::escape($ret["command_command_id_arg2"]) . "', ";
    }
    if (isset($ret["service_register"]) && $ret["service_register"] != null) {
        $rq .= "service_register = '" . $ret["service_register"] . "', ";
    }
    if (isset($ret["geo_coords"]) && $ret["geo_coords"] != null) {
        $rq .= "geo_coords = '" . $ret["geo_coords"] . "', ";
    }

    if (!$isServiceTemplate) {
        if (isset($ret["service_activate"]["service_activate"]) && $ret["service_activate"]["service_activate"] != null) {
            $rq .= "service_activate = '" . $ret["service_activate"]["service_activate"] . "', ";
        }
    } else {
        $rq .= "service_activate = '1', ";
    }

    if (strcmp("UPDATE service SET ", $rq)) {
        // Delete last ',' in request
        $rq[strlen($rq) - 2] = " ";
        $rq .= "WHERE service_id = '" . $service_id . "'";
        $dbResult = $pearDB->query($rq);
    }

    /*
     *  Update on demand macros
     */
    $macroDescription = array();
    foreach ($_REQUEST as $nam => $ele) {
        if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $macroDescription[$match[1]] = $ele;
            }
        }
    }
    if (isset($_REQUEST['macroInput']) && isset($_REQUEST['macroValue'])) {
        $service->insertMacro(
            $service_id,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            $_REQUEST['macroPassword'] ?? [],
            $macroDescription,
            true,
            false,
            $_REQUEST['macroFrom']
        );
    }
    if (isset($ret['criticality_id']) && $ret['criticality_id']) {
        setServiceCriticality($service_id, $ret['criticality_id']);
    }

    //If there is a vault configuration write into vault
    if ($vaultConfiguration !== null) {
        try {
            /** @var ReadVaultRepositoryInterface $readVaultRepository */
            $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);

            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);

            $updatedPasswordMacros = array_filter($service->getFormattedMacros(), function ($macro) {
                return $macro['macroPassword'] === '1'
                    && ! str_starts_with($macro['macroValue'], VaultConfiguration::VAULT_PATH_PATTERN);
            });
            updateServiceSecretsInVaultFromMC(
                $readVaultRepository,
                $writeVaultRepository,
                $logger,
                $vaultPath,
                (int) $service_id,
                $updatedPasswordMacros
            );
        } catch (\Throwable $ex) {
            error_log((string) $ex);
        }
    }

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        "service",
        $service_id,
        CentreonDB::escape($ret["service_description"] ?? ""),
        "mc",
        $fields
    );
}

/*
 *  For Nagios 3
 */
function updateServiceContact($service_id = null, $ret = array())
{
    if (!$service_id) {
        return;
    }
    global $form;
    global $pearDB;
    $rq = "DELETE FROM contact_service_relation ";
    $rq .= "WHERE service_service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
    if (isset($ret["service_cs"])) {
        $ret = $ret["service_cs"];
    } else {
        $ret = $form->getSubmitValue("service_cs");
    }

    $loopCount = (is_array($ret) || $ret instanceof Countable) ? count($ret) : 0;

    for ($i = 0; $i < $loopCount; $i++) {
        $rq = "INSERT INTO contact_service_relation ";
        $rq .= "(contact_id, service_service_id) ";
        $rq .= "VALUES ";
        $rq .= "('" . $ret[$i] . "', '" . $service_id . "')";
        $dbResult = $pearDB->query($rq);
    }
}

function updateServiceContactGroup($service_id = null, $ret = array())
{
    if (!$service_id) {
        return;
    }
    global $form;
    global $pearDB;
    $rq = "DELETE FROM contactgroup_service_relation ";
    $rq .= "WHERE service_service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);

    if (isset($ret["service_cgs"])) {
        $ret = $ret["service_cgs"];
    } else {
        $ret = $form->getSubmitValue("service_cgs");
    }

    $cg = new CentreonContactgroup($pearDB);

    if (is_array($ret)) {
        for ($i = 0; $i < count($ret); $i++) {
            if (!is_numeric($ret[$i])) {
                $res = $cg->insertLdapGroup($ret[$i]);
                if ($res != 0) {
                    $ret[$i] = $res;
                } else {
                    continue;
                }
            }
            if (isset($ret[$i]) && $ret[$i] && $ret[$i] != "") {
                $rq = "INSERT INTO contactgroup_service_relation ";
                $rq .= "(contactgroup_cg_id, service_service_id) ";
                $rq .= "VALUES ";
                $rq .= "('" . $ret[$i] . "', '" . $service_id . "')";
                $dbResult = $pearDB->query($rq);
            }
        }
    }
}


function updateServiceNotifs($service_id = null, $ret = array())
{
    if (!$service_id) {
        return;
    }
    global $form;
    global $pearDB;

    if (isset($ret["service_notifOpts"])) {
        $ret = $ret["service_notifOpts"];
    } else {
        $ret = $form->getSubmitValue("service_notifOpts");
    }

    $rq = "UPDATE service SET ";
    $rq .= "service_notification_options = ";
    isset($ret) && $ret != null ? $rq .= "'" . implode(",", array_keys($ret)) . "' " : $rq .= "NULL ";
    $rq .= "WHERE service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
}

// For massive change. incremental mode
function updateServiceNotifs_MC($service_id = null)
{
    if (!$service_id) {
        return;
    }
    global $form;
    global $pearDB;

    $rq = "SELECT * FROM service ";
    $rq .= "WHERE service_id = '" . $service_id . "' LIMIT 1";
    $dbResult = $pearDB->query($rq);
    $service = array();
    $service = array_map("db2str", $dbResult->fetch());
    $service = array_map("myDecode", $service);

    $ret = $form->getSubmitValue("service_notifOpts");

    if (is_array($ret)) {
        if (isset($service["service_notification_options"]) && $service["service_notification_options"] != null) {
            $temp = $service["service_notification_options"] . "," . implode(",", array_keys($ret));
        } else {
            $temp = implode(",", array_keys($ret));
        }
    }

    if (isset($temp) && $temp != null) {
        $rq = "UPDATE service SET ";
        $rq .= "service_notification_options = '" . trim($temp, ',') . "' ";
        $rq .= "WHERE service_id = '" . $service_id . "'";
        $dbResult = $pearDB->query($rq);
    }
}

function updateServiceNotifOptionInterval($service_id = null, $ret = array())
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    if (isset($ret["service_notification_interval"])) {
        $ret = $ret["service_notification_interval"];
    } else {
        $ret = $form->getSubmitValue("service_notification_interval");
    }

    $rq = "UPDATE service SET ";
    $rq .= "service_notification_interval = ";
    isset($ret) && $ret != null ? $rq .= "'" . $ret . "' " : $rq .= "NULL ";
    $rq .= "WHERE service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
}

// For massive change. incremental mode
function updateServiceNotifOptionInterval_MC($service_id = null)
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $ret = $form->getSubmitValue("service_notification_interval");

    if (isset($ret) && $ret != null) {
        $rq = "UPDATE service SET ";
        $rq .= "service_notification_interval = '" . $ret . "' ";
        $rq .= "WHERE service_id = '" . $service_id . "'";
        $dbResult = $pearDB->query($rq);
    }
}

/**
 * @param int $serviceId
 * @param array $ret
 *
 * @throws CentreonDbException
 */
function updateServiceNotifOptionTimeperiod(int $serviceId, $ret = array())
{
    global $pearDB;

    try {
        $queryParams = [];
        $request = <<<'SQL'
            UPDATE `service` SET `timeperiod_tp_id2` = :timeperiod_tp_id2
            WHERE `service_id` = :service_id
            SQL;

        $stmt = $pearDB->prepareQuery($request);
        $queryParams['service_id'] = $serviceId;

        $queryParams['timeperiod_tp_id2'] = $ret['timeperiod_tp_id2'] ?? null;

        $pearDB->executePreparedQuery($stmt, $queryParams);
    } catch (CentreonDbException $ex) {
        CentreonLog::create()->error(
            CentreonLog::LEVEL_ERROR,
            'Error while updating service notification timeperiod: ' . $ex->getMessage(),
            ['service_id' => $serviceId, 'ret' => $ret],
            $ex
        );

        throw $ex;
    }
}

// For massive change. incremental mode
function updateServiceNotifOptionTimeperiod_MC($service_id = null)
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $ret = $form->getSubmitValue("timeperiod_tp_id2");

    if (isset($ret) && $ret != null) {
        $rq = "UPDATE service SET ";
        $rq .= "timeperiod_tp_id2 = '" . $ret . "' ";
        $rq .= "WHERE service_id = '" . $service_id . "'";
        $dbResult = $pearDB->query($rq);
    }
}

function updateServiceNotifOptionFirstNotificationDelay($service_id = null, $ret = array())
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    if (isset($ret["service_first_notification_delay"])) {
        $ret = $ret["service_first_notification_delay"];
    } else {
        $ret = $form->getSubmitValue("service_first_notification_delay");
    }

    $rq = "UPDATE service SET ";
    $rq .= "service_first_notification_delay = ";
    isset($ret) && $ret != null ? $rq .= "'" . $ret . "' " : $rq .= "NULL ";
    $rq .= "WHERE service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
}

// For massive change. incremental mode
function updateServiceNotifOptionFirstNotificationDelay_MC($service_id = null)
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $ret = $form->getSubmitValue("service_first_notification_delay");

    if (isset($ret) && $ret != null) {
        $rq = "UPDATE service SET ";
        $rq .= "service_first_notification_delay = '" . $ret . "' ";
        $rq .= "WHERE service_id = '" . $service_id . "'";
        $dbResult = $pearDB->query($rq);
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceContactGroup_MC($service_id = null)
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $rq = "SELECT * FROM contactgroup_service_relation ";
    $rq .= "WHERE service_service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
    $cgs = array();
    while ($arr = $dbResult->fetch()) {
        $cgs[$arr["contactgroup_cg_id"]] = $arr["contactgroup_cg_id"];
    }
    $ret = $form->getSubmitValue("service_cgs");
    $cg = new CentreonContactgroup($pearDB);
    if (is_array($ret)) {
        for ($i = 0; $i < count($ret); $i++) {
            if (!isset($cgs[$ret[$i]])) {
                if (!is_numeric($ret[$i])) {
                    $res = $cg->insertLdapGroup($ret[$i]);
                    if ($res != 0) {
                        $ret[$i] = $res;
                    } else {
                        continue;
                    }
                }
                if (isset($ret[$i]) && $ret[$i] && $ret[$i] != "") {
                    $rq = "INSERT INTO contactgroup_service_relation ";
                    $rq .= "(contactgroup_cg_id, service_service_id) ";
                    $rq .= "VALUES ";
                    $rq .= "('" . $ret[$i] . "', '" . $service_id . "')";
                    $dbResult = $pearDB->query($rq);
                }
            }
        }
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceContact_MC($service_id = null)
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $rq = "SELECT * FROM contact_service_relation ";
    $rq .= "WHERE service_service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
    $cgs = array();
    while ($arr = $dbResult->fetch()) {
        $cs[$arr["contact_id"]] = $arr["contact_id"];
    }
    $ret = $form->getSubmitValue("service_cs");
    if (is_array($ret)) {
        for ($i = 0; $i < count($ret); $i++) {
            if (!isset($cs[$ret[$i]])) {
                $rq = "INSERT INTO contact_service_relation ";
                $rq .= "(contact_id, service_service_id) ";
                $rq .= "VALUES ";
                $rq .= "('" . $ret[$i] . "', '" . $service_id . "')";
                $dbResult = $pearDB->query($rq);
            }
        }
    }
}

function updateServiceServiceGroup($service_id = null, $ret = array())
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $rq = "DELETE FROM servicegroup_relation ";
    $rq .= "WHERE service_service_id = '" . $service_id . "'";
    $pearDB->query($rq);

    if (isset($ret["service_sgs"])) {
        $ret = $ret["service_sgs"];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'service_sgs');
    }
    for ($i = 0; $i < count($ret); $i++) {
        /* We need to record each relation for host / hostgroup selected */
        if (isset($ret["service_hPars"])) {
            $ret1 = CentreonUtils::mergeWithInitialValues($form, 'service_hPars');
        } else {
            $ret1 = getMyServiceHosts($service_id);
        }
        if (isset($ret["service_hgPars"])) {
            $ret2 = CentreonUtils::mergeWithInitialValues($form, 'service_hgPars');
        } else {
            $ret2 = getMyServiceHostGroups($service_id);
        }
        if (count($ret2)) {
            foreach ($ret2 as $key => $value) {
                $rq = "INSERT INTO servicegroup_relation ";
                $rq .= "(host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) ";
                $rq .= "VALUES ";
                $rq .= "(NULL, '" . $value . "', '" . $service_id . "', '" . $ret[$i] . "')";
                $pearDB->query($rq);
            }
        } elseif (count($ret1)) {
            foreach ($ret1 as $key => $value) {
                $rq = "INSERT INTO servicegroup_relation ";
                $rq .= "(host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) ";
                $rq .= "VALUES ";
                $rq .= "('" . $value . "', NULL, '" . $service_id . "', '" . $ret[$i] . "')";
                $pearDB->query($rq);
            }
        }
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceServiceGroup_MC($service_id = null)
{
    global $form, $pearDB;
    if (!$service_id) {
        return;
    }
    $rq = "SELECT * FROM servicegroup_relation WHERE service_service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
    $hsgs = array();
    $hgsgs = array();
    while ($arr = $dbResult->fetch()) {
        if ($arr["host_host_id"]) {
            $hsgs[$arr["host_host_id"]][] = $arr["servicegroup_sg_id"];
        }
        if ($arr["hostgroup_hg_id"]) {
            $hgsgs[$arr["hostgroup_hg_id"]][] = $arr["servicegroup_sg_id"];
        }
    }
    $ret = $form->getSubmitValue("service_sgs");
    if (is_array($ret)) {
        for ($i = 0; $i < count($ret); $i++) {
            /* We need to record each relation for host / hostgroup selected */
            $ret1 = getMyServiceHosts($service_id);
            $ret2 = getMyServiceHostGroups($service_id);
            if (count($ret2)) {
                foreach ($ret2 as $hg) {
                    if (!in_array($ret[$i], $hgsgs[$hg])) {
                        $rq = "INSERT INTO servicegroup_relation ";
                        $rq .= "(host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) ";
                        $rq .= "VALUES ";
                        $rq .= "(NULL, '" . $hg . "', '" . $service_id . "', '" . $ret[$i] . "')";
                        $dbResult = $pearDB->query($rq);
                    }
                }
            } elseif (count($ret1)) {
                foreach ($ret1 as $h) {
                    if (!isset($hsgs[$h]) || !in_array($ret[$i], $hsgs[$h])) {
                        $rq = "INSERT INTO servicegroup_relation ";
                        $rq .= "(host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) ";
                        $rq .= "VALUES ";
                        $rq .= "('" . $h . "', NULL, '" . $service_id . "', '" . $ret[$i] . "')";
                        $dbResult = $pearDB->query($rq);
                    }
                }
            }
        }
    }
}

function updateServiceTrap($service_id = null, $ret = array())
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $rq = "DELETE FROM traps_service_relation ";
    $rq .= "WHERE service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
    if (isset($ret["service_traps"])) {
        $ret = $ret["service_traps"];
    } else {
        $ret = $form->getSubmitValue("service_traps");
    }

    if (is_array($ret)) {
        for ($i = 0; $i < count($ret); $i++) {
            $rq = "INSERT INTO traps_service_relation ";
            $rq .= "(traps_id, service_id) ";
            $rq .= "VALUES ";
            $rq .= "('" . $ret[$i] . "', '" . $service_id . "')";
            $dbResult = $pearDB->query($rq);
        }
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceTrap_MC($service_id = null)
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $rq = "SELECT * FROM traps_service_relation ";
    $rq .= "WHERE service_id = '" . $service_id . "'";
    $dbResult = $pearDB->query($rq);
    $traps = array();
    while ($arr = $dbResult->fetch()) {
        $traps[$arr["traps_id"]] = $arr["traps_id"];
    }
    $ret = $form->getSubmitValue("service_traps");
    if (is_array($ret)) {
        for ($i = 0; $i < count($ret); $i++) {
            if (!isset($traps[$ret[$i]])) {
                $rq = "INSERT INTO traps_service_relation ";
                $rq .= "(traps_id, service_id) ";
                $rq .= "VALUES ";
                $rq .= "('" . $ret[$i] . "', '" . $service_id . "')";
                $dbResult = $pearDB->query($rq);
            }
        }
    }
}

function updateServiceHost($service_id = null, $ret = array(), $from_MC = false)
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $ret1 = array();
    $ret2 = array();
    if (isset($ret["service_hPars"])) {
        $ret1 = $ret["service_hPars"];
    } else {
        $ret1 = CentreonUtils::mergeWithInitialValues($form, 'service_hPars');
    }
    if (isset($ret["service_hgPars"])) {
        $ret2 = $ret["service_hgPars"];
    } else {
        $ret2 = CentreonUtils::mergeWithInitialValues($form, 'service_hgPars');
    }

    /*
     * Get actual config
     */
    $statement = $pearDB->prepare(
        'SELECT host_host_id FROM escalation_service_relation WHERE service_service_id = :service_id'
    );
    $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
    $statement->execute();
    $cacheEsc = array();
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $cacheEsc[$data['host_host_id']] = 1;
    }

    /*
     * Get actual config
     */
    $statement = $pearDB->prepare(
        'SELECT host_host_id FROM host_service_relation WHERE service_service_id = :service_id'
    );
    $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
    $statement->execute();
    $cache = array();
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $cache[$data['host_host_id']] = 1;
    }

    if (count($ret1) == 1) {
        foreach ($cache as $host_id => $flag) {
            if (!isset($cacheEsc[$host_id]) && count($cacheEsc)) {
                $statement = $pearDB->prepare(
                    <<<'SQL'
                        UPDATE escalation_service_relation
                        SET host_host_id = :host_host_id
                        WHERE service_service_id = :service_id
                        SQL
                );
                $statement->bindValue(':host_host_id', (int) $ret1[0], \PDO::PARAM_INT);
                $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
                $statement->execute();
            }
        }
    } else {
        foreach ($cache as $host_id) {
            if (!isset($cache[$host_id]) && count($cacheEsc)) {
                $statement = $pearDB->prepare(
                    <<<'SQL'
                    DELETE FROM escalation_service_relation
                    WHERE host_host_id = :host_host_id
                      AND service_service_id = :service_id
                    SQL
                );
                $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
                $statement->bindValue(':host_host_id', (int) $ret1[0], \PDO::PARAM_INT);
                $statement->execute();
            }
        }
    }

    if (!$from_MC) {
        $statement = $pearDB->prepare(
            'DELETE FROM host_service_relation WHERE service_service_id = :service_id'
        );
        $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
        $statement->execute();
    } else {
        # Purge service to host relations
        if (count($ret1)) {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    DELETE FROM host_service_relation
                    WHERE service_service_id = :service_id
                    AND host_host_id IS NOT NULL
                    SQL
            );
            $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
            $statement->execute();
        }
        # Purge service to hostgroup relations
        if (count($ret2)) {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    DELETE FROM host_service_relation
                    WHERE service_service_id = :service_id
                    AND hostgroup_hg_id IS NOT NULL
                    SQL
            );
            $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
            $statement->execute();
        }
    }

    if (count($ret2)) {
        for ($i = 0; $i < count($ret2); $i++) {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    INSERT INTO host_service_relation
                        (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
                    VALUES (:host_group_id, NULL, NULL, :service_id)
                    SQL
            );
            $statement->bindValue(':host_group_id', (int) $ret2[$i], \PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
            $statement->execute();
            setHostChangeFlag($pearDB, null, $ret2[$i]);
        }
    } elseif (count($ret1)) {
        for ($i = 0; $i < count($ret1); $i++) {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    INSERT INTO host_service_relation
                        (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
                    VALUES (NULL, :host_id, NULL, :service_id)
                    SQL
            );
            $statement->bindValue(':host_id', (int) $ret1[$i], \PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
            $statement->execute();
            setHostChangeFlag($pearDB, $ret1[$i], null);
        }
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceHost_MC($service_id = null)
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    $statement = $pearDB->prepare(
        <<<'SQL'
            SELECT * FROM host_service_relation WHERE service_service_id = :service_id
        SQL
    );
    $statement->bindValue(':service_id', $service_id, \PDO::PARAM_INT);

    $statement->execute();
    $hsvs = [];
    $hgsvs = [];

    while ($arr = $statement->fetch()) {
        if ($arr["host_host_id"]) {
            $hsvs[$arr["host_host_id"]] = $arr["host_host_id"];
        }
        if ($arr["hostgroup_hg_id"]) {
            $hgsvs[$arr["hostgroup_hg_id"]] = $arr["hostgroup_hg_id"];
        }
    }

    $ret1 = $form->getSubmitValue("service_hPars");
    $ret2 = $form->getSubmitValue("service_hgPars");
    if (is_array($ret2)) {
        for ($i = 0; $i < count($ret2); $i++) {
            if (!isset($hgsvs[$ret2[$i]])) {
                $statement = $pearDB->prepare(
                    <<<'SQL'
                        DELETE FROM host_service_relation
                        WHERE service_service_id = :service_id
                        AND host_host_id IS NOT NULL
                        SQL
                );
                $statement->bindValue(':service_id',(int) $service_id, \PDO::PARAM_INT);
                $statement->execute();

                $statement = $pearDB->prepare(
                    <<<'SQL'
                        INSERT INTO host_service_relation
                        (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
                        VALUES (:host_group_id, NULL, NULL, :service_id)
                        SQL
                );
                $statement->bindValue(':host_group_id', (int) $ret2[$i], \PDO::PARAM_INT);
                $statement->bindValue(':service_id', $service_id, \PDO::PARAM_INT);
                $statement->execute();

                setHostChangeFlag($pearDB, null, $ret2[$i]);
            }
        }
    } elseif (is_array($ret1)) {
        for ($i = 0; $i < count($ret1); $i++) {
            if (!isset($hsvs[$ret1[$i]])) {
                $statement = $pearDB->prepare(
                    <<<'SQL'
                        DELETE FROM host_service_relation
                        WHERE service_service_id = :service_id
                        AND hostgroup_hg_id IS NOT NULL
                        SQL
                );
                $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
                $statement->execute();

                $statement = $pearDB->prepare(
                    <<<'SQL'
                        INSERT INTO host_service_relation
                        (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
                        VALUES (NULL, :host_id, NULL, :service_id)
                    SQL
                );
                $statement->bindValue(':host_id', (int) $ret1[$i], \PDO::PARAM_INT);
                $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
                $statement->execute();

                setHostChangeFlag($pearDB, $ret1[$i], null);
            }
        }
    }
}

function updateServiceExtInfos($serviceId = null, $submittedValues = [])
{
    global $form, $pearDB, $isCloudPlatform;

    if (!$serviceId) {
        return;
    }

    if (! count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }
    /*
     * Check if image selected isn't a directory
     */
    if (isset($submittedValues["esi_icon_image"]) && strrchr("REP_", $submittedValues["esi_icon_image"])) {
        $submittedValues["esi_icon_image"] = null;
    }

    $rq = "UPDATE extended_service_information ";
    $rq .= "SET esi_notes = ";
    isset($submittedValues["esi_notes"]) && $submittedValues["esi_notes"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["esi_notes"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "esi_notes_url = ";
    isset($submittedValues["esi_notes_url"]) && $submittedValues["esi_notes_url"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["esi_notes_url"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "esi_action_url = ";
    isset($submittedValues["esi_action_url"]) && $submittedValues["esi_action_url"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["esi_action_url"]) . "', "
        : $rq .= "NULL, ";
    $rq .= "esi_icon_image = ";
    isset($submittedValues["esi_icon_image"]) && $submittedValues["esi_icon_image"] != null
        ? $rq .= "'" . CentreonDB::escape($submittedValues["esi_icon_image"]) . "' "
        : $rq .= "NULL ";

    if (! $isCloudPlatform) { 
        $rq .= ", esi_icon_image_alt = ";
        isset($submittedValues["esi_icon_image_alt"]) && $submittedValues["esi_icon_image_alt"] != null
            ? $rq .= "'" . CentreonDB::escape($submittedValues["esi_icon_image_alt"]) . "', "
            : $rq .= "NULL, ";
        $rq .= "graph_id = ";
        isset($submittedValues["graph_id"]) && $submittedValues["graph_id"] != null
            ? $rq .= "'" . CentreonDB::escape($submittedValues["graph_id"]) . "' "
        : $rq .= "NULL ";
    }
    $rq .= "WHERE service_service_id = '" . $serviceId . "'";
    $pearDB->query($rq);
}

function updateServiceExtInfos_MC($serviceId = null, $parameters = [])
{
    global $form, $pearDB, $isCloudPlatform;

    if (!$serviceId) {
        return;
    }

    if (count($parameters)) {
        $ret = $parameters;
    } else {
        $ret = $form->getSubmitValues();
    }
    $rq = "UPDATE extended_service_information SET ";
    if (isset($ret["esi_notes"]) && $ret["esi_notes"] != null) {
        $rq .= "esi_notes = '" . CentreonDB::escape($ret["esi_notes"]) . "', ";
    }
    if (isset($ret["esi_notes_url"]) && $ret["esi_notes_url"] != null) {
        $rq .= "esi_notes_url = '" . CentreonDB::escape($ret["esi_notes_url"]) . "', ";
    }
    if (isset($ret["esi_action_url"]) && $ret["esi_action_url"] != null) {
        $rq .= "esi_action_url = '" . CentreonDB::escape($ret["esi_action_url"]) . "', ";
    }
    if (isset($ret["esi_icon_image"]) && $ret["esi_icon_image"] != null) {
        $rq .= "esi_icon_image = '" . CentreonDB::escape($ret["esi_icon_image"]) . "', ";
    }

    if (! $isCloudPlatform) {
        if (isset($ret["esi_icon_image_alt"]) && $ret["esi_icon_image_alt"] != null) {
            $rq .= "esi_icon_image_alt = '" . CentreonDB::escape($ret["esi_icon_image_alt"]) . "', ";
        }
        if (isset($ret["graph_id"]) && $ret["graph_id"] != null) {
            $rq .= "graph_id = '" . CentreonDB::escape($ret["graph_id"]) . "', ";
        }
    } else {
        $rq .= 'esi_icon_image_alt = NULL, graph_id = NULL, ';
    }

    if (strcmp("UPDATE extended_service_information SET ", $rq)) {
        // Delete last ',' in request
        $rq[strlen($rq) - 2] = " ";
        $rq .= "WHERE service_service_id = '" . $serviceId . "'";
        $pearDB->query($rq);
    }
}

function updateServiceTemplateUsed($useTpls = array())
{
    if (!count($useTpls)) {
        return;
    }
    global $pearDB;
    require_once "./include/common/common-Func.php";
    foreach ($useTpls as $key => $value) {
        $query = "UPDATE service SET service_template_model_stm_id = '" . getMyServiceTPLID($value) .
            "' WHERE service_id = '" . $key . "'";
        $pearDB->query($query);
    }
}

function updateServiceCategories_MC($service_id = null, $ret = array())
{
    global $form, $pearDB;

    if (!$service_id) {
        return;
    }

    if (isset($ret["service_categories"])) {
        $ret = $ret["service_categories"];
    } else {
        $ret = $form->getSubmitValue("service_categories");
    }
    if (is_array($ret)) {
        for ($i = 0; $i < count($ret); $i++) {
            $rq = "INSERT INTO service_categories_relation ";
            $rq .= "(sc_id, service_service_id) ";
            $rq .= "VALUES ";
            $rq .= "('" . $ret[$i] . "', '" . $service_id . "')";
            $dbResult = $pearDB->query($rq);
        }
    }
}

function updateServiceCategories($service_id = null, $ret = array())
{
    global $form, $pearDB;
    if (!$service_id) {
        return;
    }

    $rq = "DELETE FROM service_categories_relation
                    WHERE service_service_id = :service_id
                    AND NOT EXISTS(
                        SELECT sc_id
                        FROM service_categories sc
                        WHERE sc.sc_id = service_categories_relation.sc_id
                        AND sc.level IS NOT NULL
                    )";

    $statement = $pearDB->prepare($rq);
    $statement->bindValue(':service_id', (int) $service_id, \PDO::PARAM_INT);
    $statement->execute();
    if (isset($ret["service_categories"])) {
        $ret = $ret["service_categories"];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'service_categories');
    }
    for ($i = 0; $i < count($ret); $i++) {
        $rq = "INSERT INTO service_categories_relation ";
        $rq .= "(sc_id, service_service_id) ";
        $rq .= "VALUES ";
        $rq .= "('" . $ret[$i] . "', '" . $service_id . "')";
        $dbResult = $pearDB->query($rq);
    }
}

/**
 * Inserts criticality relations
 *
 * @param int $serviceId
 * @param int $criticalityId
 * @return void
 */
function setServiceCriticality($serviceId, $criticalityId)
{
    global $pearDB;

    $statement = $pearDB->prepare(
        "DELETE FROM service_categories_relation
                WHERE service_service_id =:service_service_id
                AND NOT EXISTS(
                    SELECT sc_id
                    FROM service_categories sc
                    WHERE sc.sc_id = service_categories_relation.sc_id
                    AND sc.level IS NULL)"
    );
    $statement->bindValue(':service_service_id', $serviceId, \PDO::PARAM_INT);
    $statement->execute();
    if ($criticalityId) {
        $statement = $pearDB->prepare(
            "INSERT INTO service_categories_relation (sc_id, service_service_id)
                                VALUES (:sc_id,:service_service_id)"
        );
        $statement->bindValue(':sc_id', $criticalityId, \PDO::PARAM_INT);
        $statement->bindValue(':service_service_id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();
    }
}

/**
 * Rule for test if a ldap contactgroup name already exists
 *
 * @param array $listCgs The list of contactgroups to validate
 * @return boolean
 */
function testCg2($list)
{
    return CentreonContactgroup::verifiedExists($list);
}

/**
 * @param int $serviceId
 * @return int[]
 */
function getPollersForConfigChangeFlagFromServiceId(int $serviceId): array
{
    $hostIds = findHostsForConfigChangeFlagFromServiceIds([$serviceId]);
    return findPollersForConfigChangeFlagFromHostIds($hostIds);
}

/**
 * Find all the host IDs for which the service is bound
 *
 * @param int $serviceId
 * @return int[]
 */
function findHostsOfService(int $serviceId): array
{
    global $pearDB;
    $statement = $pearDB->prepare(
        'SELECT host.host_id
        FROM host
        INNER JOIN host_service_relation hsr
          ON hsr.host_host_id = host.host_id
        WHERE hsr.service_service_id = :service_id'
    );
    $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
    $statement->execute();
    $hostIds = [];
    while (($hostId = $statement->fetchColumn(0)) !== false) {
        $hostIds[] = $hostId;
    }
    return $hostIds;
}
