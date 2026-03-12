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
 * Determine whether a topology name is unused in the acl_topology table.
 *
 * @param string|null $topologyName The topology name to check.
 * @param bool $excludeCurrentFormId When true, exclude the current form's topology id (from submitted lca_id) from the uniqueness check.
 * @return bool `true` if no matching topology exists (name is unused, considering the exclusion), `false` otherwise.
 */
function hasTopologyNameNeverUsed($topologyName = null, bool $excludeCurrentFormId = true)
{
    global $pearDB, $form;

    $topologyId = null;
    if ($excludeCurrentFormId && isset($form)) {
        $topologyId = $form->getSubmitValue('lca_id');
    }
    $query = 'SELECT 1 FROM `acl_topology` WHERE acl_topo_name = :topology_name';
    if ($topologyId !== null) {
        $query .= ' AND acl_topo_id <> :aclTopoId';
    }
    $prepareSelect = $pearDB->prepare($query . ' LIMIT 1');
    $prepareSelect->bindValue(':topology_name', $topologyName, PDO::PARAM_STR);
    if ($topologyId !== null) {
        $prepareSelect->bindValue(':aclTopoId', (int) $topologyId, PDO::PARAM_INT);
    }
    $prepareSelect->execute();

    return $prepareSelect->fetchColumn() === false;
}

/**
 * Enable one or more ACL topology entries by setting their activation flag and recording the change in the action log.
 *
 * If a single ACL id is provided via $aclTopologyId it is treated as the sole id to enable; otherwise $acls may contain multiple ids (array keys are treated as topology ids).
 *
 * @global CentreonDB $pearDB Database connection used to update ACL records.
 * @global Centreon $centreon Centreon context used to record log actions.
 * @param int|null $aclTopologyId Optional single ACL topology id to enable.
 * @param array $acls Optional array of ACL topology ids to enable (array keys are treated as ids).
 */
function enableLCAInDB($aclTopologyId = null, $acls = [])
{
    global $pearDB, $centreon;

    if (! is_int($aclTopologyId) && empty($acls)) {
        return;
    }
    if (is_int($aclTopologyId)) {
        $acls = [$aclTopologyId => '1'];
    }

    foreach (array_keys($acls) as $currentAclTopologyId) {
        $validTopologyId = filter_var($currentAclTopologyId, FILTER_VALIDATE_INT);
        if ($validTopologyId === false) {
            continue;
        }

        $prepareUpdate = $pearDB->prepare(
            "UPDATE `acl_topology` SET acl_topo_activate = '1' "
            . 'WHERE `acl_topo_id` = :topology_id'
        );
        $prepareUpdate->bindValue(
            ':topology_id',
            $validTopologyId,
            PDO::PARAM_INT
        );

        if (! $prepareUpdate->execute()) {
            continue;
        }

        $prepareSelect = $pearDB->prepare(
            'SELECT acl_topo_name FROM `acl_topology` '
            . 'WHERE acl_topo_id = :topology_id LIMIT 1'
        );
        $prepareSelect->bindValue(
            ':topology_id',
            $currentAclTopologyId,
            PDO::PARAM_INT
        );
        $prepareSelect->execute();
        $result = $prepareSelect->fetch(PDO::FETCH_ASSOC);
        $centreon->CentreonLogAction->insertLog(
            'menu access',
            $currentAclTopologyId,
            $result !== false ? $result['acl_topo_name'] : "id:{$currentAclTopologyId}",
            'enable'
        );
    }
}

/**
 * Disable one or more ACL topologies.
 *
 * If a single topology id is passed via `$aclTopologyId`, that id will be disabled.
 * Otherwise the keys of `$acls` are treated as topology ids to disable.
 *
 * @param int|null $aclTopologyId ACL topology id to disable (optional).
 * @param array $acls Associative array whose keys are ACL topology ids to disable.
 */
function disableLCAInDB($aclTopologyId = null, $acls = [])
{
    global $pearDB, $centreon;

    if (! is_int($aclTopologyId) && empty($acls)) {
        return;
    }
    if (is_int($aclTopologyId)) {
        $acls = [$aclTopologyId => '1'];
    }

    foreach (array_keys($acls) as $currentTopologyId) {
        $validTopologyId = filter_var($currentTopologyId, FILTER_VALIDATE_INT);
        if ($validTopologyId === false) {
            continue;
        }

        $prepareUpdate = $pearDB->prepare(
            "UPDATE `acl_topology` SET acl_topo_activate = '0' "
            . 'WHERE `acl_topo_id` = :topology_id'
        );
        $prepareUpdate->bindValue(
            ':topology_id',
            $validTopologyId,
            PDO::PARAM_INT
        );

        if (! $prepareUpdate->execute()) {
            continue;
        }

        $prepareSelect = $pearDB->prepare(
            'SELECT acl_topo_name FROM `acl_topology` '
            . 'WHERE acl_topo_id = :topology_id LIMIT 1'
        );
        $prepareSelect->bindValue(
            ':topology_id',
            $currentTopologyId,
            PDO::PARAM_INT
        );
        $prepareSelect->execute();
        $result = $prepareSelect->fetch(PDO::FETCH_ASSOC);
        $centreon->CentreonLogAction->insertLog(
            'menu access',
            $currentTopologyId,
            $result !== false ? $result['acl_topo_name'] : "id:{$currentTopologyId}",
            'disable'
        );
    }
}

/**
 * Delete the specified ACL topologies and record deletion events.
 *
 * For each array key interpreted as an ACL topology identifier, removes the
 * corresponding row from `acl_topology` if the key is a valid integer and
 * inserts a deletion log using the topology name when available or the id
 * otherwise. Invalid/non-integer keys are skipped.
 *
 * @param array $acls Array whose keys are ACL topology ids to delete.
 */
function deleteLCAInDB($acls = [])
{
    global $pearDB, $centreon;

    foreach (array_keys($acls) as $currentTopologyId) {
        $validTopologyId = filter_var($currentTopologyId, FILTER_VALIDATE_INT);
        if ($validTopologyId === false) {
            continue;
        }

        $prepareSelect = $pearDB->prepare(
            'SELECT acl_topo_name FROM `acl_topology` '
            . 'WHERE acl_topo_id = :topology_id LIMIT 1'
        );
        $prepareSelect->bindValue(
            ':topology_id',
            $validTopologyId,
            PDO::PARAM_INT
        );

        $prepareSelect->execute();
        $result = $prepareSelect->fetch(PDO::FETCH_ASSOC);

        $prepareDelete = $pearDB->prepare(
            'DELETE FROM `acl_topology` WHERE acl_topo_id = :topology_id'
        );
        $prepareDelete->bindValue(
            ':topology_id',
            $validTopologyId,
            PDO::PARAM_INT
        );
        $prepareDelete->execute();
        $centreon->CentreonLogAction->insertLog(
            'menu access',
            $currentTopologyId,
            $result !== false ? $result['acl_topo_name'] : "id:{$currentTopologyId}",
            'd'
        );
    }
}

/**
 * Create multiple duplicates of specified ACL topologies.
 *
 * For each valid topology id in $acls, creates up to the requested number of duplicates with unique
 * names formed by appending an incremental suffix. Each created duplicate contains the same
 * topology fields and preserves its relations and group mappings. Creation of each duplicate is
 * performed inside a transaction; a failed duplicate is rolled back and the exception is rethrown.
 * Invalid topology ids are skipped. If not all requested duplicates can be created because unique
 * names cannot be found, a warning is emitted to the error log.
 *
 * @param array $acls Associative array whose keys are source ACL topology ids to duplicate.
 * @param array $duplicateNbr Associative array mapping source topology id to the desired number of duplicates (0–100).
 * @throws Throwable If a database error occurs while creating a duplicate (transaction is rolled back and the exception is propagated).
 */
function multipleLCAInDB($acls = [], $duplicateNbr = [])
{
    global $pearDB, $centreon;

    $selectStmt = $pearDB->prepare(
        'SELECT * FROM `acl_topology` WHERE acl_topo_id = :topology_id LIMIT 1'
    );

    $prepareInsertRelation = $pearDB->prepare(
        'INSERT INTO acl_topology_relations '
        . '(acl_topo_id, topology_topology_id, access_right) '
        . '(SELECT :new_topology_id, topology_topology_id, access_right '
        . 'FROM acl_topology_relations '
        . 'WHERE acl_topo_id = :current_topology_id)'
    );

    $prepareInsertGroup = $pearDB->prepare(
        'INSERT INTO acl_group_topology_relations '
        . '(acl_topology_id, acl_group_id) '
        . '(SELECT :new_topology_id, acl_group_id '
        . 'FROM acl_group_topology_relations '
        . 'WHERE acl_topology_id = :current_topology_id)'
    );

    foreach (array_keys($acls) as $currentTopologyId) {
        $validTopologyId = filter_var($currentTopologyId, FILTER_VALIDATE_INT);
        if ($validTopologyId === false) {
            continue;
        }

        $selectStmt->bindValue(':topology_id', $validTopologyId, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            continue;
        }

        unset($row['acl_topo_id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_topology (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $dupCount = filter_var($duplicateNbr[$currentTopologyId] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false) {
            continue;
        }
        $originalName = $row['acl_topo_name'];
        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $aclName = $originalName . '_' . $suffix;
            if (! hasTopologyNameNeverUsed($aclName, false)) {
                continue;
            }
            $i++;
            $row['acl_topo_name'] = $aclName;

            $pearDB->beginTransaction();
            try {
                foreach ($columns as $col) {
                    $value = $row[$col];
                    $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();
                $newTopologyId = (int) $pearDB->lastInsertId();

                if ($newTopologyId <= 0) {
                    $pearDB->rollBack();

                    continue;
                }

                $prepareInsertRelation->bindValue(':new_topology_id', $newTopologyId, PDO::PARAM_INT);
                $prepareInsertRelation->bindValue(':current_topology_id', $validTopologyId, PDO::PARAM_INT);
                $prepareInsertRelation->execute();

                $prepareInsertGroup->bindValue(':new_topology_id', $newTopologyId, PDO::PARAM_INT);
                $prepareInsertGroup->bindValue(':current_topology_id', $validTopologyId, PDO::PARAM_INT);
                $prepareInsertGroup->execute();

                $pearDB->commit();
            } catch (Throwable $e) {
                if ($pearDB->inTransaction()) {
                    $pearDB->rollBack();
                }

                throw $e;
            }

            $fields = $row;
            $centreon->CentreonLogAction->insertLog(
                'menu access',
                $newTopologyId,
                $aclName,
                'a',
                $fields
            );
        }
        if ($i < $dupCount) {
            error_log("Could only create {$i}/{$dupCount} duplicates for menu ACL '{$originalName}' ({$currentTopologyId}): suffix search exhausted");
        }
    }
}

/**
 * Update an ACL topology along with its relations and group mappings, and record the change in the audit log.
 *
 * Performs the update within a database transaction and ensures related topology relations and group associations are updated together.
 *
 * @global HTML_QuickFormCustom $form
 * @global Centreon $centreon
 * @param int $aclId ACL topology identifier to update.
 * @throws Throwable If an error occurs during the update; any active transaction will be rolled back.
 */
function updateLCAInDB($aclId = null)
{
    global $form, $centreon, $pearDB;
    if (! $aclId) {
        return;
    }

    $pearDB->beginTransaction();
    try {
        updateLCA($aclId);
        updateLCARelation($aclId);
        updateGroups($aclId);

        $pearDB->commit();
    } catch (Throwable $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
    $submitedValues = $form->getSubmitValues();
    $fields = CentreonLogAction::prepareChanges($submitedValues);
    $centreon->CentreonLogAction->insertLog(
        'menu access',
        $aclId,
        $submitedValues['acl_topo_name'],
        'c',
        $fields
    );
}

/**
 * Create a new ACL topology with its relations and group mappings, then log the creation.
 *
 * This function inserts the main ACL record, updates its topology relations and group associations inside a database transaction, and records the creation in the Centreon log.
 *
 * @global HTML_QuickFormCustom $form Form object containing submitted values used for logging.
 * @global Centreon $centreon Centreon service used to record the creation log.
 * @global PDO $pearDB Database connection used for transactional operations.
 * @return int The new ACL identifier.
 */
function insertLCAInDB()
{
    global $form, $centreon, $pearDB;

    $pearDB->beginTransaction();
    try {
        $aclId = insertLCA();
        updateLCARelation($aclId);
        updateGroups($aclId);

        $pearDB->commit();
    } catch (Throwable $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
    $submitedValues = $form->getSubmitValues();
    $fields = CentreonLogAction::prepareChanges($submitedValues);
    $centreon->CentreonLogAction->insertLog(
        'menu access',
        $aclId,
        $submitedValues['acl_topo_name'],
        'a',
        $fields
    );

    return $aclId;
}

/**
 * Insert an ACL
 *
 * @global HTML_QuickFormCustom $form
 * @global CentreonDB $pearDB
 * @return int Id of the new ACL topology
 */
function insertLCA()
{
    global $form, $pearDB;

    $submitedValues = $form->getSubmitValues();
    $isAclActivate = false;
    if (isset($submitedValues['acl_topo_activate'], $submitedValues['acl_topo_activate']['acl_topo_activate'])
        && $submitedValues['acl_topo_activate']['acl_topo_activate'] == '1'
    ) {
        $isAclActivate = true;
    }
    $prepare = $pearDB->prepare(
        'INSERT INTO `acl_topology` '
        . '(acl_topo_name, acl_topo_alias, acl_topo_activate, acl_comments) '
        . 'VALUES (:acl_name, :acl_alias, :is_activate, :acl_comment)'
    );
    $prepare->bindValue(
        ':acl_name',
        $submitedValues['acl_topo_name'],
        PDO::PARAM_STR
    );
    $prepare->bindValue(
        ':is_activate',
        ($isAclActivate ? '1' : '0'),
        PDO::PARAM_STR
    );
    $prepare->bindValue(
        ':acl_alias',
        $submitedValues['acl_topo_alias'],
        PDO::PARAM_STR
    );
    $prepare->bindValue(
        ':acl_comment',
        $submitedValues['acl_comments'],
        PDO::PARAM_STR
    );

    return $prepare->execute()
        ? $pearDB->lastInsertId()
        : null;
}

/**
 * Update an ACL
 *
 * @global HTML_QuickFormCustom $form
 * @global \CentreonDB $pearDB
 * @param int $aclId Acl id to update
 */
function updateLCA($aclId = null)
{
    global $form, $pearDB;
    if (! $aclId) {
        return;
    }
    $submitedValues = $form->getSubmitValues();

    $isAclActivate = false;
    if (isset($submitedValues['acl_topo_activate'], $submitedValues['acl_topo_activate']['acl_topo_activate'])
        && $submitedValues['acl_topo_activate']['acl_topo_activate'] == '1'
    ) {
        $isAclActivate = true;
    }

    $prepareUpdate = $pearDB->prepare(
        'UPDATE `acl_topology` '
        . 'SET acl_topo_name = :acl_name, '
        . 'acl_topo_alias = :acl_alias, '
        . 'acl_topo_activate = :is_activate, '
        . 'acl_comments = :acl_comment '
        . 'WHERE acl_topo_id = :acl_id'
    );

    $prepareUpdate->bindValue(
        ':acl_name',
        $submitedValues['acl_topo_name'],
        PDO::PARAM_STR
    );

    $prepareUpdate->bindValue(
        ':acl_alias',
        $submitedValues['acl_topo_alias'],
        PDO::PARAM_STR
    );

    $prepareUpdate->bindValue(
        ':is_activate',
        ($isAclActivate ? '1' : '0'),
        PDO::PARAM_STR
    );

    $prepareUpdate->bindValue(
        ':acl_comment',
        $submitedValues['acl_comments'],
        PDO::PARAM_STR
    );

    $prepareUpdate->bindValue(':acl_id', $aclId, PDO::PARAM_INT);

    $prepareUpdate->execute();
}

/**
 * Update ACL topology relations for a given ACL using submitted form data.
 *
 * Deletes existing relations for the ACL and inserts relations from the form field
 * 'acl_r_topos' (entries with key 0 are skipped). If no ACL id is provided the function
 * returns without action. The function starts and commits a transaction when one is not
 * already active and rolls back on error.
 *
 * @param int|null $aclId The ACL topology identifier to update.
 * @throws \Throwable Re-throws any exception encountered during database operations.
 */
function updateLCARelation($aclId = null)
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    $ownTransaction = ! $pearDB->inTransaction();
    if ($ownTransaction) {
        $pearDB->beginTransaction();
    }
    try {
        $prepareDelete = $pearDB->prepare(
            'DELETE FROM acl_topology_relations WHERE acl_topo_id = :acl_id'
        );
        $prepareDelete->bindValue(':acl_id', $aclId, PDO::PARAM_INT);
        $prepareDelete->execute();

        $submitedValues = $form->getSubmitValue('acl_r_topos');
        if (is_array($submitedValues)) {
            $insertStmt = $pearDB->prepare(
                'INSERT INTO acl_topology_relations (acl_topo_id, topology_topology_id, access_right) '
                . 'VALUES (:aclId, :key, :value)'
            );
            foreach ($submitedValues as $key => $value) {
                if ($key != 0) {
                    $insertStmt->bindValue(':aclId', $aclId, PDO::PARAM_INT);
                    $insertStmt->bindValue(':key', $key, PDO::PARAM_INT);
                    $insertStmt->bindValue(':value', $value, PDO::PARAM_INT);
                    $insertStmt->execute();
                }
            }
        }

        if ($ownTransaction) {
            $pearDB->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
}

/**
 * Replace the group mappings for a given ACL topology with values submitted in the global form.
 *
 * Deletes existing rows in acl_group_topology_relations for the provided ACL topology id and inserts new mappings
 * from the form field `acl_groups`. Starts and commits a transaction if one is not already active; rolls back on error.
 *
 * @param int|null $aclId The ACL topology identifier whose group relations should be updated; nothing is done if null.
 * @throws Throwable If a database operation fails.
 */
function updateGroups($aclId = null)
{
    global $form, $pearDB;
    if (! $aclId) {
        return;
    }

    $ownTransaction = ! $pearDB->inTransaction();
    if ($ownTransaction) {
        $pearDB->beginTransaction();
    }
    try {
        $prepareDelete = $pearDB->prepare(
            'DELETE FROM acl_group_topology_relations WHERE acl_topology_id = :acl_id'
        );
        $prepareDelete->bindValue(':acl_id', $aclId, PDO::PARAM_INT);
        $prepareDelete->execute();

        $submitedValues = $form->getSubmitValue('acl_groups');
        if (isset($submitedValues)) {
            $insertStmt = $pearDB->prepare(
                'INSERT INTO acl_group_topology_relations (acl_topology_id, acl_group_id)
                VALUES (:aclId, :value)'
            );
            foreach ($submitedValues as $key => $value) {
                if (isset($value)) {
                    $insertStmt->bindValue(':aclId', $aclId, PDO::PARAM_INT);
                    $insertStmt->bindValue(':value', $value, PDO::PARAM_INT);
                    $insertStmt->execute();
                }
            }
        }

        if ($ownTransaction) {
            $pearDB->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
}
