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
 * Checks whether an ACL group name is available (no existing group with the same name).
 *
 * @param string|null $name The ACL group name to check.
 * @param bool $excludeCurrentFormId Whether to exclude the current form's `acl_group_id` from the check (defaults to true).
 * @return bool `true` if no matching group exists (name is available), `false` otherwise.
 */
function testGroupExistence($name = null, bool $excludeCurrentFormId = true)
{
    global $pearDB, $form;

    $id = null;

    if ($excludeCurrentFormId && isset($form)) {
        $id = $form->getSubmitValue('acl_group_id');
    }
    $query = 'SELECT 1 FROM acl_groups WHERE acl_group_name = :name';
    if ($id !== null) {
        $query .= ' AND acl_group_id <> :aclGroupId';
    }
    $statement = $pearDB->prepare($query . ' LIMIT 1');
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    if ($id !== null) {
        $statement->bindValue(':aclGroupId', (int) $id, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetchColumn() === false;
}

/**
 * Enable one or more ACL groups, mark them as changed, and record an enable action in the audit log.
 *
 * If a single `$acl_group_id` is provided it is used as the target; otherwise `$groups` supplies the group ids to enable.
 *
 * @param int|null $acl_group_id Optional ACL group id to enable.
 * @param array $groups Array of ACL group ids (keys) to enable.
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
        $centreon->CentreonLogAction->insertLog(
            'access group',
            (int) $key,
            $row !== false ? $row['acl_group_name'] : "id:{$key}",
            'enable'
        );
    }
}

/**
 * Disable one or more ACL groups in the database and record the action in the audit log.
 *
 * If a single `$acl_group_id` is provided it takes precedence and will be disabled.
 *
 * @param int|null $acl_group_id The ACL group id to disable, or null to use `$groups`.
 * @param array $groups An array of ACL group ids to disable (array keys are used as ids).
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
        $centreon->CentreonLogAction->insertLog(
            'access group',
            (int) $key,
            $row !== false ? $row['acl_group_name'] : "id:{$key}",
            'disable'
        );
    }
}

/**
 * Deletes ACL groups whose IDs are provided as the keys of the given array and logs each deletion.
 *
 * @param array $groups Array where each key is an `acl_group_id` identifying a group to delete; values are ignored.
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
        $centreon->CentreonLogAction->insertLog(
            'access group',
            (int) $key,
            $row !== false ? $row['acl_group_name'] : "id:{$key}",
            'd'
        );
    }
}

/**
 * Duplicate selected ACL groups and their related relations, creating numbered copies.
 *
 * For each group id present in $groups, creates up to the requested number of duplicates,
 * replicating the group's fields and associated contacts, contact groups, resources,
 * actions, and menus. Invalid group ids are skipped and existing names are not overwritten.
 *
 * @param array $groups Associative array whose keys are ACL group ids to duplicate.
 * @param array $nbrDup Associative array mapping ACL group ids to the desired duplication count (0–100).
 */
function multipleGroupInDB($groups = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    $selectStmt = $pearDB->prepare(
        'SELECT * FROM acl_groups WHERE acl_group_id = :aclGroupId LIMIT 1'
    );

    foreach (array_keys($groups) as $key) {
        $aclGroupId = filter_var($key, FILTER_VALIDATE_INT);
        if ($aclGroupId === false) {
            continue;
        }

        $selectStmt->bindValue(':aclGroupId', $aclGroupId, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            continue;
        }
        unset($row['acl_group_id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_groups (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $dupCount = filter_var($nbrDup[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false) {
            continue;
        }
        $originalName = $row['acl_group_name'];
        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $acl_group_name = $originalName . '_' . $suffix;

            if (! testGroupExistence($acl_group_name, false)) {
                continue;
            }
            $i++;

            $pearDB->beginTransaction();
            try {
                $row['acl_group_name'] = $acl_group_name;
                foreach ($columns as $col) {
                    $value = $row[$col];
                    $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();

                $lastInsertId = $pearDB->lastInsertId();
                if ($lastInsertId === false) {
                    $pearDB->rollBack();

                    continue;
                }
                $maxId = (int) $lastInsertId;
                if ($maxId <= 0) {
                    $pearDB->rollBack();

                    continue;
                }

                // Duplicate Links
                duplicateContacts($key, $maxId, $pearDB);
                duplicateContactGroups($key, $maxId, $pearDB);
                duplicateResources($key, $maxId, $pearDB);
                duplicateActions($key, $maxId, $pearDB);
                duplicateMenus($key, $maxId, $pearDB);

                $fields = $row;
                $centreon->CentreonLogAction->insertLog(
                    'access group',
                    $maxId,
                    $acl_group_name,
                    'a',
                    $fields
                );
                $pearDB->commit();
            } catch (Throwable $e) {
                if ($pearDB->inTransaction()) {
                    $pearDB->rollBack();
                }

                throw $e;
            }
        }
        if ($i < $dupCount) {
            error_log("Could only create {$i}/{$dupCount} duplicates for group ACL '{$originalName}' ({$key}): suffix search exhausted");
        }
    }
}

/**
 * Create a new ACL group and persist its related contacts, contact groups, actions, resources, and menus in the database.
 *
 * This function performs the insert inside a transaction: it calls insertGroup to create the ACL group, then updates related relations.
 * On error the transaction is rolled back and the exception is rethrown. After a successful commit, an audit log entry is recorded.
 *
 * @param array $ret Optional override data used when inserting the group and its relations.
 * @return int The created `acl_group_id`.
 * @throws RuntimeException If the group insert fails and no valid ID is returned.
 * @throws Throwable Re-throws any exception/error encountered while performing database operations.
 */
function insertGroupInDB($ret = [])
{
    global $form, $centreon, $pearDB;

    try {
        $pearDB->beginTransaction();

        $acl_group_id = insertGroup($ret);
        if ((int) $acl_group_id <= 0) {
            throw new RuntimeException('Failed to insert ACL group');
        }
        updateGroupContacts($acl_group_id, $ret);
        updateGroupContactGroups($acl_group_id);
        updateGroupActions($acl_group_id);
        updateGroupResources($acl_group_id);
        updateGroupMenus($acl_group_id);

        $pearDB->commit();
    } catch (Throwable $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }

    $submitValues = $form->getSubmitValues();
    $fields = CentreonLogAction::prepareChanges($submitValues);
    $centreon->CentreonLogAction->insertLog('access group', $acl_group_id, $submitValues['acl_group_name'], 'a', $fields);

    return $acl_group_id;
}

/**
 * Create a new ACL group record.
 *
 * If $groupInfos is empty, submitted form values are used. Expected keys in
 * $groupInfos: `acl_group_name`, `acl_group_alias`, and an optional
 * `acl_group_activate` array containing `acl_group_activate => '1'` to mark the
 * group as active.
 *
 * @param array $groupInfos Optional associative array of group fields.
 * @return int|null The new acl_group_id on success, `null` on failure.
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
 * Update an ACL group and its related relations in the database and record the change in the audit log.
 *
 * Performs the group update and updates contacts, contact groups, actions, resources, and menus within a single database transaction; commits on success and rolls back on error. After a successful commit, records the submitted values in the Centreon log.
 *
 * @param int|null $acl_group_id The ACL group identifier to update. If null or falsy, the function returns without action.
 * @throws Throwable If any error occurs during the database operations; the transaction is rolled back before the exception is propagated.
 */
function updateGroupInDB($acl_group_id = null)
{
    if (! $acl_group_id) {
        return;
    }
    global $form, $centreon, $pearDB;

    try {
        $pearDB->beginTransaction();

        updateGroup($acl_group_id);
        updateGroupContacts($acl_group_id);
        updateGroupContactGroups($acl_group_id);
        updateGroupActions($acl_group_id);
        updateGroupResources($acl_group_id);
        updateGroupMenus($acl_group_id);

        $pearDB->commit();
    } catch (Throwable $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }

    $submitValues = $form->getSubmitValues();
    $fields = CentreonLogAction::prepareChanges($submitValues);
    $centreon->CentreonLogAction->insertLog('access group', $acl_group_id, $submitValues['acl_group_name'], 'c', $fields);
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
 * Replace contact relations for an ACL group with the contacts provided in the request.
 *
 * @param int $acl_group_id The ACL group identifier whose contact relations will be replaced.
 * @param array $ret Optional data array retained for API compatibility.
 */
function updateGroupContacts($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    try {
        $pearDB->beginTransaction();

        $deleteStmt = $pearDB->prepare('DELETE FROM acl_group_contacts_relations WHERE acl_group_id = :group_id');
        $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        if (isset($_POST['cg_contacts'])) {
            $insertStmt = $pearDB->prepare(
                'INSERT INTO acl_group_contacts_relations (contact_contact_id, acl_group_id)'
                . ' VALUES (:contact_id, :group_id)'
            );
            foreach ($_POST['cg_contacts'] as $id) {
                $insertStmt->bindValue(':contact_id', (int) $id, PDO::PARAM_INT);
                $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
                $insertStmt->execute();
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
 * Update contact-group relations for the specified ACL group.
 *
 * Deletes existing relations for the ACL group and inserts relations from $_POST['cg_contactGroups']. Non-numeric entries are attempted to be created as LDAP contact groups via CentreonContactgroup::insertLdapGroup and replaced with the returned id; entries that cannot be resolved are skipped.
 *
 * @param int   $acl_group_id The ACL group id to update; no action is taken if falsy.
 * @param array $ret          Optional additional data (unused).
 * @throws Throwable If a database operation fails.
 */
function updateGroupContactGroups($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    try {
        $pearDB->beginTransaction();

        $deleteStmt = $pearDB->prepare(
            'DELETE FROM acl_group_contactgroups_relations WHERE acl_group_id = :group_id'
        );
        $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        if (isset($_POST['cg_contactGroups'])) {
            $cg = new CentreonContactgroup($pearDB);
            $insertStmt = $pearDB->prepare(
                'INSERT INTO acl_group_contactgroups_relations (cg_cg_id, acl_group_id)'
                . ' VALUES (:cg_id, :group_id)'
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

        $pearDB->commit();
    } catch (Throwable $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
}

/**
 * Update the action relations for a specific ACL group.
 *
 * Deletes all existing entries in acl_group_actions_relations for the given group
 * and inserts new relations from the posted `actionAccess` list when present.
 *
 * @param int $acl_group_id The ACL group identifier to update.
 * @param array $ret Optional associative array of values (contextual/legacy, not required).
 */
function updateGroupActions($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    try {
        $pearDB->beginTransaction();

        $deleteStmt = $pearDB->prepare('DELETE FROM acl_group_actions_relations WHERE acl_group_id = :group_id');
        $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        if (isset($_POST['actionAccess'])) {
            $insertStmt = $pearDB->prepare(
                'INSERT INTO acl_group_actions_relations (acl_action_id, acl_group_id)'
                . ' VALUES (:action_id, :group_id)'
            );
            foreach ($_POST['actionAccess'] as $id) {
                $insertStmt->bindValue(':action_id', (int) $id, PDO::PARAM_INT);
                $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
                $insertStmt->execute();
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
 * Replace topology (menu) relations for the specified ACL group with the provided menu access values.
 *
 * Executes a transactional update that deletes existing topology relations for the given group and,
 * if present, inserts relations for each topology id in `$_POST['menuAccess']`.
 *
 * @param int|null $acl_group_id The ACL group id to update; nothing is done if null or falsy.
 * @param array $ret Optional additional data (unused by this function).
 */
function updateGroupMenus($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    try {
        $pearDB->beginTransaction();

        $deleteStmt = $pearDB->prepare('DELETE FROM acl_group_topology_relations WHERE acl_group_id = :group_id');
        $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        if (isset($_POST['menuAccess'])) {
            $insertStmt = $pearDB->prepare(
                'INSERT INTO acl_group_topology_relations (acl_topology_id, acl_group_id)'
                . ' VALUES (:topology_id, :group_id)'
            );
            foreach ($_POST['menuAccess'] as $id) {
                $insertStmt->bindValue(':topology_id', (int) $id, PDO::PARAM_INT);
                $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
                $insertStmt->execute();
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
 * Update resource relations for an ACL group.
 *
 * Deletes unlocked resource relations for the given ACL group and inserts new relations
 * from the submitted `$_POST['resourceAccess']` list.
 *
 * @param int   $acl_group_id The ACL group identifier to update.
 * @param array $ret          Optional additional data (unused).
 * @throws Throwable If a database error occurs; transaction will be rolled back and the exception rethrown.
 */
function updateGroupResources($acl_group_id, $ret = [])
{
    global $form, $pearDB;

    if (! $acl_group_id) {
        return;
    }

    try {
        $pearDB->beginTransaction();

        $deleteStmt = $pearDB->prepare(
            'DELETE argr FROM acl_res_group_relations argr'
            . ' JOIN acl_resources ar ON argr.acl_res_id = ar.acl_res_id'
            . ' WHERE argr.acl_group_id = :group_id AND ar.locked = 0'
        );
        $deleteStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        if (isset($_POST['resourceAccess'])) {
            $insertStmt = $pearDB->prepare(
                'INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id)'
                . ' VALUES (:res_id, :group_id)'
            );
            foreach ($_POST['resourceAccess'] as $id) {
                $insertStmt->bindValue(':res_id', (int) $id, PDO::PARAM_INT);
                $insertStmt->bindValue(':group_id', (int) $acl_group_id, PDO::PARAM_INT);
                $insertStmt->execute();
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
