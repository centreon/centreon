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
 * Indicates if the topology name has already been used
 *
 * @global \CentreonDB $pearDB
 * @global HTML_QuickFormCustom $form
 * @param string $topologyName
 * @return bool Return false if the topology name has already been used
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
 * Enable an ACL
 *
 * @global CentreonDB $pearDB
 * @global Centreon $centreon
 * @param int $aclTopologyId ACL topology id to enable
 * @param array $acls Array of ACL topology id to disable
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
 * Disable an ACL
 *
 * @global CentreonDB $pearDB
 * @global Centreon $centreon
 * @param int $aclTopologyId ACL topology id to disable
 * @param array $acls Array of ACL topology id to disable
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
 * Delete a list of ACL
 *
 * @global CentreonDB $pearDB
 * @global Centreon $centreon
 * @param array $acls
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
 * Duplicate a list of ACL
 *
 * @global CentreonDB $pearDB
 * @global Centreon $centreon
 * @param array $lcas
 * @param array $nbrDup
 * @param mixed $acls
 * @param mixed $duplicateNbr
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

        $dupCount = filter_var($duplicateNbr[$currentTopologyId] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
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
 * Update an ACL
 *
 * @global HTML_QuickFormCustom $form
 * @global Centreon $centreon
 * @param int $aclId Acl topology id to update
 */
function updateLCAInDB($aclId = null)
{
    global $form, $centreon;
    if (! $aclId) {
        return;
    }
    updateLCA($aclId);
    updateLCARelation($aclId);
    updateGroups($aclId);
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
 * Insert an ACL
 *
 * @global HTML_QuickFormCustom $form
 * @global Centreon $centreon
 * @return int Id of the new ACL
 */
function insertLCAInDB()
{
    global $form, $centreon;

    $aclId = insertLCA();
    updateLCARelation($aclId);
    updateGroups($aclId);
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
 * Update all relation of ACL from the global form
 *
 * @global HTML_QuickFormCustom $form
 * @global \CentreonDB $pearDB
 * @param type $acl_id
 * @param null|mixed $aclId
 * @return type
 */
function updateLCARelation($aclId = null)
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $pearDB->beginTransaction();

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

        $pearDB->commit();
    } catch (Throwable $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
}

/**
 * Update all groups of ACL from the global form
 *
 * @global HTML_QuickFormCustom $form
 * @global \CentreonDB $pearDB
 * @param type $acl_id
 * @param null|mixed $aclId
 * @return type
 */
function updateGroups($aclId = null)
{
    global $form, $pearDB;
    if (! $aclId) {
        return;
    }

    try {
        $pearDB->beginTransaction();

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

        $pearDB->commit();
    } catch (Throwable $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
}
