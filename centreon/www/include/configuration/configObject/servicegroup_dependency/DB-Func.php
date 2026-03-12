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

/**
 * Check whether a dependency name is available (no existing dependency uses that name).
 *
 * @param string|null $name The dependency name to check.
 * @param bool $excludeCurrentFormId If true, exclude the dependency id from the current form submission (if present) when checking for duplicates.
 * @param bool $purge If true, purge obsolete dependencies before performing the existence check.
 * @return bool `true` if no existing dependency uses the given name, `false` otherwise.
 */
function testServiceGroupDependencyExistence($name = null, bool $excludeCurrentFormId = true, bool $purge = true)
{
    global $pearDB;
    global $form;

    $name = HtmlAnalyzer::sanitizeAndRemoveTags($name ?? '');
    if ($purge) {
        CentreonDependency::purgeObsoleteDependencies($pearDB);
    }

    $id = null;
    if ($excludeCurrentFormId && isset($form)) {
        $id = $form->getSubmitValue('dep_id');
    }
    $query = 'SELECT 1 FROM dependency WHERE dep_name = :name';
    if ($id !== null) {
        $query .= ' AND dep_id <> :depId';
    }
    $statement = $pearDB->prepare($query . ' LIMIT 1');
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    if ($id !== null) {
        $statement->bindValue(':depId', (int) $id, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetchColumn() === false;
}

/**
 * Checks whether submitted parent and child service-group selections form a direct cycle.
 *
 * Examines the form's dep_sgParents and dep_sgChilds values and returns false if any parent is also listed as a child.
 *
 * @return bool `true` if no parent appears among the configured children, `false` otherwise.
 */
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

/**
 * Delete specified service group dependencies from the database and log each deletion.
 *
 * Accepts an array whose keys are dependency ids to remove; non-integer keys are ignored.
 * For each valid id, removes the dependency row and records a log entry using the dependency name
 * when available or "id:KEY" when the row cannot be found.
 *
 * @param array $dependencies Array whose keys are dependency ids to delete.
 */
function deleteServiceGroupDependencyInDB($dependencies = [])
{
    global $pearDB, $oreon;
    $selectStatement = $pearDB->prepare('SELECT dep_name FROM `dependency` WHERE `dep_id` = :dep_id LIMIT 1');
    $deleteStatement = $pearDB->prepare('DELETE FROM dependency WHERE dep_id = :dep_id');
    foreach (array_keys($dependencies) as $key) {
        $depId = filter_var($key, FILTER_VALIDATE_INT);
        if ($depId === false) {
            continue;
        }

        $selectStatement->bindValue(':dep_id', $depId, PDO::PARAM_INT);
        $selectStatement->execute();
        $row = $selectStatement->fetch();

        $deleteStatement->bindValue(':dep_id', $depId, PDO::PARAM_INT);
        $deleteStatement->execute();
        $oreon->CentreonLogAction->insertLog(
            'servicegroup dependency',
            $key,
            $row !== false ? $row['dep_name'] : "id:{$key}",
            'd'
        );
    }
}

/**
 * Duplicate multiple service group dependencies and replicate their parent/child relations.
 *
 * For each dependency identifier present in $dependencies, this function attempts to create
 * the requested number of duplicates (as specified in $nbrDup). Each duplicate receives a
 * unique suffixed name, the original dependency's column values are copied, and associated
 * parent and child service-group relations are recreated for the new dependency. Creation
 * actions are logged; a warning is emitted to error_log if the requested number of duplicates
 * could not be produced.
 *
 * @param array $dependencies Map-like array whose keys are dependency identifiers (dep_id) to process.
 * @param array $nbrDup       Associative array mapping dependency keys (same keys as $dependencies)
 *                            to the desired number of duplicates (integer, 0..100).
 *
 * @throws RuntimeException If a duplicated row is inserted but the new dependency id cannot be retrieved.
 * @throws Throwable        If a database operation fails during duplication (transactions are rolled back).
 */
function multipleServiceGroupDependencyInDB($dependencies = [], $nbrDup = [])
{
    global $pearDB, $oreon;
    $selectStmt = $pearDB->prepare(
        'SELECT * FROM dependency WHERE dep_id = :dep_id LIMIT 1'
    );
    $selectParentStmt = $pearDB->prepare(
        'SELECT DISTINCT servicegroup_sg_id FROM dependency_servicegroupParent_relation '
        . 'WHERE dependency_dep_id = :dep_id'
    );
    $insertParentStmt = $pearDB->prepare(
        'INSERT INTO dependency_servicegroupParent_relation (dependency_dep_id, servicegroup_sg_id) '
        . 'VALUES (:depId, :servicegroupId)'
    );
    $selectChildStmt = $pearDB->prepare(
        'SELECT DISTINCT servicegroup_sg_id FROM dependency_servicegroupChild_relation '
        . 'WHERE dependency_dep_id = :dep_id'
    );
    $insertChildStmt = $pearDB->prepare(
        'INSERT INTO dependency_servicegroupChild_relation (dependency_dep_id, servicegroup_sg_id) '
        . 'VALUES (:depId, :servicegroupId)'
    );

    CentreonDependency::purgeObsoleteDependencies($pearDB);

    foreach (array_keys($dependencies) as $key) {
        $depId = filter_var($key, FILTER_VALIDATE_INT);
        if ($depId === false) {
            continue;
        }

        $selectStmt->bindValue(':dep_id', $depId, PDO::PARAM_INT);
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
        // Fetch relationships once before duplication loop
        $selectParentStmt->bindValue(':dep_id', $depId, PDO::PARAM_INT);
        $selectParentStmt->execute();
        $parents = $selectParentStmt->fetchAll(PDO::FETCH_ASSOC);

        $selectChildStmt->bindValue(':dep_id', $depId, PDO::PARAM_INT);
        $selectChildStmt->execute();
        $children = $selectChildStmt->fetchAll(PDO::FETCH_ASSOC);

        $dupCount = filter_var($nbrDup[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false) {
            continue;
        }
        $originalName = $row['dep_name'];
        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $dep_name = $originalName . '_' . $suffix;
            if (! testServiceGroupDependencyExistence($dep_name, false, false)) {
                continue;
            }
            $i++;
            $fields = [];
            foreach ($row as $key2 => $value2) {
                $fields[$key2] = $key2 == 'dep_name' ? $dep_name : $value2;
            }
            $row['dep_name'] = $dep_name;

            $pearDB->beginTransaction();
            try {
                foreach ($columns as $col) {
                    $value = $row[$col];
                    $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();
                $lastId = (int) $pearDB->lastInsertId();
                if ($lastId <= 0) {
                    throw new RuntimeException('Failed to retrieve duplicated dependency id');
                }
                $fields['dep_sgParents'] = '';
                foreach ($parents as $sg) {
                    $insertParentStmt->bindValue(':depId', $lastId, PDO::PARAM_INT);
                    $insertParentStmt->bindValue(':servicegroupId', (int) $sg['servicegroup_sg_id'], PDO::PARAM_INT);
                    $insertParentStmt->execute();
                    $fields['dep_sgParents'] .= $sg['servicegroup_sg_id'] . ',';
                }
                $fields['dep_sgParents'] = trim($fields['dep_sgParents'], ',');

                $fields['dep_sgChilds'] = '';
                foreach ($children as $sg) {
                    $insertChildStmt->bindValue(':depId', $lastId, PDO::PARAM_INT);
                    $insertChildStmt->bindValue(':servicegroupId', (int) $sg['servicegroup_sg_id'], PDO::PARAM_INT);
                    $insertChildStmt->execute();
                    $fields['dep_sgChilds'] .= $sg['servicegroup_sg_id'] . ',';
                }
                $fields['dep_sgChilds'] = trim($fields['dep_sgChilds'], ',');

                $pearDB->commit();
            } catch (Throwable $e) {
                if ($pearDB->inTransaction()) {
                    $pearDB->rollBack();
                }

                throw $e;
            }
            $oreon->CentreonLogAction->insertLog(
                'servicegroup dependency',
                $lastId,
                $dep_name,
                'a',
                $fields
            );
        }
        if ($i < $dupCount) {
            error_log("Could only create {$i}/{$dupCount} duplicates for service group dependency '{$originalName}' ({$key}): suffix search exhausted");
        }
    }
}

function updateServiceGroupDependencyInDB($dep_id = null)
{
    if (! $dep_id) {
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

    return $dep_id;
}

/**
 * Insert a new service group dependency and record its creation in the changelog.
 *
 * @param array<string, mixed> $ret Optional associative array of dependency fields; when empty, values are taken from the current form submission.
 * @return int The newly inserted dependency id.
 * @throws RuntimeException If the inserted dependency id cannot be retrieved.
 */
function insertServiceGroupDependency($ret = []): int
{
    global $form, $pearDB, $centreon;
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }

    $resourceValues = sanitizeResourceParameters($ret);
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
    if ($depId <= 0) {
        throw new RuntimeException('Failed to retrieve inserted servicegroup dependency id');
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($resourceValues);
    $centreon->CentreonLogAction->insertLog(
        'servicegroup dependency',
        $depId,
        $resourceValues['dep_name'],
        'a',
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
        'servicegroup dependency',
        $depId,
        $resourceValues['dep_name'],
        'c',
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

function updateServiceGroupDependencyServiceGroupParents($dep_id = null, $ret = [])
{
    if (! $dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $statement = $pearDB->prepare('DELETE FROM dependency_servicegroupParent_relation WHERE dependency_dep_id = :dep_id');
    $statement->bindValue(':dep_id', (int) $dep_id, PDO::PARAM_INT);
    $statement->execute();
    if (isset($ret['dep_sgParents'])) {
        $ret = $ret['dep_sgParents'];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_sgParents');
    }
    $statement = $pearDB->prepare(
        'INSERT INTO dependency_servicegroupParent_relation (dependency_dep_id, servicegroup_sg_id)
        VALUES (:dep_id, :sg_id)'
    );
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $statement->bindValue(':dep_id', (int) $dep_id, PDO::PARAM_INT);
        $statement->bindValue(':sg_id', (int) $ret[$i], PDO::PARAM_INT);
        $statement->execute();
    }
}

function updateServiceGroupDependencyServiceGroupChilds($dep_id = null, $ret = [])
{
    if (! $dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $statement = $pearDB->prepare('DELETE FROM dependency_servicegroupChild_relation WHERE dependency_dep_id = :dep_id');
    $statement->bindValue(':dep_id', (int) $dep_id, PDO::PARAM_INT);
    $statement->execute();
    if (isset($ret['dep_sgChilds'])) {
        $ret = $ret['dep_sgChilds'];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_sgChilds');
    }
    $statement = $pearDB->prepare(
        'INSERT INTO dependency_servicegroupChild_relation (dependency_dep_id, servicegroup_sg_id)
        VALUES (:dep_id, :sg_id)'
    );
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $statement->bindValue(':dep_id', (int) $dep_id, PDO::PARAM_INT);
        $statement->bindValue(':sg_id', (int) $ret[$i], PDO::PARAM_INT);
        $statement->execute();
    }
}
