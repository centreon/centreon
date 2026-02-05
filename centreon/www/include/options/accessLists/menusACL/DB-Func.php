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
function hasTopologyNameNeverUsed($topologyName = null)
{
    global $pearDB, $form;

    $topologyId = null;
    if (isset($form)) {
        $topologyId = $form->getSubmitValue('lca_id');
    }
    $prepareSelect = $pearDB->prepare(
        'SELECT acl_topo_name, acl_topo_id FROM `acl_topology` '
        . 'WHERE acl_topo_name = :topology_name'
    );
    $prepareSelect->bindValue(
        ':topology_name',
        $topologyName,
        PDO::PARAM_STR
    );
    if ($prepareSelect->execute()) {
        $result = $prepareSelect->fetch(PDO::FETCH_ASSOC);
        $total = $prepareSelect->rowCount();
        if ($total >= 1 && $result['acl_topo_id'] == $topologyId) {
            /**
             * In case of modification, we need to return true
             */
            return true;
        }

        return ! ($total >= 1 && $result['acl_topo_id'] != $topologyId);
    }
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
        $prepareUpdate = $pearDB->prepare(
            "UPDATE `acl_topology` SET acl_topo_activate = '1' "
            . 'WHERE `acl_topo_id` = :topology_id'
        );
        $prepareUpdate->bindValue(
            ':topology_id',
            $currentAclTopologyId,
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

        if ($prepareSelect->execute()) {
            $result = $prepareSelect->fetch(PDO::FETCH_ASSOC);
            $centreon->CentreonLogAction->insertLog(
                'menu access',
                $currentAclTopologyId,
                $result['acl_topo_name'],
                'enable'
            );
        }
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
        $prepareUpdate = $pearDB->prepare(
            "UPDATE `acl_topology` SET acl_topo_activate = '0' "
            . 'WHERE `acl_topo_id` = :topology_id'
        );
        $prepareUpdate->bindValue(
            ':topology_id',
            $currentTopologyId,
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

        if ($prepareSelect->execute()) {
            $result = $prepareSelect->fetch(PDO::FETCH_ASSOC);
            $centreon->CentreonLogAction->insertLog(
                'menu access',
                $currentTopologyId,
                $result['acl_topo_name'],
                'disable'
            );
        }
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
        $prepareSelect = $pearDB->prepare(
            'SELECT acl_topo_name FROM `acl_topology` '
            . 'WHERE acl_topo_id = :topology_id LIMIT 1'
        );
        $prepareSelect->bindValue(
            ':topology_id',
            $currentTopologyId,
            PDO::PARAM_INT
        );

        if (! $prepareSelect->execute()) {
            continue;
        }

        $result = $prepareSelect->fetch(PDO::FETCH_ASSOC);
        $topologyName = $result['acl_topo_name'];

        $prepareDelete = $pearDB->prepare(
            'DELETE FROM `acl_topology` WHERE acl_topo_id = :topology_id'
        );
        $prepareDelete->bindValue(
            ':topology_id',
            $currentTopologyId,
            PDO::PARAM_INT
        );
        if ($prepareDelete->execute()) {
            $centreon->CentreonLogAction->insertLog(
                'menu access',
                $currentTopologyId,
                $topologyName,
                'd'
            );
        }
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

    foreach (array_keys($acls) as $currentTopologyId) {
        $prepareSelect = $pearDB->prepare(
            'SELECT * FROM `acl_topology` WHERE acl_topo_id = :topology_id LIMIT 1'
        );
        $prepareSelect->bindValue(
            ':topology_id',
            $currentTopologyId,
            PDO::PARAM_INT
        );

        if (! $prepareSelect->execute()) {
            continue;
        }

        $topology = $prepareSelect->fetch(PDO::FETCH_ASSOC);

        $topology['acl_topo_id'] = '';
        for ($newIndex = 1; $newIndex <= $duplicateNbr[$currentTopologyId]; $newIndex++) {
            $val = null;
            $aclName = null;
            $fields = [];
            foreach ($topology as $column => $value) {
                if ($column === 'acl_topo_name') {
                    $count = 1;
                    $aclName = $value . '_' . $count;
                    while (! hasTopologyNameNeverUsed($aclName)) {
                        $count++;
                        $aclName = $value . '_' . $count;
                    }
                    $value = $aclName;
                    $fields['acl_topo_name'] = $aclName;
                }
                if (is_null($val)) {
                    $val .= (is_null($value) || empty($value))
                        ? 'NULL'
                        : "'" . $pearDB->escape($value) . "'";
                } else {
                    $val .= (is_null($value) || empty($value))
                        ? ', NULL'
                        : ", '" . $pearDB->escape($value) . "'";
                }

                if ($column !== 'acl_topo_id' && $column !== 'acl_topo_name') {
                    $fields[$column] = $value;
                }
            }

            if (! is_null($val)) {
                $pearDB->query(
                    "INSERT INTO acl_topology VALUES ({$val})"
                );
                $newTopologyId = $pearDB->lastInsertId();

                $prepareInsertRelation = $pearDB->prepare(
                    'INSERT INTO acl_topology_relations '
                    . '(acl_topo_id, topology_topology_id, access_right) '
                    . '(SELECT :new_topology_id, topology_topology_id, access_right '
                    . 'FROM acl_topology_relations '
                    . 'WHERE acl_topo_id = :current_topology_id)'
                );
                $prepareInsertRelation->bindValue(
                    ':new_topology_id',
                    $newTopologyId,
                    PDO::PARAM_INT
                );
                $prepareInsertRelation->bindValue(
                    ':current_topology_id',
                    $currentTopologyId,
                    PDO::PARAM_INT
                );

                if (! $prepareInsertRelation->execute()) {
                    continue;
                }

                $prepareInsertGroup = $pearDB->prepare(
                    'INSERT INTO acl_group_topology_relations '
                    . '(acl_topology_id, acl_group_id) '
                    . '(SELECT :new_topology_id, acl_group_id '
                    . 'FROM acl_group_topology_relations '
                    . 'WHERE acl_topology_id = :current_topology_id)'
                );
                $prepareInsertGroup->bindValue(
                    ':new_topology_id',
                    $newTopologyId,
                    PDO::PARAM_INT
                );
                $prepareInsertGroup->bindValue(
                    ':current_topology_id',
                    $currentTopologyId,
                    PDO::PARAM_INT
                );

                if ($prepareInsertGroup->execute()) {
                    $centreon->CentreonLogAction->insertLog(
                        'menu access',
                        $newTopologyId,
                        $aclName,
                        'a',
                        $fields
                    );
                }
            }
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

    $prepareDelete = $pearDB->prepare(
        'DELETE FROM acl_topology_relations WHERE acl_topo_id = :acl_id'
    );
    $prepareDelete->bindValue(':acl_id', $aclId, PDO::PARAM_INT);

    if ($prepareDelete->execute()) {
        $submitedValues = $form->getSubmitValue('acl_r_topos');
        foreach ($submitedValues as $key => $value) {
            if (isset($submitedValues) && $key != 0) {
                $prepare = $pearDB->prepare(
                    'INSERT INTO acl_topology_relations (acl_topo_id, topology_topology_id, access_right) '
                    . 'VALUES (:aclId, :key, :value)'
                );
                $prepare->bindValue(':aclId', $aclId, PDO::PARAM_INT);
                $prepare->bindValue(':key', $key, PDO::PARAM_INT);
                $prepare->bindValue(':value', $value, PDO::PARAM_INT);

                $prepare->execute();
            }
        }
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

    $prepareDelete = $pearDB->prepare(
        'DELETE FROM acl_group_topology_relations WHERE acl_topology_id = :acl_id'
    );

    $prepareDelete->bindValue(':acl_id', $aclId, PDO::PARAM_INT);

    if ($prepareDelete->execute()) {
        $submitedValues = $form->getSubmitValue('acl_groups');
        if (isset($submitedValues)) {
            foreach ($submitedValues as $key => $value) {
                if (isset($value)) {
                    $query = <<<'SQL'
                        INSERT INTO acl_group_topology_relations
                        (acl_topology_id, acl_group_id)
                        VALUES (:aclId, :value)
                        SQL;
                    $statement = $pearDB->prepare($query);
                    $statement->bindValue(':aclId', $aclId, PDO::PARAM_INT);
                    $statement->bindValue(':value', $value, PDO::PARAM_INT);
                    $statement->execute();
                }
            }
        }
    }
}
