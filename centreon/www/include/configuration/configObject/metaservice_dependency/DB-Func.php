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
        'SELECT * FROM dependency WHERE dep_id = :dep_id LIMIT 1'
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
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            continue;
        }
        unset($row['dep_id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO dependency (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );
        $originalName = $row['dep_name'];
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $dep_name = $originalName . '_' . $i;
            if (testExistence($dep_name)) {
                $row['dep_name'] = $dep_name;

                foreach ($columns as $col) {
                    $value = $row[$col];
                    $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
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
