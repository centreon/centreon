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
 * SVN : $URL$
 * SVN : $Id$
 *
 */

if (!isset($oreon)) {
    exit();
}

function testServiceDependencyExistence($name = null)
{
    global $pearDB;
    global $form;

    CentreonDependency::purgeObsoleteDependencies($pearDB);

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('dep_id');
    }
    $statement = $pearDB->prepare("SELECT dep_name, dep_id FROM dependency WHERE dep_name = :name");
    $statement->bindValue(':name', $name, \PDO::PARAM_STR);
    $statement->execute();
    $dep = $statement->fetch();
    if ($dep === false) {
        return true;
    }

    return $dep['dep_id'] == $id;
}

function testCycleH($childs = null)
{
    global $pearDB;
    global $form;
    $parents = [];
    $childs = [];
    if (isset($form)) {
        $parents = $form->getSubmitValue('dep_hSvPar');
        $childs = $form->getSubmitValue('dep_hSvChi');
        $childs = array_flip($childs);
    }
    foreach ($parents as $parent) {
        if (array_key_exists($parent, $childs)) {
            return false;
        }
    }
    return true;
}

function deleteServiceDependencyInDB($dependencies = [])
{
    global $pearDB, $oreon;
    $selectStatement = $pearDB->prepare("SELECT dep_name FROM `dependency` WHERE `dep_id` = :dep_id LIMIT 1");
    $deleteStatement = $pearDB->prepare("DELETE FROM dependency WHERE dep_id = :dep_id");
    foreach (array_keys($dependencies) as $key) {
        $selectStatement->bindValue(':dep_id', (int) $key, \PDO::PARAM_INT);
        $selectStatement->execute();
        $row = $selectStatement->fetch();
        if ($row === false) {
            continue;
        }

        $deleteStatement->bindValue(':dep_id', (int) $key, \PDO::PARAM_INT);
        $deleteStatement->execute();
        $oreon->CentreonLogAction->insertLog("service dependency", $key, $row['dep_name'], "d");
    }
}

function multipleServiceDependencyInDB($dependencies = [], $nbrDup = [])
{
    global $pearDB, $oreon;
    $selectStmt = $pearDB->prepare(
        "SELECT dep_name, dep_description, inherits_parent, execution_failure_criteria,
                notification_failure_criteria, dep_comment
        FROM dependency WHERE dep_id = :dep_id LIMIT 1"
    );
    $insertStmt = $pearDB->prepare(
        "INSERT INTO dependency
        (dep_name, dep_description, inherits_parent, execution_failure_criteria,
         notification_failure_criteria, dep_comment)
        VALUES (:dep_name, :dep_description, :inherits_parent, :execution_failure_criteria,
         :notification_failure_criteria, :dep_comment)"
    );
    $selectHostChildStmt = $pearDB->prepare(
        "SELECT host_host_id FROM dependency_hostChild_relation WHERE dependency_dep_id = :dep_id"
    );
    $insertHostChildStmt = $pearDB->prepare(
        "INSERT INTO dependency_hostChild_relation (dependency_dep_id, host_host_id)
        VALUES (:depId, :hostId)"
    );
    $selectServiceParentStmt = $pearDB->prepare(
        "SELECT service_service_id, host_host_id FROM dependency_serviceParent_relation
        WHERE dependency_dep_id = :dep_id"
    );
    $insertServiceParentStmt = $pearDB->prepare(
        "INSERT INTO dependency_serviceParent_relation (dependency_dep_id, service_service_id, host_host_id)
        VALUES (:depId, :serviceId, :hostId)"
    );
    $selectServiceChildStmt = $pearDB->prepare(
        "SELECT service_service_id, host_host_id FROM dependency_serviceChild_relation
        WHERE dependency_dep_id = :dep_id"
    );
    $insertServiceChildStmt = $pearDB->prepare(
        "INSERT INTO dependency_serviceChild_relation (dependency_dep_id, service_service_id, host_host_id)
        VALUES (:depId, :serviceId, :hostId)"
    );

    foreach (array_keys($dependencies) as $key) {
        $selectStmt->bindValue(':dep_id', (int) $key, \PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            continue;
        }
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $dep_name = $row['dep_name'] . '_' . $i;
            $fields = [];
            foreach ($row as $key2 => $value2) {
                $fields[$key2] = $key2 == 'dep_name' ? $dep_name : $value2;
            }
            if (testServiceDependencyExistence($dep_name)) {
                $insertStmt->bindValue(':dep_name', $dep_name, \PDO::PARAM_STR);
                $insertStmt->bindValue(':dep_description', $row['dep_description'], \PDO::PARAM_STR);
                $insertStmt->bindValue(':inherits_parent', $row['inherits_parent'], \PDO::PARAM_STR);
                $insertStmt->bindValue(':execution_failure_criteria', $row['execution_failure_criteria'], \PDO::PARAM_STR);
                $insertStmt->bindValue(':notification_failure_criteria', $row['notification_failure_criteria'], \PDO::PARAM_STR);
                $insertStmt->bindValue(':dep_comment', $row['dep_comment'], \PDO::PARAM_STR);
                $insertStmt->execute();
                $lastId = (int) $pearDB->lastInsertId();
                if ($lastId > 0) {
                    $selectHostChildStmt->bindValue(':dep_id', (int) $key, \PDO::PARAM_INT);
                    $selectHostChildStmt->execute();
                    $fields['dep_hostPar'] = '';
                    while ($host = $selectHostChildStmt->fetch()) {
                        $insertHostChildStmt->bindValue(':depId', (int) $lastId, \PDO::PARAM_INT);
                        $insertHostChildStmt->bindValue(':hostId', (int) $host['host_host_id'], \PDO::PARAM_INT);
                        $insertHostChildStmt->execute();
                        $fields['dep_hostPar'] .= $host['host_host_id'] . ',';
                    }
                    $fields["dep_hostPar"] = trim($fields["dep_hostPar"], ",");

                    $selectServiceParentStmt->bindValue(':dep_id', (int) $key, \PDO::PARAM_INT);
                    $selectServiceParentStmt->execute();
                    $fields['dep_hSvPar'] = '';
                    while ($service = $selectServiceParentStmt->fetch()) {
                        $insertServiceParentStmt->bindValue(':depId', (int) $lastId, \PDO::PARAM_INT);
                        $insertServiceParentStmt->bindValue(
                            ':serviceId',
                            (int) $service['service_service_id'],
                            \PDO::PARAM_INT
                        );
                        $insertServiceParentStmt->bindValue(':hostId', (int) $service['host_host_id'], \PDO::PARAM_INT);
                        $insertServiceParentStmt->execute();
                        $fields['dep_hSvPar'] .= $service['service_service_id'] . ',';
                    }
                    $fields["dep_hSvPar"] = trim($fields["dep_hSvPar"], ",");

                    $selectServiceChildStmt->bindValue(':dep_id', (int) $key, \PDO::PARAM_INT);
                    $selectServiceChildStmt->execute();
                    $fields['dep_hSvChi'] = '';
                    while ($service = $selectServiceChildStmt->fetch()) {
                        $insertServiceChildStmt->bindValue(':depId', (int) $lastId, \PDO::PARAM_INT);
                        $insertServiceChildStmt->bindValue(
                            ':serviceId',
                            (int) $service['service_service_id'],
                            \PDO::PARAM_INT
                        );
                        $insertServiceChildStmt->bindValue(':hostId', (int) $service['host_host_id'], \PDO::PARAM_INT);
                        $insertServiceChildStmt->execute();
                        $fields['dep_hSvChi'] .= $service['service_service_id'] . ',';
                    }
                    $fields["dep_hSvChi"] = trim($fields["dep_hSvChi"], ",");
                    $oreon->CentreonLogAction->insertLog(
                        "service dependency",
                        $lastId,
                        $dep_name,
                        "a",
                        $fields
                    );
                }
            }
        }
    }
}

function updateServiceDependencyInDB($dep_id = null)
{
    if (!$dep_id) {
        exit();
    }
    updateServiceDependency($dep_id);
    updateServiceDependencyServiceParents($dep_id);
    updateServiceDependencyServiceChilds($dep_id);
    updateServiceDependencyHostChildren($dep_id);
}

function insertServiceDependencyInDB($ret = [])
{
    $dep_id = insertServiceDependency($ret);
    updateServiceDependencyServiceParents($dep_id, $ret);
    updateServiceDependencyServiceChilds($dep_id, $ret);
    updateServiceDependencyHostChildren($dep_id, $ret);
    return ($dep_id);
}

/**
 * Create a service dependency
 *
 * @param array<string, mixed> $ret
 * @return int
 */
function insertServiceDependency($ret = []): int
{
    global $form, $pearDB, $centreon;
    if (!count($ret)) {
        $ret = $form->getSubmitValues();
    }

    $resourceValues = sanitizeResourceParameters($ret);
    $statement = $pearDB->prepare(
        "INSERT INTO `dependency`
        (dep_name, dep_description, inherits_parent, execution_failure_criteria,
         notification_failure_criteria, dep_comment)
        VALUES (:depName, :depDescription, :inheritsParent, :executionFailure,
                :notificationFailure, :depComment)"
    );
    $statement->bindValue(':depName', $resourceValues['dep_name'], \PDO::PARAM_STR);
    $statement->bindValue(':depDescription', $resourceValues['dep_description'], \PDO::PARAM_STR);
    $statement->bindValue(':inheritsParent', $resourceValues['inherits_parent'], \PDO::PARAM_STR);
    $statement->bindValue(
        ':executionFailure',
        $resourceValues['execution_failure_criteria'] ?? null,
        \PDO::PARAM_STR
    );
    $statement->bindValue(
        ':notificationFailure',
        $resourceValues['notification_failure_criteria'] ?? null,
        \PDO::PARAM_STR
    );
    $statement->bindValue(':depComment', $resourceValues['dep_comment'], \PDO::PARAM_STR);
    $statement->execute();

    $depId = (int) $pearDB->lastInsertId();

    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        "service dependency",
        $depId,
        $resourceValues["dep_name"],
        "a",
        $fields
    );
    return $depId;
}

/**
 * Update a service dependency
 *
 * @param null|int $depId
 */
function updateServiceDependency($depId = null): void
{
    if (!$depId) {
        exit();
    }
    global $form, $pearDB, $centreon;

    $resourceValues = sanitizeResourceParameters($form->getSubmitValues());
    $statement = $pearDB->prepare(
        "UPDATE `dependency`
        SET dep_name = :depName,
        dep_description = :depDescription,
        inherits_parent = :inheritsParent,
        execution_failure_criteria = :executionFailure,
        notification_failure_criteria = :notificationFailure,
        dep_comment = :depComment
        WHERE dep_id = :depId"
    );
    $statement->bindValue(':depName', $resourceValues['dep_name'], \PDO::PARAM_STR);
    $statement->bindValue(':depDescription', $resourceValues['dep_description'], \PDO::PARAM_STR);
    $statement->bindValue(':inheritsParent', $resourceValues['inherits_parent'], \PDO::PARAM_STR);
    $statement->bindValue(
        ':executionFailure',
        $resourceValues['execution_failure_criteria'] ?? null,
        \PDO::PARAM_STR
    );
    $statement->bindValue(
        ':notificationFailure',
        $resourceValues['notification_failure_criteria'] ?? null,
        \PDO::PARAM_STR
    );
    $statement->bindValue(':depComment', $resourceValues['dep_comment'], \PDO::PARAM_STR);
    $statement->bindValue(':depId', $depId, \PDO::PARAM_INT);
    $statement->execute();

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($resourceValues);
    $centreon->CentreonLogAction->insertLog(
        "service dependency",
        $depId,
        $resourceValues["dep_name"],
        "c",
        $fields
    );
}

/**
 * sanitize resources parameter for Create / Update a service dependency
 *
 * @param array<string, mixed> $resources
 * @return array<string, mixed>
 */
function sanitizeResourceParameters(array $resources): array
{
    $sanitizedParameters = [];
    $sanitizedParameters['dep_name'] = \HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_name']);
    if (empty($sanitizedParameters['dep_name'])) {
        throw new InvalidArgumentException(_("Dependency name can't be empty"));
    }

    $sanitizedParameters['dep_description'] = \HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_description']);
    if (empty($sanitizedParameters['dep_description'])) {
        throw new InvalidArgumentException(_("Dependency description can't be empty"));
    }

    $resources["inherits_parent"]["inherits_parent"] == 1
        ? $sanitizedParameters["inherits_parent"] = '1'
        : $sanitizedParameters["inherits_parent"] = '0';

    if (isset($resources["execution_failure_criteria"]) && is_array($resources["execution_failure_criteria"])) {
        $sanitizedParameters['execution_failure_criteria'] = \HtmlAnalyzer::sanitizeAndRemoveTags(
            implode(
                ",",
                array_keys($resources["execution_failure_criteria"])
            )
        );
    }

    if (isset($resources["notification_failure_criteria"]) && is_array($resources["notification_failure_criteria"])) {
        $sanitizedParameters['notification_failure_criteria'] = \HtmlAnalyzer::sanitizeAndRemoveTags(
            implode(
                ",",
                array_keys($resources["notification_failure_criteria"])
            )
        );
    }
    $sanitizedParameters['dep_comment'] = \HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_comment']);

    return $sanitizedParameters;
}

function updateServiceDependencyServiceParents($dep_id = null, $ret = [])
{
    if (!$dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (!count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $statement = $pearDB->prepare("DELETE FROM dependency_serviceParent_relation WHERE dependency_dep_id = :dep_id");
    $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
    $statement->execute();
    $ret1 = $ret["dep_hSvPar"] ?? CentreonUtils::mergeWithInitialValues($form, "dep_hSvPar");
    $statement = $pearDB->prepare(
        "INSERT INTO dependency_serviceParent_relation (dependency_dep_id, service_service_id, host_host_id)
        VALUES (:dep_id, :service_id, :host_id)"
    );
    $counter = count($ret1);
    for ($i = 0; $i < $counter; $i++) {
        $exp = explode("-", $ret1[$i]);
        if (count($exp) == 2) {
            $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $exp[1], \PDO::PARAM_INT);
            $statement->bindValue(':host_id', (int) $exp[0], \PDO::PARAM_INT);
            $statement->execute();
        }
    }
}

function updateServiceDependencyServiceChilds($dep_id = null, $ret = [])
{
    if (!$dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (!count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $statement = $pearDB->prepare("DELETE FROM dependency_serviceChild_relation WHERE dependency_dep_id = :dep_id");
    $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
    $statement->execute();
    $ret1 = $ret["dep_hSvChi"] ?? CentreonUtils::mergeWithInitialValues($form, "dep_hSvChi");
    $statement = $pearDB->prepare(
        "INSERT INTO dependency_serviceChild_relation (dependency_dep_id, service_service_id, host_host_id)
        VALUES (:dep_id, :service_id, :host_id)"
    );
    $counter = count($ret1);
    for ($i = 0; $i < $counter; $i++) {
        $exp = explode("-", $ret1[$i]);
        if (count($exp) == 2) {
            $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $exp[1], \PDO::PARAM_INT);
            $statement->bindValue(':host_id', (int) $exp[0], \PDO::PARAM_INT);
            $statement->execute();
        }
    }
}

/**
 * Update Service Dependency Host Children
 * @param null|mixed $dep_id
 * @param mixed $ret
 */
function updateServiceDependencyHostChildren($dep_id = null, $ret = [])
{
    if (!$dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (!count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $statement = $pearDB->prepare("DELETE FROM dependency_hostChild_relation WHERE dependency_dep_id = :dep_id");
    $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
    $statement->execute();
    if (isset($ret["dep_hHostChi"])) {
        $ret1 = $ret["dep_hHostChi"];
    } else {
        $ret1 = CentreonUtils::mergeWithInitialValues($form, "dep_hHostChi");
    }
    $statement = $pearDB->prepare(
        "INSERT INTO dependency_hostChild_relation (dependency_dep_id, host_host_id)
        VALUES (:dep_id, :host_id)"
    );
    $counter = count($ret1);
    for ($i = 0; $i < $counter; $i++) {
        $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
        $statement->bindValue(':host_id', (int) $ret1[$i], \PDO::PARAM_INT);
        $statement->execute();
    }
}
