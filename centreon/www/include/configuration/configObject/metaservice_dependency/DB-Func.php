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
    $query = "SELECT dep_name, dep_id FROM dependency WHERE dep_name = '"
        . htmlentities($name, ENT_QUOTES, 'UTF-8') . "'";
    $dbResult = $pearDB->query($query);
    $dep = $dbResult->fetch();
    // Modif case
    if ($dbResult->rowCount() >= 1 && $dep['dep_id'] == $id) {
        return true;
    } // Duplicate entry

    return ! ($dbResult->rowCount() >= 1 && $dep['dep_id'] != $id);
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
    foreach ($dependencies as $key => $value) {
        $dbResult = $pearDB->query("DELETE FROM dependency WHERE dep_id = '" . $key . "'");
    }
}

function multipleMetaServiceDependencyInDB($dependencies = [], $nbrDup = [])
{
    foreach ($dependencies as $key => $value) {
        global $pearDB;
        $dbResult = $pearDB->query("SELECT * FROM dependency WHERE dep_id = '" . $key . "' LIMIT 1");
        $row = $dbResult->fetch();
        $row['dep_id'] = null;
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = null;
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                if ($key2 == 'dep_name') {
                    $dep_name = $value2 . '_' . $i;
                    $value2 = $value2 . '_' . $i;
                }
                $val
                    ? $val .= ($value2 != null ? (", '" . $value2 . "'") : ', NULL')
                    : $val .= ($value2 != null ? ("'" . $value2 . "'") : 'NULL');
            }
            if (testExistence($dep_name)) {
                $rq = $val ? 'INSERT INTO dependency VALUES (' . $val . ')' : null;
                $pearDB->query($rq);
                $dbResult = $pearDB->query('SELECT MAX(dep_id) FROM dependency');
                $maxId = $dbResult->fetch();
                if (isset($maxId['MAX(dep_id)'])) {
                    $query = 'SELECT DISTINCT meta_service_meta_id FROM dependency_metaserviceParent_relation '
                        . "WHERE dependency_dep_id = '" . $key . "'";
                    $dbResult = $pearDB->query($query);
                    $statement = $pearDB->prepare('INSERT INTO dependency_metaserviceParent_relation '
                        . 'VALUES (:maxId, :metaId)');
                    while ($ms = $dbResult->fetch()) {
                        $statement->bindValue(':maxId', (int) $maxId['MAX(dep_id)'], PDO::PARAM_INT);
                        $statement->bindValue(':metaId', (int) $ms['meta_service_meta_id'], PDO::PARAM_INT);
                        $statement->execute();
                    }
                    $dbResult->closeCursor();
                    $query = 'SELECT DISTINCT meta_service_meta_id FROM dependency_metaserviceChild_relation '
                        . "WHERE dependency_dep_id = '" . $key . "'";
                    $dbResult = $pearDB->query($query);
                    $childStatement = $pearDB->prepare('INSERT INTO dependency_metaserviceChild_relation '
                        . 'VALUES (:maxId, :metaId)');
                    while ($ms = $dbResult->fetch()) {
                        $childStatement->bindValue(':maxId', (int) $maxId['MAX(dep_id)'], PDO::PARAM_INT);
                        $childStatement->bindValue(':metaId', (int) $ms['meta_service_meta_id'], PDO::PARAM_INT);
                        $childStatement->execute();
                    }
                    $dbResult->closeCursor();
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

    $dbResult = $pearDB->query('SELECT MAX(dep_id) FROM dependency');
    $depId = $dbResult->fetch();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($resourceValues);
    $centreon->CentreonLogAction->insertLog(
        'metaservice dependency',
        $depId['MAX(dep_id)'],
        $resourceValues['dep_name'],
        'a',
        $fields
    );

    return (int) $depId['MAX(dep_id)'];
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
    $rq = 'DELETE FROM dependency_metaserviceParent_relation ';
    $rq .= "WHERE dependency_dep_id = '" . $dep_id . "'";
    $pearDB->query($rq);
    $ret = [];
    $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_msParents');
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $rq = 'INSERT INTO dependency_metaserviceParent_relation ';
        $rq .= '(dependency_dep_id, meta_service_meta_id) ';
        $rq .= 'VALUES ';
        $rq .= "('" . $dep_id . "', '" . $ret[$i] . "')";
        $pearDB->query($rq);
    }
}

function updateMetaServiceDependencyMetaServiceChilds($dep_id = null)
{
    if (! $dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    $rq = 'DELETE FROM dependency_metaserviceChild_relation ';
    $rq .= "WHERE dependency_dep_id = '" . $dep_id . "'";
    $pearDB->query($rq);
    $ret = [];
    $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_msChilds');
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $rq = 'INSERT INTO dependency_metaserviceChild_relation ';
        $rq .= '(dependency_dep_id, meta_service_meta_id) ';
        $rq .= 'VALUES ';
        $rq .= "('" . $dep_id . "', '" . $ret[$i] . "')";
        $pearDB->query($rq);
    }
}
