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

if (! isset($oreon)) {
    exit();
}

function testExistence($name = null)
{
    global $pearDB;
    global $form;

    CentreonDependency::purgeObsoleteDependencies($pearDB);

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('dep_id');
    }
    $statement = $pearDB->prepare('SELECT dep_name, dep_id FROM dependency WHERE dep_name = :name');
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    $statement->execute();
    $dep = $statement->fetch();
    if ($dep === false) {
        return true;
    }

    return $dep['dep_id'] == $id;
}

function testCycle($childs = null)
{
    global $pearDB;
    global $form;
    $parents = [];
    $childs = [];
    if (isset($form)) {
        $parents = $form->getSubmitValue('dep_msParents');
        $childs = $form->getSubmitValue('dep_msChilds');
        $childs = array_flip($childs);
    }
    foreach ($parents as $parent) {
        if (array_key_exists($parent, $childs)) {
            return false;
        }
    }

    return true;
}

function deleteMetaServiceDependencyInDB($dependencies = [])
{
    global $pearDB;
    $statement = $pearDB->prepare('DELETE FROM dependency WHERE dep_id = :dep_id');
    foreach (array_keys($dependencies) as $key) {
        $statement->bindValue(':dep_id', (int) $key, PDO::PARAM_INT);
        $statement->execute();
    }
}

function multipleMetaServiceDependencyInDB($dependencies = [], $nbrDup = [])
{
    global $pearDB;
    $selectStmt = $pearDB->prepare(
        'SELECT dep_name, dep_description, inherits_parent, execution_failure_criteria,
                notification_failure_criteria, dep_comment
        FROM dependency WHERE dep_id = :dep_id LIMIT 1'
    );
    $insertStmt = $pearDB->prepare(
        'INSERT INTO dependency
        (dep_name, dep_description, inherits_parent, execution_failure_criteria,
         notification_failure_criteria, dep_comment)
        VALUES (:dep_name, :dep_description, :inherits_parent, :execution_failure_criteria,
         :notification_failure_criteria, :dep_comment)'
    );
    $selectParentStmt = $pearDB->prepare(
        'SELECT DISTINCT meta_service_meta_id FROM dependency_metaserviceParent_relation '
        . 'WHERE dependency_dep_id = :dep_id'
    );
    $insertParentStmt = $pearDB->prepare(
        'INSERT INTO dependency_metaserviceParent_relation (dependency_dep_id, meta_service_meta_id) '
        . 'VALUES (:depId, :metaId)'
    );
    $selectChildStmt = $pearDB->prepare(
        'SELECT DISTINCT meta_service_meta_id FROM dependency_metaserviceChild_relation '
        . 'WHERE dependency_dep_id = :dep_id'
    );
    $insertChildStmt = $pearDB->prepare(
        'INSERT INTO dependency_metaserviceChild_relation (dependency_dep_id, meta_service_meta_id) '
        . 'VALUES (:depId, :metaId)'
    );

    foreach (array_keys($dependencies) as $key) {
        $selectStmt->bindValue(':dep_id', (int) $key, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch();
        if ($row === false) {
            continue;
        }
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $dep_name = $row['dep_name'] . '_' . $i;
            if (testExistence($dep_name)) {
                $insertStmt->bindValue(':dep_name', $dep_name, PDO::PARAM_STR);
                $insertStmt->bindValue(':dep_description', $row['dep_description'], PDO::PARAM_STR);
                $insertStmt->bindValue(':inherits_parent', $row['inherits_parent'], PDO::PARAM_STR);
                $insertStmt->bindValue(':execution_failure_criteria', $row['execution_failure_criteria'], PDO::PARAM_STR);
                $insertStmt->bindValue(':notification_failure_criteria', $row['notification_failure_criteria'], PDO::PARAM_STR);
                $insertStmt->bindValue(':dep_comment', $row['dep_comment'], PDO::PARAM_STR);
                $insertStmt->execute();
                $lastId = (int) $pearDB->lastInsertId();
                if ($lastId > 0) {
                    $selectParentStmt->bindValue(':dep_id', (int) $key, PDO::PARAM_INT);
                    $selectParentStmt->execute();
                    while ($ms = $selectParentStmt->fetch()) {
                        $insertParentStmt->bindValue(':depId', (int) $lastId, PDO::PARAM_INT);
                        $insertParentStmt->bindValue(':metaId', (int) $ms['meta_service_meta_id'], PDO::PARAM_INT);
                        $insertParentStmt->execute();
                    }
                    $selectParentStmt->closeCursor();
                    $selectChildStmt->bindValue(':dep_id', (int) $key, PDO::PARAM_INT);
                    $selectChildStmt->execute();
                    while ($ms = $selectChildStmt->fetch()) {
                        $insertChildStmt->bindValue(':depId', (int) $lastId, PDO::PARAM_INT);
                        $insertChildStmt->bindValue(':metaId', (int) $ms['meta_service_meta_id'], PDO::PARAM_INT);
                        $insertChildStmt->execute();
                    }
                    $selectChildStmt->closeCursor();
                }
            }
        }
    }
}

function updateMetaServiceDependencyInDB($dep_id = null)
{
    if (! $dep_id) {
        exit();
    }
    updateMetaServiceDependency($dep_id);
    updateMetaServiceDependencyMetaServiceParents($dep_id);
    updateMetaServiceDependencyMetaServiceChilds($dep_id);
}

function insertMetaServiceDependencyInDB()
{
    $dep_id = insertMetaServiceDependency();
    updateMetaServiceDependencyMetaServiceParents($dep_id);
    updateMetaServiceDependencyMetaServiceChilds($dep_id);

    return $dep_id;
}

/**
 * Create a metaservice dependency
 *
 * @return int
 */
function insertMetaServiceDependency(): int
{
    global $form, $pearDB, $centreon;
    $resourceValues = sanitizeResourceParameters($form->getSubmitValues());

    $statement = $pearDB->prepare(
        'INSERT INTO `dependency`
        (dep_name, dep_description, inherits_parent, execution_failure_criteria,
         notification_failure_criteria, dep_comment)
        VALUES (:depName, :depDescription, :inheritsParent, :executionFailure,
                :notificationFailure, :depComment)'
    );
    $statement->bindValue(':depName', $resourceValues['dep_name'], PDO::PARAM_STR);
    $statement->bindValue(':depDescription', $resourceValues['dep_description'], PDO::PARAM_STR);
    $statement->bindValue(':inheritsParent', $resourceValues['inherits_parent'], PDO::PARAM_STR);
    $statement->bindValue(':executionFailure', $resourceValues['execution_failure_criteria'], PDO::PARAM_STR);
    $statement->bindValue(':notificationFailure', $resourceValues['notification_failure_criteria'], PDO::PARAM_STR);
    $statement->bindValue(':depComment', $resourceValues['dep_comment'], PDO::PARAM_STR);
    $statement->execute();

    $depId = (int) $pearDB->lastInsertId();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($resourceValues);
    $centreon->CentreonLogAction->insertLog(
        'metaservice dependency',
        $depId,
        $resourceValues['dep_name'],
        'a',
        $fields
    );

    return $depId;
}

/**
 * Update a metaservice dependency
 *
 * @param null|int $depId
 */
function updateMetaServiceDependency($depId = null): void
{
    if (! $depId) {
        exit();
    }
    global $form, $pearDB, $centreon;

    $resourceValues = sanitizeResourceParameters($form->getSubmitValues());
    $statement = $pearDB->prepare(
        'UPDATE `dependency`
        SET dep_name = :depName,
        dep_description = :depDescription,
        inherits_parent = :inheritsParent,
        execution_failure_criteria = :executionFailure,
        notification_failure_criteria = :notificationFailure,
        dep_comment = :depComment
        WHERE dep_id = :depId'
    );
    $statement->bindValue(':depName', $resourceValues['dep_name'], PDO::PARAM_STR);
    $statement->bindValue(':depDescription', $resourceValues['dep_description'], PDO::PARAM_STR);
    $statement->bindValue(':inheritsParent', $resourceValues['inherits_parent'], PDO::PARAM_STR);
    $statement->bindValue(':executionFailure', $resourceValues['execution_failure_criteria'], PDO::PARAM_STR);
    $statement->bindValue(':notificationFailure', $resourceValues['notification_failure_criteria'], PDO::PARAM_STR);
    $statement->bindValue(':depComment', $resourceValues['dep_comment'], PDO::PARAM_STR);
    $statement->bindValue(':depId', $depId, PDO::PARAM_INT);
    $statement->execute();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($resourceValues);
    $centreon->CentreonLogAction->insertLog(
        'metaservice dependency',
        $depId,
        $resourceValues['dep_name'],
        'c',
        $fields
    );
}

/**
 * sanitize resources parameter for Create / Update a meta service dependency
 *
 * @param array<string, mixed> $resources
 * @return array<string, mixed>
 */
function sanitizeResourceParameters(array $resources): array
{
    $sanitizedParameters = [];
    $sanitizedParameters['dep_name'] = HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_name']);
    if (empty($sanitizedParameters['dep_name'])) {
        throw new InvalidArgumentException(_("Dependency name can't be empty"));
    }

    $sanitizedParameters['dep_description'] = HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_description']);
    if (empty($sanitizedParameters['dep_description'])) {
        throw new InvalidArgumentException(_("Dependency description can't be empty"));
    }

    $resources['inherits_parent']['inherits_parent'] == 1
        ? $sanitizedParameters['inherits_parent'] = '1'
        : $sanitizedParameters['inherits_parent'] = '0';

    $sanitizedParameters['execution_failure_criteria'] = HtmlAnalyzer::sanitizeAndRemoveTags(
        implode(
            ',',
            array_keys($resources['execution_failure_criteria'])
        )
    );

    $sanitizedParameters['notification_failure_criteria'] = HtmlAnalyzer::sanitizeAndRemoveTags(
        implode(
            ',',
            array_keys($resources['notification_failure_criteria'])
        )
    );
    $sanitizedParameters['dep_comment'] = HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_comment']);

    return $sanitizedParameters;
}

function updateMetaServiceDependencyMetaServiceParents($dep_id = null)
{
    if (! $dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    $statement = $pearDB->prepare('DELETE FROM dependency_metaserviceParent_relation WHERE dependency_dep_id = :dep_id');
    $statement->bindValue(':dep_id', (int) $dep_id, PDO::PARAM_INT);
    $statement->execute();
    $ret = [];
    $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_msParents');
    $statement = $pearDB->prepare(
        'INSERT INTO dependency_metaserviceParent_relation (dependency_dep_id, meta_service_meta_id)
        VALUES (:dep_id, :meta_id)'
    );
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $statement->bindValue(':dep_id', (int) $dep_id, PDO::PARAM_INT);
        $statement->bindValue(':meta_id', (int) $ret[$i], PDO::PARAM_INT);
        $statement->execute();
    }
}

function updateMetaServiceDependencyMetaServiceChilds($dep_id = null)
{
    if (! $dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    $statement = $pearDB->prepare('DELETE FROM dependency_metaserviceChild_relation WHERE dependency_dep_id = :dep_id');
    $statement->bindValue(':dep_id', (int) $dep_id, PDO::PARAM_INT);
    $statement->execute();
    $ret = [];
    $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_msChilds');
    $statement = $pearDB->prepare(
        'INSERT INTO dependency_metaserviceChild_relation (dependency_dep_id, meta_service_meta_id)
        VALUES (:dep_id, :meta_id)'
    );
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $statement->bindValue(':dep_id', (int) $dep_id, PDO::PARAM_INT);
        $statement->bindValue(':meta_id', (int) $ret[$i], PDO::PARAM_INT);
        $statement->execute();
    }
}
