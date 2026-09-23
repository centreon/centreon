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

require_once _CENTREON_PATH_ . 'www/class/centreonLDAP.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonContactgroup.class.php';

/**
 * Set the Acl group changed flag to 1
 *
 * @param $db CentreonDB
 * @param $aclGroupId int
 */
function setAclGroupChanged($db, $aclGroupId)
{
    $prepare = $db->prepare(
        "UPDATE acl_groups SET acl_group_changed = '1' WHERE acl_group_id = :id"
    );
    $prepare->bindValue(':id', $aclGroupId, PDO::PARAM_INT);
    $prepare->execute();
}

/**
 * Test if group exists
 *
 * @param null $name
 * @return bool
 */
function testGroupExistence($name = null)
{
    global $pearDB, $form;

    $id = null;

    if (isset($form)) {
        $id = $form->getSubmitValue('acl_group_id');
    }
    $statement = $pearDB->prepare(
        'SELECT acl_group_id, acl_group_name FROM acl_groups '
        . 'WHERE acl_group_name = :name'
    );
    $statement->bindValue(':name', htmlentities($name, ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->execute();
    $cg = $statement->fetch();
    if ($statement->rowCount() >= 1 && $cg['acl_group_id'] == $id) {
        return true;
    }

    return ! ($statement->rowCount() >= 1 && $cg['acl_group_id'] != $id);
    // Duplicate entry

}

/**
 * @param null $acl_group_id
 * @param array $groups
 */
function enableGroupInDB($acl_group_id = null, $groups = [])
{
    global $pearDB, $centreon;

    if (! $acl_group_id && ! count($groups)) {
        return;
    }

    if ($acl_group_id) {
        $groups = [$acl_group_id => '1'];
    }

    foreach ($groups as $key => $value) {
        $dbResult = $pearDB->prepare(
            <<<'SQL'
                UPDATE acl_groups
                SET acl_group_activate = '1',
                    acl_group_changed = '1'
                WHERE acl_group_id = :aclGroupId
                SQL
        );
        $dbResult->bindValue('aclGroupId', $key, PDO::PARAM_INT);
        $dbResult->execute();

        $dbResult = $pearDB->prepare(
            'SELECT acl_group_name FROM `acl_groups`
            WHERE acl_group_id = :aclGroupId LIMIT 1'
        );
        $dbResult->bindValue('aclGroupId', $key, PDO::PARAM_INT);
        $dbResult->execute();
        $row = $dbResult->fetch();
        $centreon->CentreonLogAction->insertLog('access group', (int) $key, $row['acl_group_name'], 'enable');
    }
}

/**
 * @param null $acl_group_id
 * @param array $groups
 */
function disableGroupInDB($acl_group_id = null, $groups = [])
{
    global $pearDB, $centreon;

    if (! $acl_group_id && ! count($groups)) {
        return;
    }
    if ($acl_group_id) {
        $groups = [$acl_group_id => '1'];
    }

    foreach ($groups as $key => $value) {
        $dbResult = $pearDB->prepare(
            "UPDATE acl_groups SET acl_group_activate = '0' WHERE acl_group_id = :aclGroupId"
        );
        $dbResult->bindValue('aclGroupId', $key, PDO::PARAM_INT);
        $dbResult->execute();
        $dbResult = $pearDB->prepare(
            'SELECT acl_group_name FROM `acl_groups` WHERE acl_group_id = :aclGroupId LIMIT 1'
        );
        $dbResult->bindValue('aclGroupId', $key, PDO::PARAM_INT);
        $dbResult->execute();
        $row = $dbResult->fetch();
        $centreon->CentreonLogAction->insertLog('access group', (int) $key, $row['acl_group_name'], 'disable');
    }
}

/**
 * Delete the selected group in DB
 * @param $groups
 */
function deleteGroupInDB($groups = [])
{
    global $pearDB, $centreon;

    foreach ($groups as $key => $value) {
        $dbResult = $pearDB->prepare(
            'SELECT acl_group_name FROM `acl_groups` WHERE acl_group_id = :aclGroupId LIMIT 1'
        );
        $dbResult->bindValue('aclGroupId', $key, PDO::PARAM_INT);
        $dbResult->execute();
        $row = $dbResult->fetch();
        $dbResult = $pearDB->prepare('DELETE FROM acl_groups WHERE acl_group_id = :aclGroupId');
        $dbResult->bindValue('aclGroupId', $key, PDO::PARAM_INT);
        $dbResult->execute();
        $centreon->CentreonLogAction->insertLog('access group', (int) $key, $row['acl_group_name'], 'd');
    }
}

/**
 * Duplicate the selected group
 * @param $groups
 * @param $nbrDup
 */
function multipleGroupInDB($groups = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    foreach ($groups as $key => $value) {
        $dbResult = $pearDB->prepare('SELECT * FROM acl_groups WHERE acl_group_id = :aclGroupId LIMIT 1');
        $dbResult->bindValue('aclGroupId', $key, PDO::PARAM_INT);
        $dbResult->execute();
        $row = $dbResult->fetch();
        unset($row['acl_group_id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_groups (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $originalName = $row['acl_group_name'];
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $acl_group_name = $originalName . '_' . $i;
            $row['acl_group_name'] = $acl_group_name;

            if (testGroupExistence($acl_group_name)) {
                foreach ($columns as $col) {
                    $insertStmt->bindValue(':' . $col, $row[$col], $row[$col] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();
                $fields = $row;
                $dbResult = $pearDB->query('SELECT MAX(acl_group_id) FROM acl_groups');
                $maxId = $dbResult->fetch();
                $dbResult->closeCursor();

                // Duplicate Links
                duplicateContacts($key, $maxId['MAX(acl_group_id)'], $pearDB);
                duplicateContactGroups($key, $maxId['MAX(acl_group_id)'], $pearDB);
                duplicateResources($key, $maxId['MAX(acl_group_id)'], $pearDB);
                duplicateActions($key, $maxId['MAX(acl_group_id)'], $pearDB);
                duplicateMenus($key, $maxId['MAX(acl_group_id)'], $pearDB);

                $centreon->CentreonLogAction->insertLog(
                    'access group',
                    $maxId['MAX(acl_group_id)'],
                    $acl_group_name,
                    'a',
                    $fields
                );
            }
        }
    }
}

/**
 * Insert group in DB
 * @param $ret
 */
function insertGroupInDB($ret = [])
{
    global $form, $centreon;

    $acl_group_id = insertGroup($ret);
    updateGroupContacts($acl_group_id, $ret);
    updateGroupContactGroups($acl_group_id);
    updateGroupActions($acl_group_id);
    updateGroupResources($acl_group_id);
    updateGroupMenus($acl_group_id);

    $ret = $form->getSubmitValues();
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog('access group', $acl_group_id, $ret['acl_group_name'], 'a', $fields);

    return $acl_group_id;
}

/**
 * Insert a new access group
 *
 * @param $groupInfos Array containing group's informations
 * @global $form    HTML_QuickFormCustom
 * @global $pearDB  CentreonDB
 * @return int Return id of the new access group
 */
function insertGroup($groupInfos)
{
    global $form, $pearDB;

    if (! count($groupInfos)) {
        $groupInfos = $form->getSubmitValues();
    }

    $isAclGroupActivate = false;
    if (isset($groupInfos['acl_group_activate'], $groupInfos['acl_group_activate']['acl_group_activate'])
        && $groupInfos['acl_group_activate']['acl_group_activate'] == '1'
    ) {
        $isAclGroupActivate = true;
    }

    $request = 'INSERT INTO acl_groups '
            . '(acl_group_name, acl_group_alias, acl_group_activate) '
            . 'VALUES (:group_name, :group_alias, :is_activate)';

    $prepare = $pearDB->prepare($request);
    $prepare->bindValue(
        ':group_name',
        $groupInfos['acl_group_name'],
        PDO::PARAM_STR
    );
    $prepare->bindValue(
        ':group_alias',
        $groupInfos['acl_group_alias'],
        PDO::PARAM_STR
    );
    $prepare->bindValue(
        ':is_activate',
        ($isAclGroupActivate ? '1' : '0'),
        PDO::PARAM_STR
    );

    return $prepare->execute()
        ? $pearDB->lastInsertId()
        : null;
}

/**
 * Update Group in DB
 * @param $acl_group_id
 */
function updateGroupInDB($acl_group_id = null)
{
    if (! $acl_group_id) {
        return;
    }
    global $form, $centreon;

    updateGroup($acl_group_id);
    updateGroupContacts($acl_group_id);
    updateGroupContactGroups($acl_group_id);
    updateGroupActions($acl_group_id);
    updateGroupResources($acl_group_id);
    updateGroupMenus($acl_group_id);

    $ret = $form->getSubmitValues();
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog('access group', $acl_group_id, $ret['acl_group_name'], 'c', $fields);
}

/**
 * Update the selected group
 *
 * @param int $acl_group_id
 * @param null|mixed $aclGroupId
 * @global $form HTML_QuickFormCustom
 * @global $pearDB CentreonDB
 */
function updateGroup($aclGroupId = null)
{
    global $form, $pearDB;

    if (is_null($aclGroupId)) {
        return;
    }

    $groupInfos = $form->getSubmitValues();

    $isAclGroupActivate = false;
    if (isset($groupInfos['acl_group_activate'], $groupInfos['acl_group_activate']['acl_group_activate'])
        && $groupInfos['acl_group_activate']['acl_group_activate'] == '1'
    ) {
        $isAclGroupActivate = true;
    }

    $request = 'UPDATE acl_groups '
        . 'SET acl_group_name = :acl_group_name, '
        . 'acl_group_alias = :acl_group_alias, '
        . 'acl_group_activate = :is_activate '
        . 'WHERE acl_group_id = :acl_group_id';

    $prepare = $pearDB->prepare($request);
    $prepare->bindValue(
        ':acl_group_name',
        $groupInfos['acl_group_name'],
        PDO::PARAM_STR
    );
    $prepare->bindValue(
        ':acl_group_alias',
        $groupInfos['acl_group_alias'],
        PDO::PARAM_STR
    );
    $prepare->bindValue(
        ':is_activate',
        ($isAclGroupActivate ? '1' : '0'),
        PDO::PARAM_STR
    );
    $prepare->bindValue(
        ':acl_group_id',
        $aclGroupId,
        PDO::PARAM_INT
    );

    $prepare->execute();

    setAclGroupChanged($pearDB, $aclGroupId);
}

/**
 * Update Contacts lists
 * @param $acl_group_id
 * @param $ret
 */
function updateGroupContacts($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    $deleteStmt = $pearDB->prepare('DELETE FROM acl_group_contacts_relations WHERE acl_group_id = :group_id');
    $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
    $deleteStmt->execute();
    if (isset($_POST['cg_contacts'])) {
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_group_contacts_relations (contact_contact_id, acl_group_id) '
            . 'VALUES (:contact_id, :group_id)'
        );
        foreach ($_POST['cg_contacts'] as $id) {
            $insertStmt->bindValue(':contact_id', (int) $id, PDO::PARAM_INT);
            $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
            $insertStmt->execute();
        }
    }
}

/**
 * Update contact group list
 * @param $acl_group_id
 * @param $ret
 */
function updateGroupContactGroups($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    $deleteStmt = $pearDB->prepare('DELETE FROM acl_group_contactgroups_relations WHERE acl_group_id = :group_id');
    $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
    $deleteStmt->execute();
    if (isset($_POST['cg_contactGroups'])) {
        $cg = new CentreonContactgroup($pearDB);
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_group_contactgroups_relations (cg_cg_id, acl_group_id) '
            . 'VALUES (:cg_id, :group_id)'
        );
        foreach ($_POST['cg_contactGroups'] as $id) {
            if (! is_numeric($id)) {
                $res = $cg->insertLdapGroup($id);
                if ($res != 0) {
                    $id = $res;
                } else {
                    continue;
                }
            }
            $insertStmt->bindValue(':cg_id', (int) $id, PDO::PARAM_INT);
            $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
            $insertStmt->execute();
        }
    }
}

/**
 * Update Group actions
 * @param $acl_group_id
 * @param $ret
 */
function updateGroupActions($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    $deleteStmt = $pearDB->prepare('DELETE FROM acl_group_actions_relations WHERE acl_group_id = :group_id');
    $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
    $deleteStmt->execute();
    if (isset($_POST['actionAccess'])) {
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_group_actions_relations (acl_action_id, acl_group_id) '
            . 'VALUES (:action_id, :group_id)'
        );
        foreach ($_POST['actionAccess'] as $id) {
            $insertStmt->bindValue(':action_id', (int) $id, PDO::PARAM_INT);
            $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
            $insertStmt->execute();
        }
    }
}

/**
 * Update Menu Access
 * @param $acl_group_id
 * @param $ret
 */
function updateGroupMenus($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    $deleteStmt = $pearDB->prepare('DELETE FROM acl_group_topology_relations WHERE acl_group_id = :group_id');
    $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
    $deleteStmt->execute();
    if (isset($_POST['menuAccess'])) {
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_group_topology_relations (acl_topology_id, acl_group_id) '
            . 'VALUES (:topology_id, :group_id)'
        );
        foreach ($_POST['menuAccess'] as $id) {
            $insertStmt->bindValue(':topology_id', (int) $id, PDO::PARAM_INT);
            $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
            $insertStmt->execute();
        }
    }
}

/**
 * Update Group ressources
 * @param $acl_group_id
 * @param $ret
 */
function updateGroupResources($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    $deleteStmt = $pearDB->prepare(
        'DELETE argr FROM acl_res_group_relations argr '
        . 'JOIN acl_resources ar ON argr.acl_res_id = ar.acl_res_id '
        . 'WHERE argr.acl_group_id = :group_id '
        . 'AND ar.locked = 0'
    );
    $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
    $deleteStmt->execute();
    if (isset($_POST['resourceAccess'])) {
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id) '
            . 'VALUES (:res_id, :group_id)'
        );
        foreach ($_POST['resourceAccess'] as $id) {
            $insertStmt->bindValue(':res_id', (int) $id, PDO::PARAM_INT);
            $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
            $insertStmt->execute();
        }
    }
}

/**
 * Duplicate Contacts lists
 * @param $acl_group_id
 * @param $ret
 * @param mixed $idTD
 * @param mixed $acl_id
 * @param mixed $pearDB
 */
function duplicateContacts($idTD, $acl_id, $pearDB)
{
    $request = 'INSERT INTO acl_group_contacts_relations (contact_contact_id, acl_group_id) '
        . 'SELECT contact_contact_id, :acl_group_id AS acl_group_id '
        . 'FROM acl_group_contacts_relations '
        . 'WHERE acl_group_id = :acl_group_id_td';
    $statement = $pearDB->prepare($request);
    $statement->bindValue(':acl_group_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_group_id_td', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();
}

/**
 * Duplicate Contactgroups lists
 * @param $acl_group_id
 * @param $ret
 * @param mixed $idTD
 * @param mixed $acl_id
 * @param mixed $pearDB
 */
function duplicateContactGroups($idTD, $acl_id, $pearDB)
{
    $request = 'INSERT INTO acl_group_contactgroups_relations (cg_cg_id, acl_group_id) '
        . 'SELECT cg_cg_id, :acl_group_id AS acl_group_id '
        . 'FROM acl_group_contactgroups_relations '
        . 'WHERE acl_group_id = :acl_group_id_td';
    $statement = $pearDB->prepare($request);
    $statement->bindValue(':acl_group_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_group_id_td', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();
}

/**
 * Duplicate Resources lists
 * @param $acl_group_id
 * @param $ret
 * @param mixed $idTD
 * @param mixed $acl_id
 * @param mixed $pearDB
 */
function duplicateResources($idTD, $acl_id, $pearDB)
{
    $request = 'INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id) '
        . 'SELECT acl_res_id, :acl_group_id AS acl_group_id '
        . 'FROM acl_res_group_relations '
        . 'WHERE acl_group_id = :acl_group_id_td';
    $statement = $pearDB->prepare($request);
    $statement->bindValue(':acl_group_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_group_id_td', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();
}

/**
 * Duplicate Actions lists
 * @param $acl_group_id
 * @param $ret
 * @param mixed $idTD
 * @param mixed $acl_id
 * @param mixed $pearDB
 */
function duplicateActions($idTD, $acl_id, $pearDB)
{
    $request = 'INSERT INTO acl_group_actions_relations (acl_action_id, acl_group_id) '
        . 'SELECT acl_action_id, :acl_group_id AS acl_group_id '
        . 'FROM acl_group_actions_relations '
        . 'WHERE acl_group_id = :acl_group_id_td';
    $statement = $pearDB->prepare($request);
    $statement->bindValue(':acl_group_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_group_id_td', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();
}

/**
 * Duplicate Menu lists
 * @param $acl_group_id
 * @param $ret
 * @param mixed $idTD
 * @param mixed $acl_id
 * @param mixed $pearDB
 */
function duplicateMenus($idTD, $acl_id, $pearDB)
{
    $request = 'INSERT INTO acl_group_topology_relations (acl_topology_id, acl_group_id) '
        . 'SELECT acl_topology_id, :acl_group_id AS acl_group_id '
        . 'FROM acl_group_topology_relations '
        . 'WHERE acl_group_id = :acl_group_id_td';
    $statement = $pearDB->prepare($request);
    $statement->bindValue(':acl_group_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_group_id_td', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();
}

/**
 * Rule for test if a ldap contactgroup name already exists
 *
 * @param array $listCgs The list of contactgroups to validate
 * @param mixed $list
 * @return bool
 */
function testCg($list)
{
    return CentreonContactgroup::verifiedExists($list);
}
