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

function testServiceGroupDependencyExistence($name = null)
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

function testServiceGroupDependencyCycle($childs = null)
{
    global $pearDB;
    global $form;
    $parents = [];
    $childs = [];
    if (isset($form)) {
        $parents = $form->getSubmitValue('dep_sgParents');
        $childs = $form->getSubmitValue('dep_sgChilds');
        $childs = array_flip($childs);
    }
    foreach ($parents as $parent) {
        if (array_key_exists($parent, $childs)) {
            return false;
        }
    }
    return true;
}

function deleteServiceGroupDependencyInDB($dependencies = [])
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
        $oreon->CentreonLogAction->insertLog("servicegroup dependency", $key, $row['dep_name'], "d");
    }
}

function multipleServiceGroupDependencyInDB($dependencies = [], $nbrDup = [])
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
    $selectParentStmt = $pearDB->prepare(
        "SELECT DISTINCT servicegroup_sg_id FROM dependency_servicegroupParent_relation "
        . "WHERE dependency_dep_id = :dep_id"
    );
    $insertParentStmt = $pearDB->prepare(
        "INSERT INTO dependency_servicegroupParent_relation (dependency_dep_id, servicegroup_sg_id) "
        . "VALUES (:depId, :servicegroupId)"
    );
    $selectChildStmt = $pearDB->prepare(
        "SELECT DISTINCT servicegroup_sg_id FROM dependency_servicegroupChild_relation "
        . "WHERE dependency_dep_id = :dep_id"
    );
    $insertChildStmt = $pearDB->prepare(
        "INSERT INTO dependency_servicegroupChild_relation (dependency_dep_id, servicegroup_sg_id) "
        . "VALUES (:depId, :servicegroupId)"
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
            if (testServiceGroupDependencyExistence($dep_name)) {
                $insertStmt->bindValue(':dep_name', $dep_name, \PDO::PARAM_STR);
                $insertStmt->bindValue(':dep_description', $row['dep_description'], \PDO::PARAM_STR);
                $insertStmt->bindValue(':inherits_parent', $row['inherits_parent'], \PDO::PARAM_STR);
                $insertStmt->bindValue(':execution_failure_criteria', $row['execution_failure_criteria'], \PDO::PARAM_STR);
                $insertStmt->bindValue(':notification_failure_criteria', $row['notification_failure_criteria'], \PDO::PARAM_STR);
                $insertStmt->bindValue(':dep_comment', $row['dep_comment'], \PDO::PARAM_STR);
                $insertStmt->execute();
                $lastId = (int) $pearDB->lastInsertId();
                if ($lastId > 0) {
                    $selectParentStmt->bindValue(':dep_id', (int) $key, \PDO::PARAM_INT);
                    $selectParentStmt->execute();
                    $fields["dep_sgParents"] = "";
                    while ($sg = $selectParentStmt->fetch()) {
                        $insertParentStmt->bindValue(':depId', (int) $lastId, \PDO::PARAM_INT);
                        $insertParentStmt->bindValue(':servicegroupId', (int) $sg['servicegroup_sg_id'], \PDO::PARAM_INT);
                        $insertParentStmt->execute();
                        $fields["dep_sgParents"] .= $sg['servicegroup_sg_id'] . ",";
                    }
                    $fields["dep_sgParents"] = trim($fields["dep_sgParents"], ",");
                    $selectParentStmt->closeCursor();
                    $selectChildStmt->bindValue(':dep_id', (int) $key, \PDO::PARAM_INT);
                    $selectChildStmt->execute();
                    $fields["dep_sgChilds"] = "";
                    while ($sg = $selectChildStmt->fetch()) {
                        $insertChildStmt->bindValue(':depId', (int) $lastId, \PDO::PARAM_INT);
                        $insertChildStmt->bindValue(':servicegroupId', (int) $sg['servicegroup_sg_id'], \PDO::PARAM_INT);
                        $insertChildStmt->execute();
                        $fields["dep_sgChilds"] .= $sg['servicegroup_sg_id'] . ",";
                    }
                    $selectChildStmt->closeCursor();
                    $fields["dep_sgChilds"] = trim($fields["dep_sgChilds"], ",");
                    $oreon->CentreonLogAction->insertLog(
                        "servicegroup dependency",
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

function updateServiceGroupDependencyInDB($dep_id = null)
{
    if (!$dep_id) {
        exit();
    }
    updateServiceGroupDependency($dep_id);
    updateServiceGroupDependencyServiceGroupParents($dep_id);
    updateServiceGroupDependencyServiceGroupChilds($dep_id);
}

function insertServiceGroupDependencyInDB($ret = [])
{
    $dep_id = insertServiceGroupDependency($ret);
    updateServiceGroupDependencyServiceGroupParents($dep_id, $ret);
    updateServiceGroupDependencyServiceGroupChilds($dep_id, $ret);
    return ($dep_id);
}

/**
 * Create a service group dependency
 *
 * @param array<string, mixed> $ret
 * @return int
 */
function insertServiceGroupDependency($ret = []): int
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
    $statement->bindValue(':executionFailure', $resourceValues['execution_failure_criteria'], \PDO::PARAM_STR);
    $statement->bindValue(':notificationFailure', $resourceValues['notification_failure_criteria'], \PDO::PARAM_STR);
    $statement->bindValue(':depComment', $resourceValues['dep_comment'], \PDO::PARAM_STR);
    $statement->execute();

    $depId = (int) $pearDB->lastInsertId();

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($resourceValues);
    $centreon->CentreonLogAction->insertLog(
        "servicegroup dependency",
        $depId,
        $resourceValues["dep_name"],
        "a",
        $fields
    );
    return $depId;
}

/**
 * Update a service group dependency
 *
 * @param null|int $depId
 */
function updateServiceGroupDependency($depId = null): void
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
    $statement->bindValue(':executionFailure', $resourceValues['execution_failure_criteria'], \PDO::PARAM_STR);
    $statement->bindValue(':notificationFailure', $resourceValues['notification_failure_criteria'], \PDO::PARAM_STR);
    $statement->bindValue(':depComment', $resourceValues['dep_comment'], \PDO::PARAM_STR);
    $statement->bindValue(':depId', $depId, \PDO::PARAM_INT);
    $statement->execute();

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($resourceValues);
    $centreon->CentreonLogAction->insertLog(
        "servicegroup dependency",
        $depId,
        $resourceValues["dep_name"],
        "c",
        $fields
    );
}

/**
 * sanitize resources parameter for Create / Update a service group dependency
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

    $sanitizedParameters['execution_failure_criteria'] = \HtmlAnalyzer::sanitizeAndRemoveTags(
        implode(
            ",",
            array_keys($resources["execution_failure_criteria"])
        )
    );

    $sanitizedParameters['notification_failure_criteria'] = \HtmlAnalyzer::sanitizeAndRemoveTags(
        implode(
            ",",
            array_keys($resources["notification_failure_criteria"])
        )
    );
    $sanitizedParameters['dep_comment'] = \HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_comment']);

    return $sanitizedParameters;
}

function updateServiceGroupDependencyServiceGroupParents($dep_id = null, $ret = [])
{
    if (!$dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (!count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $statement = $pearDB->prepare("DELETE FROM dependency_servicegroupParent_relation WHERE dependency_dep_id = :dep_id");
    $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
    $statement->execute();
    if (isset($ret["dep_sgParents"])) {
        $ret = $ret["dep_sgParents"];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_sgParents');
    }
    $statement = $pearDB->prepare(
        "INSERT INTO dependency_servicegroupParent_relation (dependency_dep_id, servicegroup_sg_id)
        VALUES (:dep_id, :sg_id)"
    );
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
        $statement->bindValue(':sg_id', (int) $ret[$i], \PDO::PARAM_INT);
        $statement->execute();
    }
}

function updateServiceGroupDependencyServiceGroupChilds($dep_id = null, $ret = [])
{
    if (!$dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (!count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $statement = $pearDB->prepare("DELETE FROM dependency_servicegroupChild_relation WHERE dependency_dep_id = :dep_id");
    $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
    $statement->execute();
    if (isset($ret["dep_sgChilds"])) {
        $ret = $ret["dep_sgChilds"];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_sgChilds');
    }
    $statement = $pearDB->prepare(
        "INSERT INTO dependency_servicegroupChild_relation (dependency_dep_id, servicegroup_sg_id)
        VALUES (:dep_id, :sg_id)"
    );
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $statement->bindValue(':dep_id', (int) $dep_id, \PDO::PARAM_INT);
        $statement->bindValue(':sg_id', (int) $ret[$i], \PDO::PARAM_INT);
        $statement->execute();
    }
}
