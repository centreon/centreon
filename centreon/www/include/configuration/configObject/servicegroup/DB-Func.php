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

if (! isset($centreon)) {
    exit();
}

/**
 * Check whether a service group name is available, optionally excluding the current form's ID.
 *
 * @param string|null $name The service group name to check.
 * @param bool $excludeCurrentFormId When true, exclude the sg_id from the current form submission (if present) from the existence check.
 * @return bool `true` if no service group exists with the given name (name is available), `false` otherwise.
 */
function testServiceGroupExistence($name = null, bool $excludeCurrentFormId = true)
{
    global $pearDB, $form;

    $id = null;

    if ($excludeCurrentFormId && isset($form)) {
        $id = $form->getSubmitValue('sg_id');
    }
    $sgName = HtmlAnalyzer::sanitizeAndRemoveTags($name);

    $query = 'SELECT 1 FROM servicegroup WHERE sg_name = :sg_name';
    if ($id !== null) {
        $query .= ' AND sg_id <> :sgId';
    }
    $statement = $pearDB->prepare($query . ' LIMIT 1');
    $statement->bindValue(':sg_name', $sgName, PDO::PARAM_STR);
    if ($id !== null) {
        $statement->bindValue(':sgId', (int) $id, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetchColumn() === false;
}

/**
 * Enable a service group by its identifier.
 *
 * Sets the service group's activation flag in the database, signals a configuration
 * change for the service group, and records the enable action in the audit log.
 *
 * @param int|string|null $sgId The service group ID. If null or not a valid integer, no action is taken.
 */
function enableServiceGroupInDB($sgId = null)
{
    if (! $sgId) {
        return;
    }

    global $pearDB, $centreon;

    $sgId = filter_var($sgId, FILTER_VALIDATE_INT);

    $statement = $pearDB->prepare("UPDATE servicegroup SET sg_activate = '1' WHERE sg_id = :sg_id");
    $statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
    $statement->execute();

    $statement2 = $pearDB->prepare('SELECT sg_name FROM `servicegroup` WHERE `sg_id` = :sg_id LIMIT 1');
    $statement2->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
    $statement2->execute();
    $row = $statement2->fetch();
    if ($row === false) {
        return;
    }

    signalConfigurationChange('servicegroup', $sgId);
    $centreon->CentreonLogAction->insertLog('servicegroup', $sgId, $row['sg_name'], 'enable');
}

/**
 * Disable the specified service group and record the change.
 *
 * If the provided ID is falsy or no matching service group exists, no action is taken.
 * Also signals a configuration change for the service group and inserts a 'disable' audit log entry.
 *
 * @param int|null $sgId The service group ID to disable.
 */
function disableServiceGroupInDB($sgId = null)
{
    if (! $sgId) {
        return;
    }
    global $pearDB, $centreon;

    $sgId = filter_var($sgId, FILTER_VALIDATE_INT);

    $statement = $pearDB->prepare("UPDATE servicegroup SET sg_activate = '0' WHERE sg_id = :sg_id");
    $statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
    $statement->execute();

    $statement2 = $pearDB->prepare('SELECT sg_name FROM `servicegroup` WHERE `sg_id` = :sg_id LIMIT 1');
    $statement2->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
    $statement2->execute();
    $row = $statement2->fetch();
    if ($row === false) {
        return;
    }

    signalConfigurationChange('servicegroup', $sgId, [], false);
    $centreon->CentreonLogAction->insertLog('servicegroup', $sgId, $row['sg_name'], 'disable');
}

/**
 * Remove the dependency row if the specified service group is the last parent for that dependency.
 *
 * Checks how many service-group parents reference the dependency tied to the given service group,
 * and deletes the corresponding dependency record when exactly one parent remains.
 *
 * @param int $servicegroupId The ID of the service group to inspect.
 */
function removeRelationLastServicegroupDependency(int $servicegroupId): void
{
    global $pearDB;

    $statement = $pearDB->prepare(
        'SELECT count(dependency_dep_id) AS nb_dependency, dependency_dep_id AS id
        FROM dependency_servicegroupParent_relation
        WHERE dependency_dep_id = (SELECT dependency_dep_id FROM dependency_servicegroupParent_relation
                                   WHERE servicegroup_sg_id = :sg_id)
        GROUP BY dependency_dep_id'
    );
    $statement->bindValue(':sg_id', $servicegroupId, PDO::PARAM_INT);
    $statement->execute();
    $result = $statement->fetch();

    // is last parent
    if (isset($result['nb_dependency']) && $result['nb_dependency'] == 1) {
        $deleteStmt = $pearDB->prepare('DELETE FROM dependency WHERE dep_id = :dep_id');
        $deleteStmt->bindValue(':dep_id', (int) $result['id'], PDO::PARAM_INT);
        $deleteStmt->execute();
    }
}

/**
 * Delete the specified service groups whose IDs are provided as the array's keys.
 *
 * For each valid service group ID key, removes the last dependency relation if needed,
 * deletes the service group row, signals a configuration change for the deleted group,
 * and records the deletion in the action log. After processing all IDs, updates ACLs.
 *
 * @param array<int, mixed> $serviceGroups service group IDs as keys
 */
function deleteServiceGroupInDB($serviceGroups = [])
{
    global $pearDB, $centreon;

    foreach (array_keys($serviceGroups) as $key) {
        $sgId = filter_var($key, FILTER_VALIDATE_INT);
        if ($sgId === false) {
            continue;
        }

        $previousPollerIds = getPollersForConfigChangeFlagFromServicegroupId($sgId);

        removeRelationLastServicegroupDependency($sgId);
        $statement = $pearDB->prepare('SELECT sg_name FROM `servicegroup` WHERE `sg_id` = :sg_id LIMIT 1');
        $statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();
        if ($row === false) {
            continue;
        }

        $statement2 = $pearDB->prepare('DELETE FROM servicegroup WHERE sg_id = :sg_id');
        $statement2->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
        $statement2->execute();

        signalConfigurationChange('servicegroup', $sgId, $previousPollerIds);
        $centreon->CentreonLogAction->insertLog('servicegroup', $sgId, $row['sg_name'], 'd');
    }
    $centreon->user->access->updateACL();
}

/**
 * Duplicate multiple service groups according to provided counts and propagate their relations, ACLs, and configuration changes.
 *
 * Creates up to the requested number of copies for each service group whose ID is provided as a key in `$serviceGroups`. For each created duplicate this function:
 * - inserts a new service group record with a unique name,
 * - replicates servicegroup relations for the new group,
 * - duplicates ACL entries for the new group,
 * - signals a configuration change for the new group,
 * - writes an audit log entry.
 *
 * Invalid or non‑existent service group IDs are skipped. If name collisions or other constraints prevent creating the requested number of duplicates, the function will create as many as possible and write a partial‑creation warning to the PHP error log. After processing all groups the ACL is updated.
 *
 * @param array<int,mixed> $serviceGroups Service group identifiers passed as the array keys; each key is treated as the source sg_id to duplicate.
 * @param array<int,int> $nbrDup Mapping from the service group key to the number of duplicates to create (0–100). Invalid or out‑of‑range counts are ignored.
 */
function multipleServiceGroupInDB($serviceGroups = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    foreach (array_keys($serviceGroups) as $key) {
        $sgId = filter_var($key, FILTER_VALIDATE_INT);
        if ($sgId === false) {
            continue;
        }

        $statement = $pearDB->prepare('SELECT * FROM servicegroup WHERE sg_id = :sg_id LIMIT 1');
        $statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();
        if ($row === false) {
            continue;
        }

        unset($row['sg_id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO servicegroup (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $dupCount = filter_var($nbrDup[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false) {
            continue;
        }
        $originalName = $row['sg_name'];
        $suffix = 1;

        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $sgName = $originalName . '_' . $suffix;
            if (! testServiceGroupExistence($sgName, false)) {
                continue;
            }
            $i++;
            $row['sg_name'] = $sgName;
            $fields = $row;
            $pearDB->beginTransaction();
            try {
                foreach ($columns as $col) {
                    $value = $row[$col];
                    $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();
                $newSgId = (int) $pearDB->lastInsertId();
                if ($newSgId <= 0) {
                    $pearDB->rollBack();
                    continue;
                }
                $statement = $pearDB->prepare('
                    SELECT DISTINCT sgr.host_host_id, sgr.hostgroup_hg_id, sgr.service_service_id
                    FROM servicegroup_relation sgr WHERE sgr.servicegroup_sg_id = :sg_id
                ');
                $statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
                $statement->execute();
                $fields['sg_hgServices'] = '';
                $insertRelStmt = $pearDB->prepare('
                    INSERT INTO servicegroup_relation
                    (host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id)
                    VALUES (:host_host_id, :hostgroup_hg_id, :service_service_id, :servicegroup_sg_id)
                ');
                while ($service = $statement->fetch()) {
                    $bindParams = [];
                    foreach ($service as $key2 => $value2) {
                        switch ($key2) {
                            case 'host_host_id':
                                $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                                $value2
                                    ? $bindParams[':host_host_id'] = [PDO::PARAM_INT => $value2]
                                    : $bindParams[':host_host_id'] = [PDO::PARAM_NULL => null];
                                break;
                            case 'hostgroup_hg_id':
                                $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                                $value2
                                    ? $bindParams[':hostgroup_hg_id'] = [PDO::PARAM_INT => $value2]
                                    : $bindParams[':hostgroup_hg_id'] = [PDO::PARAM_NULL => null];
                                break;
                            case 'service_service_id':
                                $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                                $value2
                                    ? $bindParams[':service_service_id'] = [PDO::PARAM_INT => $value2]
                                    : $bindParams[':service_service_id'] = [PDO::PARAM_NULL => null];
                                break;
                        }
                    }
                    $bindParams[':servicegroup_sg_id'] = [PDO::PARAM_INT => $newSgId];
                    foreach ($bindParams as $token => $bindValues) {
                        foreach ($bindValues as $paramType => $value) {
                            $insertRelStmt->bindValue($token, $value, $paramType);
                        }
                    }
                    $insertRelStmt->execute();
                    $fields['sg_hgServices'] .= $service['service_service_id'] . ',';
                }
                $fields['sg_hgServices'] = trim($fields['sg_hgServices'], ',');

                CentreonACL::duplicateSgAcl([$newSgId => $sgId]);
                $pearDB->commit();
            } catch (Throwable $e) {
                if ($pearDB->inTransaction()) {
                    $pearDB->rollBack();
                }

                throw $e;
            }

            signalConfigurationChange('servicegroup', $newSgId);
            $centreon->CentreonLogAction->insertLog(
                'servicegroup',
                $newSgId,
                $sgName,
                'a',
                $fields
            );
        }
        if ($i < $dupCount) {
            error_log("Could only create {$i}/{$dupCount} duplicates for service group '{$originalName}' ({$sgId}): suffix search exhausted");
        }
    }
    $centreon->user->access->updateACL();
}

/**
 * Attach the given service group to ACL datasets referenced by the submitted resource access rules.
 *
 * For each rule ID in $submittedValues['resource_access_rules'], either appends the service group to an
 * existing dataset filter of type `servicegroup` or creates a new dataset, links it to the rule,
 * links the service group to the dataset, and creates a corresponding dataset filter.
 *
 * @param int $serviceGroupId ID of the service group to link into ACL datasets.
 * @param array $submittedValues Array of submitted form values; must contain a key
 *                              `resource_access_rules` with an iterable list of rule IDs.
 * @throws Throwable If a database transaction fails; the exception will be rethrown after rollback.
 */
function updateServiceGroupAcl(int $serviceGroupId, array $submittedValues = []): void
{
    global $pearDB;

    $ruleIds = $submittedValues['resource_access_rules'];

    foreach ($ruleIds as $ruleId) {
        $datasets = findDatasetsByRuleId($ruleId);

        /**
         * see if at least a dataset filter saved is of type servicegroup
         * if so then add the new servicegroup to the dataset
         * otherwise create a new dataset for this servicegroup
         */
        $serviceGroupDatasetFilters = array_values(
            array_filter(
                $datasets,
                fn (array $dataset) => $dataset['dataset_filter_type'] === 'servicegroup'
            )
        );

        // No dataset_filter of type service group found. Create a new one
        if ($serviceGroupDatasetFilters === []) {
            // get the dataset with the highest ID (last one added) which is the first element of the datasets array
            $lastDatasetAdded = $datasets[0];
            preg_match('/dataset_for_rule_\d+_(\d+)/', $lastDatasetAdded['dataset_name'], $matches);
            // calculate the new dataset_name
            $newDatasetName = 'dataset_for_rule_' . $ruleId . '_' . ((int) $matches[1] + 1);
            if ($pearDB->beginTransaction()) {
                try {
                    $datasetId = createNewDataset(datasetName: $newDatasetName);
                    linkDatasetToRule(datasetId: $datasetId, ruleId: $ruleId);
                    linkServiceGroupToDataset(datasetId: $datasetId, serviceGroupId: $serviceGroupId);
                    createNewDatasetFilter(datasetId: $datasetId, ruleId: $ruleId, serviceGroupId: $serviceGroupId);
                    $pearDB->commit();
                } catch (Throwable $exception) {
                    if ($pearDB->inTransaction()) {
                        $pearDB->rollBack();
                    }

                    throw $exception;
                }
            }
        } elseif ($pearDB->beginTransaction()) {
            try {
                linkServiceGroupToDataset(datasetId: $serviceGroupDatasetFilters[0]['dataset_id'], serviceGroupId: $serviceGroupId);
                // Expend the existing hostgroup dataset_filter
                $expendedResourceIds = $serviceGroupDatasetFilters[0]['dataset_filter_resources'] . ', ' . $serviceGroupId;

                updateDatasetFiltersResourceIds(
                    datasetFilterId: $serviceGroupDatasetFilters[0]['dataset_filter_id'],
                    resourceIds: $expendedResourceIds
                );
                $pearDB->commit();
            } catch (Throwable $exception) {
                if ($pearDB->inTransaction()) {
                    $pearDB->rollBack();
                }

                throw $exception;
            }
        }
    }
}

/**
 * @param int $datasetFilterId
 * @param string $resourceIds
 */
function updateDatasetFiltersResourceIds(int $datasetFilterId, string $resourceIds): void
{
    global $pearDB;

    $request = <<<'SQL'
            UPDATE dataset_filters SET resource_ids = :resourceIds WHERE `id` = :datasetFilterId
        SQL;

    $statement = $pearDB->prepare($request);
    $statement->bindValue(':datasetFilterId', $datasetFilterId, PDO::PARAM_INT);
    $statement->bindValue(':resourceIds', $resourceIds, PDO::PARAM_STR);
    $statement->execute();
}

/**
 * @param int $datasetId
 * @param int $serviceGroupId
 */
function linkServiceGroupToDataset(int $datasetId, int $serviceGroupId): void
{
    global $pearDB;

    $query = <<<'SQL'
            INSERT INTO acl_resources_sg_relations (sg_id, acl_res_id) VALUES (:serviceGroupId, :datasetId)
        SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':datasetId', $datasetId, PDO::PARAM_INT);
    $statement->bindValue(':serviceGroupId', $serviceGroupId, PDO::PARAM_INT);
    $statement->execute();
}

/**
 * @param int $datasetId
 * @param int $ruleId
 * @param int $serviceGroupId
 */
function createNewDatasetFilter(int $datasetId, int $ruleId, int $serviceGroupId): void
{
    global $pearDB;

    $query = <<<'SQL'
            INSERT INTO dataset_filters (`type`, acl_resource_id, acl_group_id, resource_ids)
            VALUES ('servicegroup', :datasetId, :ruleId, :serviceGroupId)
        SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':datasetId', $datasetId, PDO::PARAM_INT);
    $statement->bindValue(':ruleId', $ruleId, PDO::PARAM_INT);
    $statement->bindValue(':serviceGroupId', $serviceGroupId, PDO::PARAM_STR);

    $statement->execute();
}

/**
 * @param string $datasetName
 * @return int
 */
function createNewDataset(string $datasetName): int
{
    global $pearDB;
    // create new dataset
    $query = <<<'SQL'
            INSERT INTO acl_resources (acl_res_name, all_hosts, all_hostgroups, all_servicegroups, acl_res_activate, changed, cloud_specific)
            VALUES (:name, '0', '0', '0', '1', 1, 1)
        SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':name', $datasetName, PDO::PARAM_STR);
    $statement->execute();

    return $pearDB->lastInsertId();
}

/**
 * @param int $ruleId
 *
 * @throws PDOException
 *
 * @return list<array{
 *     dataset_name: string,
 *     dataset_filter_id: int,
 *     dataset_filter_parent_id: int|null,
 *     dataset_filter_type: string,
 *     dataset_filter_resources: string,
 *     dataset_id: int,
 *     rule_id: int
 * }>|array{} */
function findDatasetsByRuleId(int $ruleId): array
{
    global $pearDB;

    $request = <<<'SQL'
            SELECT
                dataset.acl_res_name AS dataset_name,
                id AS dataset_filter_id,
                parent_id AS dataset_filter_parent_id,
                type AS dataset_filter_type,
                resource_ids AS dataset_filter_resources,
                acl_resource_id AS dataset_id,
                acl_group_id AS rule_id
            FROM dataset_filters
            INNER JOIN acl_resources AS dataset
                ON dataset.acl_res_id = dataset_filters.acl_resource_id
            WHERE dataset_filters.acl_group_id = :ruleId
            ORDER BY dataset_id DESC
        SQL;

    $statement = $pearDB->prepare($request);
    $statement->bindValue(':ruleId', $ruleId, PDO::PARAM_INT);
    $statement->execute();

    if ($record = $statement->fetchAll(PDO::FETCH_ASSOC)) {
        return $record;
    }

    return [];
}

function insertServiceGroupInDB(bool $isCloudPlatform = false, array $submittedValues = [])
{
    global $centreon, $form;

    $submittedValues = $submittedValues ?: $form->getSubmitValues();

    $serviceGroupId = insertServiceGroup($submittedValues);
    updateServiceGroupServices($serviceGroupId, $submittedValues);

    // Only apply ACL for cloud context
    if ($isCloudPlatform) {
        updateServiceGroupAcl(serviceGroupId: $serviceGroupId, submittedValues: $submittedValues);
    }

    signalConfigurationChange('servicegroup', $serviceGroupId);
    $centreon->user->access->updateACL();

    return $serviceGroupId;
}

/**
 * @param int $datasetId
 * @param int $ruleId
 */
function linkDatasetToRule(int $datasetId, int $ruleId): void
{
    global $pearDB;

    // link dataset to the rule
    $query = <<<'SQL'
            INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id) VALUES (:datasetId, :ruleId)
        SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':ruleId', $ruleId, PDO::PARAM_INT);
    $statement->bindValue(':datasetId', $datasetId, PDO::PARAM_INT);
    $statement->execute();
}

function updateServiceGroupInDB(
    bool $isCloudPlatform = false,
    $serviceGroupId = null,
    $submittedValues = [],
    $increment = false,
) {
    global $centreon, $form;

    if (! $serviceGroupId) {
        return;
    }

    $submittedValues = $submittedValues ?: $form->getSubmitValues();

    $previousPollerIds = getPollersForConfigChangeFlagFromServiceGroupId($serviceGroupId);

    updateServiceGroup($serviceGroupId, $submittedValues);
    updateServiceGroupServices($serviceGroupId, $submittedValues, $increment);

    if ($isCloudPlatform) {
        updateServiceGroupAcl(serviceGroupId: $serviceGroupId, submittedValues: $submittedValues);
    }

    signalConfigurationChange('servicegroup', $serviceGroupId, $previousPollerIds);
    $centreon->user->access->updateACL();
}

/**
 * Create a new service group from provided submitted values.
 *
 * Accepted keys in $submittedValues include:
 * - `sg_name`, `sg_alias`, `sg_comment`, `geo_coords`, `sg_activate`
 * Values are sanitized/validated as appropriate before insertion.
 *
 * @param array $submittedValues Associative array of service group fields.
 * @return int The newly created service group's ID.
 */
function insertServiceGroup($submittedValues = [])
{
    global $pearDB, $centreon;

    $bindParams = [];
    foreach ($submittedValues as $key => $value) {
        switch ($key) {
            case 'sg_name':
                $value = HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $bindParams[':sg_name'] = [PDO::PARAM_STR => $value];
                break;
            case 'sg_alias':
                $value = HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $bindParams[':sg_alias'] = [PDO::PARAM_STR => $value];
                break;
            case 'sg_comment':
                $value = HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $value
                    ? $bindParams[':sg_comment'] = [PDO::PARAM_STR => $value]
                    : $bindParams[':sg_comment'] = [PDO::PARAM_NULL => null];
                break;
            case 'geo_coords':
                centreonUtils::validateGeoCoords($value)
                    ? $bindParams[':geo_coords'] = [PDO::PARAM_STR => $value]
                    : $bindParams[':geo_coords'] = [PDO::PARAM_NULL => null];
                break;
            case 'sg_activate':
                $value = filter_var($value['sg_activate'], FILTER_VALIDATE_REGEXP, [
                    'options' => [
                        'regexp' => '/^0|1$/',
                    ],
                ]);
                $value
                    ? $bindParams[':sg_activate'] = [PDO::PARAM_STR => $value]
                    : $bindParams[':sg_activate'] = [PDO::PARAM_STR => '0'];
                break;
        }
    }

    $statement = $pearDB->prepare('
        INSERT INTO servicegroup (sg_name, sg_alias, sg_comment, geo_coords, sg_activate)
        VALUES (:sg_name, :sg_alias, :sg_comment, :geo_coords, :sg_activate)
    ');
    foreach ($bindParams as $token => $bindValues) {
        foreach ($bindValues as $paramType => $value) {
            $statement->bindValue($token, $value, $paramType);
        }
    }
    $statement->execute();

    $sgId = (int) $pearDB->lastInsertId();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($submittedValues);
    $centreon->CentreonLogAction->insertLog(
        'servicegroup',
        $sgId,
        htmlentities($submittedValues['sg_name'], ENT_QUOTES, 'UTF-8'),
        'a',
        $fields
    );

    return $sgId;
}

/**
 * Update fields of an existing service group and record the change in the audit log.
 *
 * Applies sanitized and validated values from $submittedValues to the servicegroup row
 * identified by $serviceGroupId, persists the changes to the database, and inserts a
 * changelog entry describing the modifications.
 *
 * @param int $serviceGroupId The ID of the service group to update; no action is taken if falsy.
 * @param array $submittedValues Associative array of fields to update. Supported keys:
 *                              - 'sg_name' (string): sanitized name.
 *                              - 'sg_alias' (string): sanitized alias.
 *                              - 'sg_comment' (string|null): sanitized comment or null to clear.
 *                              - 'geo_coords' (string|null): validated geographical coordinates or null to clear.
 *                              - 'sg_activate' (array|mixed): activation flag; expected under 'sg_activate' key with value '0' or '1'.
 */
function updateServiceGroup($serviceGroupId, $submittedValues = [])
{
    global $pearDB, $centreon;

    if (! $serviceGroupId) {
        return;
    }

    $bindParams = [];
    $serviceGroupId = filter_var($serviceGroupId, FILTER_VALIDATE_INT);
    $bindParams[':sg_id'] = [PDO::PARAM_INT => $serviceGroupId];
    foreach ($submittedValues as $key => $value) {
        switch ($key) {
            case 'sg_name':
                $value = HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $bindParams[':sg_name'] = [PDO::PARAM_STR => $value];
                break;
            case 'sg_alias':
                $value = HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $bindParams[':sg_alias'] = [PDO::PARAM_STR => $value];
                break;
            case 'sg_comment':
                $value = HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $value
                    ? $bindParams[':sg_comment'] = [PDO::PARAM_STR => $value]
                    : $bindParams[':sg_comment'] = [PDO::PARAM_NULL => null];
                break;
            case 'geo_coords':
                centreonUtils::validateGeoCoords($value)
                    ? $bindParams[':geo_coords'] = [PDO::PARAM_STR => $value]
                    : $bindParams[':geo_coords'] = [PDO::PARAM_NULL => null];
                break;
            case 'sg_activate':
                $value = filter_var($value['sg_activate'], FILTER_VALIDATE_REGEXP, [
                    'options' => [
                        'regexp' => '/^0|1$/',
                    ],
                ]);
                $value
                    ? $bindParams[':sg_activate'] = [PDO::PARAM_STR => $value]
                    : $bindParams[':sg_activate'] = [PDO::PARAM_STR => '0'];
                break;
        }
    }

    $statement = $pearDB->prepare(
        <<<'SQL'
            UPDATE servicegroup SET
                sg_name = :sg_name,
                sg_alias = :sg_alias,
                sg_comment = :sg_comment,
                geo_coords = :geo_coords,
                sg_activate = :sg_activate
            WHERE sg_id = :sg_id
            SQL
    );

    foreach ($bindParams as $token => $bindValues) {
        foreach ($bindValues as $paramType => $value) {
            $statement->bindValue($token, $value, $paramType);
        }
    }
    $statement->execute();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($submittedValues);
    $centreon->CentreonLogAction->insertLog(
        'servicegroup',
        $serviceGroupId,
        htmlentities($submittedValues['sg_name'], ENT_QUOTES, 'UTF-8'),
        'c',
        $fields
    );
}

/**
 * Update service-to-servicegroup relations for a given service group.
 *
 * Adds relations between the specified service group and services (including
 * service templates and hostgroup services) using values from the provided
 * arrays or from the form when arrays are not supplied. If `$increment` is
 * false, existing relations for the service group are removed before new
 * relations are inserted.
 *
 * @param int|string $sgId The service group identifier.
 * @param array<string,array<int,string>> $ret Optional relation arrays. Recognized keys:
 *        - `sg_tServices`: array of "hostId-serviceId" strings for service templates.
 *        - `sg_hServices`: array of "hostId-serviceId" strings for regular services.
 *        - `sg_hgServices`: array of "hostgroupId-serviceId" strings for hostgroup services.
 *        If a key is absent the function will use form values for that relation type.
 * @param bool $increment If true, preserve existing relations and only add missing ones;
 *                       if false, remove all existing relations for the service group before inserting.
 */
function updateServiceGroupServices($sgId, $ret = [], $increment = false)
{
    if (! $sgId) {
        return;
    }
    global $pearDB, $form;

    $sgId = filter_var($sgId, FILTER_VALIDATE_INT);

    if ($increment == false && $sgId !== false) {
        $statement = $pearDB->prepare('
            DELETE FROM servicegroup_relation
            WHERE servicegroup_sg_id = :sg_id
        ');
        $statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
        $statement->execute();
    }

    // service templates
    $retTmp = $ret['sg_tServices'] ?? $form->getSubmitValue('sg_tServices');
    if ($retTmp) {
        $statement = $pearDB->prepare('
            SELECT servicegroup_sg_id service FROM servicegroup_relation
            WHERE host_host_id = :host_host_id AND service_service_id = :service_service_id
            AND servicegroup_sg_id = :sg_id
        ');

        $statement2 = $pearDB->prepare('
            INSERT INTO servicegroup_relation (host_host_id, service_service_id, servicegroup_sg_id)
            VALUES (:host_host_id, :service_service_id, :servicegroup_sg_id)
        ');
        $counter = count($retTmp);
        for ($i = 0; $i < $counter; $i++) {
            if (isset($retTmp[$i]) && $retTmp[$i]) {
                $t = preg_split("/\-/", $retTmp[$i]);
                $hostHostId = filter_var($t[0], FILTER_VALIDATE_INT);
                $serviceServiceId = filter_var($t[1], FILTER_VALIDATE_INT);
                $statement->bindValue(':host_host_id', $hostHostId, PDO::PARAM_INT);
                $statement->bindValue(':service_service_id', $serviceServiceId, PDO::PARAM_INT);
                $statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
                $statement->execute();
                if ($statement->fetch() === false) {
                    $statement2->bindValue(':host_host_id', $hostHostId, PDO::PARAM_INT);
                    $statement2->bindValue(':service_service_id', $serviceServiceId, PDO::PARAM_INT);
                    $statement2->bindValue(':servicegroup_sg_id', $sgId, PDO::PARAM_INT);
                    $statement2->execute();
                }
            }
        }
    }

    // regular services
    $retTmp = $ret['sg_hServices'] ?? CentreonUtils::mergeWithInitialValues($form, 'sg_hServices');

    $statement = $pearDB->prepare('
        SELECT servicegroup_sg_id service FROM servicegroup_relation
        WHERE host_host_id = :host_host_id AND service_service_id = :service_service_id
        AND servicegroup_sg_id = :sg_id
    ');

    $statement2 = $pearDB->prepare('
        INSERT INTO servicegroup_relation (host_host_id, service_service_id, servicegroup_sg_id)
        VALUES (:host_host_id, :service_service_id, :servicegroup_sg_id)
    ');
    $counter = count($retTmp);
    for ($i = 0; $i < $counter; $i++) {
        if (isset($retTmp[$i]) && $retTmp[$i]) {
            $t = preg_split("/\-/", $retTmp[$i]);
            $hostHostId = filter_var($t[0], FILTER_VALIDATE_INT);
            $serviceServiceId = filter_var($t[1], FILTER_VALIDATE_INT);
            $statement->bindValue(':host_host_id', $hostHostId, PDO::PARAM_INT);
            $statement->bindValue(':service_service_id', $serviceServiceId, PDO::PARAM_INT);
            $statement->bindValue(':sg_id', $sgId, PDO::PARAM_INT);
            $statement->execute();
            if ($statement->fetch() === false) {
                $statement2->bindValue(':host_host_id', $hostHostId, PDO::PARAM_INT);
                $statement2->bindValue(':service_service_id', $serviceServiceId, PDO::PARAM_INT);
                $statement2->bindValue(':servicegroup_sg_id', $sgId, PDO::PARAM_INT);
                $statement2->execute();
            }
        }
    }

    // hostgroup services
    $retTmp = $ret['sg_hgServices'] ?? CentreonUtils::mergeWithInitialValues($form, 'sg_hgServices');

    $statement = $pearDB->prepare('
        SELECT servicegroup_sg_id service FROM servicegroup_relation
        WHERE hostgroup_hg_id = :hostgroup_hg_id AND service_service_id = :service_service_id
        AND servicegroup_sg_id = :servicegroup_sg_id
    ');

    $statement2 = $pearDB->prepare('
        INSERT INTO servicegroup_relation (hostgroup_hg_id, service_service_id, servicegroup_sg_id)
        VALUES (:hostgroup_hg_id, :service_service_id, :servicegroup_sg_id)
    ');
    $counter = count($retTmp);
    for ($i = 0; $i < $counter; $i++) {
        $t = preg_split("/\-/", $retTmp[$i]);
        $hostGroupId = filter_var($t[0], FILTER_VALIDATE_INT);
        $serviceServiceId = filter_var($t[1], FILTER_VALIDATE_INT);
        $statement->bindValue(':hostgroup_hg_id', $hostGroupId, PDO::PARAM_INT);
        $statement->bindValue(':service_service_id', $serviceServiceId, PDO::PARAM_INT);
        $statement->bindValue(':servicegroup_sg_id', $sgId, PDO::PARAM_INT);
        $statement->execute();
        if ($statement->fetch() === false) {
            $statement2->bindValue(':hostgroup_hg_id', $hostGroupId, PDO::PARAM_INT);
            $statement2->bindValue(':service_service_id', $serviceServiceId, PDO::PARAM_INT);
            $statement2->bindValue(':servicegroup_sg_id', $sgId, PDO::PARAM_INT);
            $statement2->execute();
        }
    }
}

/**
 * @param int $servicegroupId
 * @return int[]
 */
function getPollersForConfigChangeFlagFromServiceGroupId(int $servicegroupId): array
{
    $hostIds = findHostsForConfigChangeFlagFromServiceGroupId($servicegroupId);

    return findPollersForConfigChangeFlagFromHostIds($hostIds);
}
