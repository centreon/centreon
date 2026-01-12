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

function testServiceDependencyExistence($name = null)
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
    foreach ($dependencies as $key => $value) {
        $dbResult2 = $pearDB->query("SELECT dep_name FROM `dependency` WHERE `dep_id` = '" . $key . "' LIMIT 1");
        $row = $dbResult2->fetch();

        $dbResult = $pearDB->query("DELETE FROM dependency WHERE dep_id = '" . $key . "'");
        $oreon->CentreonLogAction->insertLog('service dependency', $key, $row['dep_name'], 'd');
    }
}

function multipleServiceDependencyInDB($dependencies = [], $nbrDup = [])
{
    foreach ($dependencies as $key => $value) {
        global $pearDB, $oreon;
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
                if ($key2 != 'dep_id') {
                    $fields[$key2] = $value2;
                }
                if (isset($dep_name)) {
                    $fields['dep_name'] = $dep_name;
                }
            }
            if (isset($dep_name) && testServiceDependencyExistence($dep_name)) {
                $rq = $val ? 'INSERT INTO dependency VALUES (' . $val . ')' : null;
                $pearDB->query($rq);
                $dbResult = $pearDB->query('SELECT MAX(dep_id) FROM dependency');
                $maxId = $dbResult->fetch();
                if (isset($maxId['MAX(dep_id)'])) {
                    $query = "SELECT * FROM dependency_hostChild_relation WHERE dependency_dep_id = '" . $key . "'";
                    $dbResult = $pearDB->query($query);
                    $fields['dep_hostPar'] = '';
                    $query = 'INSERT INTO dependency_hostChild_relation VALUES (:dep_id, :host_host_id)';
                    $statement = $pearDB->prepare($query);
                    while ($host = $dbResult->fetch()) {
                        $statement->bindValue(':dep_id', (int) $maxId['MAX(dep_id)'], PDO::PARAM_INT);
                        $statement->bindValue(':host_host_id', (int) $host['host_host_id'], PDO::PARAM_INT);
                        $statement->execute();
                        $fields['dep_hostPar'] .= $host['host_host_id'] . ',';
                    }
                    $fields['dep_hostPar'] = trim($fields['dep_hostPar'], ',');

                    $query = "SELECT * FROM dependency_serviceParent_relation WHERE dependency_dep_id = '" . $key . "'";
                    $dbResult = $pearDB->query($query);
                    $fields['dep_hSvPar'] = '';
                    $query = 'INSERT INTO dependency_serviceParent_relation 
                        VALUES (:dep_id, :service_service_id, :host_host_id)';
                    $statement = $pearDB->prepare($query);
                    while ($service = $dbResult->fetch()) {
                        $statement->bindValue(':dep_id', (int) $maxId['MAX(dep_id)'], PDO::PARAM_INT);
                        $statement->bindValue(
                            ':service_service_id',
                            (int) $service['service_service_id'],
                            PDO::PARAM_INT
                        );
                        $statement->bindValue(':host_host_id', (int) $service['host_host_id'], PDO::PARAM_INT);
                        $statement->execute();
                        $fields['dep_hSvPar'] .= $service['service_service_id'] . ',';
                    }
                    $fields['dep_hSvPar'] = trim($fields['dep_hSvPar'], ',');
                    $query = "SELECT * FROM dependency_serviceChild_relation WHERE dependency_dep_id = '" . $key . "'";
                    $dbResult = $pearDB->query($query);
                    $fields['dep_hSvChi'] = '';
                    $query = 'INSERT INTO dependency_serviceChild_relation 
                        VALUES (:dep_id, :service_service_id, :host_host_id)';
                    $statement = $pearDB->prepare($query);
                    while ($service = $dbResult->fetch()) {
                        $statement->bindValue(':dep_id', (int) $maxId['MAX(dep_id)'], PDO::PARAM_INT);
                        $statement->bindValue(
                            ':service_service_id',
                            (int) $service['service_service_id'],
                            PDO::PARAM_INT
                        );
                        $statement->bindValue(':host_host_id', (int) $service['host_host_id'], PDO::PARAM_INT);
                        $statement->execute();
                        $fields['dep_hSvChi'] .= $service['service_service_id'] . ',';
                    }
                    $fields['dep_hSvChi'] = trim($fields['dep_hSvChi'], ',');
                    $oreon->CentreonLogAction->insertLog(
                        'service dependency',
                        $maxId['MAX(dep_id)'],
                        $dep_name,
                        'a',
                        $fields
                    );
                }
            }
        }
    }
}

function updateServiceDependencyInDB($dep_id = null)
{
    if (! $dep_id) {
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

    return $dep_id;
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
    $statement->bindValue(
        ':executionFailure',
        $resourceValues['execution_failure_criteria'] ?? null,
        PDO::PARAM_STR
    );
    $statement->bindValue(
        ':notificationFailure',
        $resourceValues['notification_failure_criteria'] ?? null,
        PDO::PARAM_STR
    );
    $statement->bindValue(':depComment', $resourceValues['dep_comment'], PDO::PARAM_STR);
    $statement->execute();

    $dbResult = $pearDB->query('SELECT MAX(dep_id) FROM dependency');
    $depId = $dbResult->fetch();

    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        'service dependency',
        $depId['MAX(dep_id)'],
        $resourceValues['dep_name'],
        'a',
        $fields
    );

    return (int) $depId['MAX(dep_id)'];
}

/**
 * Update a service dependency
 *
 * @param null|int $depId
 */
function updateServiceDependency($depId = null): void
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
    $statement->bindValue(
        ':executionFailure',
        $resourceValues['execution_failure_criteria'] ?? null,
        PDO::PARAM_STR
    );
    $statement->bindValue(
        ':notificationFailure',
        $resourceValues['notification_failure_criteria'] ?? null,
        PDO::PARAM_STR
    );
    $statement->bindValue(':depComment', $resourceValues['dep_comment'], PDO::PARAM_STR);
    $statement->bindValue(':depId', $depId, PDO::PARAM_INT);
    $statement->execute();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($resourceValues);
    $centreon->CentreonLogAction->insertLog(
        'service dependency',
        $depId,
        $resourceValues['dep_name'],
        'c',
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

    if (isset($resources['execution_failure_criteria']) && is_array($resources['execution_failure_criteria'])) {
        $sanitizedParameters['execution_failure_criteria'] = HtmlAnalyzer::sanitizeAndRemoveTags(
            implode(
                ',',
                array_keys($resources['execution_failure_criteria'])
            )
        );
    }

    if (isset($resources['notification_failure_criteria']) && is_array($resources['notification_failure_criteria'])) {
        $sanitizedParameters['notification_failure_criteria'] = HtmlAnalyzer::sanitizeAndRemoveTags(
            implode(
                ',',
                array_keys($resources['notification_failure_criteria'])
            )
        );
    }
    $sanitizedParameters['dep_comment'] = HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_comment']);

    return $sanitizedParameters;
}

function updateServiceDependencyServiceParents($dep_id = null, $ret = [])
{
    if (! $dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $rq = 'DELETE FROM dependency_serviceParent_relation ';
    $rq .= "WHERE dependency_dep_id = '" . $dep_id . "'";
    $dbResult = $pearDB->query($rq);
    $ret1 = $ret['dep_hSvPar'] ?? CentreonUtils::mergeWithInitialValues($form, 'dep_hSvPar');
    $counter = count($ret1);
    for ($i = 0; $i < $counter; $i++) {
        $exp = explode('-', $ret1[$i]);
        if (count($exp) == 2) {
            $rq = 'INSERT INTO dependency_serviceParent_relation ';
            $rq .= '(dependency_dep_id, service_service_id, host_host_id) ';
            $rq .= 'VALUES ';
            $rq .= "('" . $dep_id . "', '" . $exp[1] . "', '" . $exp[0] . "')";
            $dbResult = $pearDB->query($rq);
        }
    }
}

function updateServiceDependencyServiceChilds($dep_id = null, $ret = [])
{
    if (! $dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $rq = 'DELETE FROM dependency_serviceChild_relation ';
    $rq .= "WHERE dependency_dep_id = '" . $dep_id . "'";
    $dbResult = $pearDB->query($rq);
    $ret1 = $ret['dep_hSvChi'] ?? CentreonUtils::mergeWithInitialValues($form, 'dep_hSvChi');
    $counter = count($ret1);
    for ($i = 0; $i < $counter; $i++) {
        $exp = explode('-', $ret1[$i]);
        if (count($exp) == 2) {
            $rq = 'INSERT INTO dependency_serviceChild_relation ';
            $rq .= '(dependency_dep_id, service_service_id, host_host_id) ';
            $rq .= 'VALUES ';
            $rq .= "('" . $dep_id . "', '" . $exp[1] . "', '" . $exp[0] . "')";
            $dbResult = $pearDB->query($rq);
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
    if (! $dep_id) {
        exit();
    }
    global $form;
    global $pearDB;
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $rq = 'DELETE FROM dependency_hostChild_relation ';
    $rq .= "WHERE dependency_dep_id = '" . $dep_id . "'";
    $dbResult = $pearDB->query($rq);
    if (isset($ret['dep_hHostChi'])) {
        $ret1 = $ret['dep_hHostChi'];
    } else {
        $ret1 = CentreonUtils::mergeWithInitialValues($form, 'dep_hHostChi');
    }
    $counter = count($ret1);
    for ($i = 0; $i < $counter; $i++) {
        $rq = 'INSERT INTO dependency_hostChild_relation ';
        $rq .= '(dependency_dep_id, host_host_id) ';
        $rq .= 'VALUES ';
        $rq .= "('" . $dep_id . "', '" . $ret1[$i] . "')";
        $dbResult = $pearDB->query($rq);
    }
}
