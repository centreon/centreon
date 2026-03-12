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
 * Check whether a contact group name is available (not already used by another contact group).
 *
 * @param string|null $name The contact group name to check; if null it is treated as an empty string.
 * @param bool $excludeCurrentFormId When true and a form is present, exclude the current form's `cg_id` from the existence check.
 * @return bool `true` if no contact group exists with the given name (name available), `false` otherwise.
 */
function testContactGroupExistence($name = null, bool $excludeCurrentFormId = true)
{
    global $pearDB, $form, $centreon;

    $name = $centreon->checkIllegalChar(HtmlAnalyzer::sanitizeAndRemoveTags($name ?? ''));
    $id = null;

    if ($excludeCurrentFormId && isset($form)) {
        $id = $form->getSubmitValue('cg_id');
    }
    $query = 'SELECT 1 FROM `contactgroup` WHERE `cg_name` = :cgName';
    if ($id !== null) {
        $query .= ' AND cg_id <> :cgId';
    }
    $stmt = $pearDB->prepare($query . ' LIMIT 1');
    $stmt->bindValue(':cgName', $name, PDO::PARAM_STR);
    if ($id !== null) {
        $stmt->bindValue(':cgId', (int) $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchColumn() === false;
}

/**
 * Enable the specified contact group and record the action in the audit log.
 *
 * If no valid `$cg_id` is provided, the function returns without performing any action.
 *
 * @param int|null $cg_id The contact group identifier to enable.
 */
function enableContactGroupInDB($cg_id = null)
{
    global $pearDB, $centreon;

    if (! $cg_id) {
        return;
    }
    $stmt = $pearDB->prepare('UPDATE `contactgroup` SET `cg_activate` = \'1\' WHERE `cg_id` = :cgId');
    $stmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt2 = $pearDB->prepare('SELECT cg_name FROM `contactgroup` WHERE `cg_id` = :cgId LIMIT 1');
    $stmt2->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $stmt2->execute();
    $row = $stmt2->fetch();
    $cgName = is_array($row) ? $row['cg_name'] : "id:{$cg_id}";

    $centreon->CentreonLogAction->insertLog('contactgroup', $cg_id, $cgName, 'enable');
}

/**
 * Disable a contact group and record the action in the audit log.
 *
 * Sets the contact group's activate flag to 0 and inserts a log entry with type 'disable'.
 * If the contact group's name cannot be retrieved, the log uses "id:<cg_id>" as the name.
 *
 * @param int|null $cg_id The contact group ID to disable; if null or falsy, the function does nothing.
 */
function disableContactGroupInDB($cg_id = null)
{
    global $pearDB, $centreon;

    if (! $cg_id) {
        return;
    }
    $stmt = $pearDB->prepare('UPDATE `contactgroup` SET `cg_activate` = \'0\' WHERE `cg_id` = :cgId');
    $stmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt2 = $pearDB->prepare('SELECT cg_name FROM `contactgroup` WHERE `cg_id` = :cgId LIMIT 1');
    $stmt2->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $stmt2->execute();
    $row = $stmt2->fetch();
    $cgName = is_array($row) ? $row['cg_name'] : "id:{$cg_id}";

    $centreon->CentreonLogAction->insertLog('contactgroup', $cg_id, $cgName, 'disable');
}

/**
 * Delete contact groups identified by the keys of the provided array and record a deletion log for each.
 *
 * For each array key treated as an integer contact group ID, the function removes the corresponding
 * contactgroup row (if any) and inserts a log entry using the group's name when available or "id:<key>"
 * as a fallback. Non-integer keys are ignored.
 *
 * @param array $contactGroups Array whose keys are contact group IDs to delete; values are ignored.
 */
function deleteContactGroupInDB($contactGroups = [])
{
    global $pearDB, $centreon;

    $selectStmt = $pearDB->prepare('SELECT cg_name FROM `contactgroup` WHERE `cg_id` = :cgId LIMIT 1');
    $deleteStmt = $pearDB->prepare('DELETE FROM `contactgroup` WHERE `cg_id` = :cgId');

    foreach (array_keys($contactGroups) as $key) {
        $cgId = filter_var($key, FILTER_VALIDATE_INT);
        if ($cgId === false) {
            continue;
        }

        $selectStmt->bindValue(':cgId', $cgId, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch();
        $cgName = is_array($row) ? $row['cg_name'] : "id:{$key}";

        $deleteStmt->bindValue(':cgId', $cgId, PDO::PARAM_INT);
        $deleteStmt->execute();
        $centreon->CentreonLogAction->insertLog('contactgroup', $key, $cgName, 'd');
    }
}

/**
 * Duplicate specified contact groups, creating new groups with unique suffixed names while preserving ACL and contact relations and logging each creation.
 *
 * @param array $contactGroups Associative array whose keys are contact group IDs (integers) to duplicate; values are ignored.
 * @param array $nbrDup Associative array mapping the same keys to the number of duplicates to create for each group (integer, treated as 0..100).
 * @throws Throwable If a database error or transaction failure occurs during duplication.
 */
function multipleContactGroupInDB($contactGroups = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    $selectStmt = $pearDB->prepare(
        'SELECT * FROM `contactgroup` WHERE `cg_id` = :cgId LIMIT 1'
    );
    $selectAclStmt = $pearDB->prepare(
        'SELECT DISTINCT `acl_group_id` FROM `acl_group_contactgroups_relations` WHERE `cg_cg_id` = :cgId'
    );
    $insertAclStmt = $pearDB->prepare(
        'INSERT INTO `acl_group_contactgroups_relations` (`cg_cg_id`, `acl_group_id`) VALUES (:newCgId, :aclGroupId)'
    );
    $selectContactsStmt = $pearDB->prepare(
        'SELECT DISTINCT `contact_contact_id` FROM `contactgroup_contact_relation`
        WHERE `contactgroup_cg_id` = :cgId'
    );
    $insertContactStmt = $pearDB->prepare(
        'INSERT INTO `contactgroup_contact_relation` (`contact_contact_id`, `contactgroup_cg_id`) VALUES (:contactId, :newCgId)'
    );

    foreach (array_keys($contactGroups) as $key) {
        $cgId = filter_var($key, FILTER_VALIDATE_INT);
        if ($cgId === false) {
            continue;
        }

        $selectStmt->bindValue(':cgId', $cgId, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            continue;
        }
        unset($row['cg_id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO `contactgroup` (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        // Fetch relationships once before duplication loop
        $selectAclStmt->bindValue(':cgId', $cgId, PDO::PARAM_INT);
        $selectAclStmt->execute();
        $aclRelations = $selectAclStmt->fetchAll(PDO::FETCH_ASSOC);

        $selectContactsStmt->bindValue(':cgId', $cgId, PDO::PARAM_INT);
        $selectContactsStmt->execute();
        $contactRelations = $selectContactsStmt->fetchAll(PDO::FETCH_ASSOC);

        $dupCount = filter_var($nbrDup[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false) {
            continue;
        }
        $originalName = $row['cg_name'];
        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $cg_name = $originalName . '_' . $suffix;

            if (! testContactGroupExistence($cg_name, false)) {
                continue;
            }
            $i++;

            $pearDB->beginTransaction();
            try {
                $row['cg_name'] = $cg_name;
                foreach ($columns as $col) {
                    $value = $row[$col];
                    $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();

                $newCgId = (int) $pearDB->lastInsertId();
                if ($newCgId <= 0) {
                    $pearDB->rollBack();

                    continue;
                }

                $fields = [];
                foreach ($row as $key2 => $value2) {
                    if ($key2 !== 'cg_id') {
                        $fields[$key2] = $value2;
                    }
                }
                $fields['cg_name'] = $cg_name;

                $fields['cg_aclRelation'] = '';
                foreach ($aclRelations as $cgAcl) {
                    $insertAclStmt->bindValue(':newCgId', $newCgId, PDO::PARAM_INT);
                    $insertAclStmt->bindValue(':aclGroupId', (int) $cgAcl['acl_group_id'], PDO::PARAM_INT);
                    $insertAclStmt->execute();
                    $fields['cg_aclRelation'] .= $cgAcl['acl_group_id'] . ',';
                }
                $fields['cg_aclRelation'] = trim($fields['cg_aclRelation'], ',');

                $fields['cg_contacts'] = '';
                foreach ($contactRelations as $cct) {
                    $insertContactStmt->bindValue(':contactId', (int) $cct['contact_contact_id'], PDO::PARAM_INT);
                    $insertContactStmt->bindValue(':newCgId', $newCgId, PDO::PARAM_INT);
                    $insertContactStmt->execute();
                    $fields['cg_contacts'] .= $cct['contact_contact_id'] . ',';
                }
                $fields['cg_contacts'] = trim($fields['cg_contacts'], ',');

                $pearDB->commit();

                $centreon->CentreonLogAction->insertLog(
                    'contactgroup',
                    $newCgId,
                    $cg_name,
                    'a',
                    $fields
                );
            } catch (Throwable $e) {
                if ($pearDB->inTransaction()) {
                    $pearDB->rollBack();
                }

                throw $e;
            }
        }
        if ($i < $dupCount) {
            error_log("Could only create {$i}/{$dupCount} duplicates for contact group '{$originalName}' ({$key}): suffix search exhausted");
        }
    }
}

/**
 * Create a new contact group and, on success, update its contact and ACL group relations.
 *
 * @param array $ret Optional associative array of contact group fields and related relations used for insertion and subsequent updates.
 * @return int The newly created contact group ID, or 0 if creation failed.
 */
function insertContactGroupInDB($ret = [])
{
    $cg_id = insertContactGroup($ret);
    if ($cg_id > 0) {
        updateContactGroupContacts($cg_id, $ret);
        updateContactGroupAclGroups($cg_id, $ret);
    }

    return $cg_id;
}

/**
 * Create a new contact group record from provided form data and log its creation.
 *
 * @param array $ret Optional associative array of contact group values. Expected keys: `cg_name`, `cg_alias`, `cg_comment`, and `cg_activate` (array with key `cg_activate` set to '0' or other). When empty, values are taken from the global form submit values.
 * @return int The newly inserted contact group ID, or `0` if the insertion failed.
 */
function insertContactGroup($ret)
{
    global $form, $pearDB, $centreon;

    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }

    $cgName = $centreon->checkIllegalChar(
        HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_name'])
    );
    $cgAlias = HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_alias']);
    $cgComment = HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_comment']);
    $cgActivate = $ret['cg_activate']['cg_activate'] === '0' ? '0' : '1'; // enum

    $stmt = $pearDB->prepare(
        'INSERT INTO `contactgroup` (`cg_name`, `cg_alias`, `cg_comment`, `cg_activate`)
        VALUES (:cgName, :cgAlias, :cgComment, :cgActivate)'
    );

    $stmt->bindValue(':cgName', $cgName, PDO::PARAM_STR);
    $stmt->bindValue(':cgAlias', $cgAlias, PDO::PARAM_STR);
    $stmt->bindValue(':cgComment', $cgComment, PDO::PARAM_STR);
    $stmt->bindValue(':cgActivate', $cgActivate, PDO::PARAM_STR);
    $stmt->execute();

    $cgId = (int) $pearDB->lastInsertId();
    if ($cgId <= 0) {
        return 0;
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        'contactgroup',
        $cgId,
        $cgName,
        'a',
        $fields
    );

    return $cgId;
}

/**
 * Update a contact group's main fields and its related contacts and ACL group relations in the database.
 *
 * If `$cg_id` is falsy the function returns without performing any operation.
 *
 * @param int|null $cg_id The ID of the contact group to update.
 * @param array $params Optional associative array of values to apply; when empty the current form submit values are used.
 */
function updateContactGroupInDB($cg_id = null, $params = [])
{
    if (! $cg_id) {
        return;
    }

    updateContactGroup($cg_id, $params);
    updateContactGroupContacts($cg_id, $params);
    updateContactGroupAclGroups($cg_id, $params);
}

/**
 * Update the main fields of an existing contact group and record the change in the changelog.
 *
 * @param int|null $cgId The ID of the contact group to update; if null the function exits without action.
 * @param array $params Optional associative array of submitted values (e.g., `cg_name`, `cg_alias`, `cg_comment`, `cg_activate`); when empty, values are read from the global form.
 */
function updateContactGroup($cgId = null, $params = [])
{
    global $form, $pearDB, $centreon;
    if (! $cgId) {
        return;
    }
    $ret = count($params) ? $params : $form->getSubmitValues();

    $cgName = $centreon->checkIllegalChar(
        HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_name'])
    );
    $cgAlias = HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_alias']);
    $cgComment = HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_comment']);
    $cgActivate = $ret['cg_activate']['cg_activate'] === '0' ? '0' : '1'; // enum

    $stmt = $pearDB->prepare(
        'UPDATE `contactgroup` SET `cg_name` = :cgName, `cg_alias` = :cgAlias, `cg_comment` = :cgComment, '
        . '`cg_activate` = :cgActivate WHERE `cg_id` = :cgId'
    );

    $stmt->bindValue(':cgName', $cgName, PDO::PARAM_STR);
    $stmt->bindValue(':cgAlias', $cgAlias, PDO::PARAM_STR);
    $stmt->bindValue(':cgComment', $cgComment, PDO::PARAM_STR);
    $stmt->bindValue(':cgActivate', $cgActivate, PDO::PARAM_STR);
    $stmt->bindValue(':cgId', (int) $cgId, PDO::PARAM_INT);
    $stmt->execute();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog('contactgroup', $cgId, $cgName, 'c', $fields);
}

/**
 * Replace the contacts associated with a contact group and synchronize related custom views.
 *
 * Deletes all existing contact relations for the given contact group ID and inserts the provided
 * contacts as the new relations; for each added contact it triggers a contact-group custom view sync.
 *
 * @param int $cg_id The contact group ID to update.
 * @param array<int>|array<string,mixed> $ret Optional list/array of contact IDs to set for the group.
 *     If omitted or empty, contact IDs are taken from the form's `cg_contacts` values.
 *
 * @throws Exception If a database operation fails; the transaction is rolled back before the exception is rethrown.
 */
function updateContactGroupContacts($cg_id, $ret = [])
{
    global $centreon, $form, $pearDB;
    if (! $cg_id) {
        return;
    }

    try {
        $pearDB->beginTransaction();

        $deleteStmt = $pearDB->prepare(
            'DELETE FROM `contactgroup_contact_relation` WHERE `contactgroup_cg_id` = :cgId'
        );
        $deleteStmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
        $deleteStmt->execute();

        $ret = $ret['cg_contacts'] ?? CentreonUtils::mergeWithInitialValues($form, 'cg_contacts');
        $counter = count($ret);

        $insertStmt = $pearDB->prepare(
            'INSERT INTO `contactgroup_contact_relation` (`contact_contact_id`, `contactgroup_cg_id`)
            VALUES (:contactId, :cgId)'
        );
        for ($i = 0; $i < $counter; $i++) {
            $insertStmt->bindValue(':contactId', (int) $ret[$i], PDO::PARAM_INT);
            $insertStmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
            $insertStmt->execute();

            CentreonCustomView::syncContactGroupCustomView($centreon, $pearDB, $ret[$i]);
        }

        $pearDB->commit();
    } catch (Exception $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
}

/**
 * Replace ACL group relations for a contact group with the provided list.
 *
 * Deletes existing ACL relations for the given contact group ID and inserts the supplied ACL group IDs;
 * if `$ret` is not provided, values are taken from the form's `cg_acl_groups`.
 *
 * @param int $cg_id The contact group ID to update.
 * @param array $ret An indexed array of ACL group IDs to associate with the contact group (optional).
 * @throws Exception If a database error occurs; the transaction will be rolled back before the exception is re-thrown.
 */
function updateContactGroupAclGroups($cg_id, $ret = [])
{
    global $form, $pearDB;

    if (! $cg_id) {
        return;
    }

    try {
        $pearDB->beginTransaction();

        $deleteStmt = $pearDB->prepare(
            'DELETE FROM `acl_group_contactgroups_relations` WHERE `cg_cg_id` = :cgId'
        );
        $deleteStmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
        $deleteStmt->execute();

        $ret = $ret['cg_acl_groups'] ?? CentreonUtils::mergeWithInitialValues($form, 'cg_acl_groups');
        $counter = count($ret);

        $insertStmt = $pearDB->prepare(
            'INSERT INTO `acl_group_contactgroups_relations` (`acl_group_id`, `cg_cg_id`) VALUES (:aclGroupId, :cgId)'
        );
        for ($i = 0; $i < $counter; $i++) {
            $insertStmt->bindValue(':aclGroupId', (int) $ret[$i], PDO::PARAM_INT);
            $insertStmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
            $insertStmt->execute();
        }

        $pearDB->commit();
    } catch (Exception $e) {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }

        throw $e;
    }
}

/**
 * Retrieve the contact group identifier for the given contact group name.
 *
 * @param string $name The contact group name to look up.
 * @return int The contact group ID if found, 0 otherwise.
 */
function getContactGroupIdByName($name)
{
    global $pearDB;

    $id = 0;
    $stmt = $pearDB->prepare('SELECT cg_id FROM contactgroup WHERE cg_name = :cgName');
    $stmt->bindValue(':cgName', $name, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row !== false) {
        $id = (int) $row['cg_id'];
    }

    return $id;
}
