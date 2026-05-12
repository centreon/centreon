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

function testContactGroupExistence($name = null)
{
    global $pearDB, $form, $centreon;
    $id = null;

    if (isset($form)) {
        $id = $form->getSubmitValue('cg_id');
    }
    $stmt = $pearDB->prepare(
        'SELECT `cg_name`, `cg_id` FROM `contactgroup` WHERE `cg_name` = :cgName'
    );
    $stmt->bindValue(':cgName', htmlentities($centreon->checkIllegalChar($name), ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $stmt->execute();
    $cg = $stmt->fetch();

    if ($stmt->rowCount() >= 1 && $cg['cg_id'] == $id) {
        // Modif case
        return true;
    }

    return ! ($stmt->rowCount() >= 1 && $cg['cg_id'] != $id);
    // Duplicate entry

}

function enableContactGroupInDB($cg_id = null)
{
    global $pearDB, $centreon;

    if (! $cg_id) {
        return;
    }
    $stmt = $pearDB->prepare("UPDATE `contactgroup` SET `cg_activate` = '1' WHERE `cg_id` = :cgId");
    $stmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt2 = $pearDB->prepare('SELECT cg_name FROM `contactgroup` WHERE `cg_id` = :cgId LIMIT 1');
    $stmt2->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $stmt2->execute();
    $dbResult2 = $stmt2;
    $row = $dbResult2->fetch();

    $centreon->CentreonLogAction->insertLog('contactgroup', $cg_id, $row['cg_name'], 'enable');
}

function disableContactGroupInDB($cg_id = null)
{
    global $pearDB, $centreon;

    if (! $cg_id) {
        return;
    }
    $stmt = $pearDB->prepare("UPDATE `contactgroup` SET `cg_activate` = '0' WHERE `cg_id` = :cgId");
    $stmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt2 = $pearDB->prepare('SELECT cg_name FROM `contactgroup` WHERE `cg_id` = :cgId LIMIT 1');
    $stmt2->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $stmt2->execute();
    $dbResult2 = $stmt2;
    $row = $dbResult2->fetch();

    $centreon->CentreonLogAction->insertLog('contactgroup', $cg_id, $row['cg_name'], 'disable');
}

function deleteContactGroupInDB($contactGroups = [])
{
    global $pearDB, $centreon;

    $stmt = $pearDB->prepare('SELECT cg_name FROM `contactgroup` WHERE `cg_id` = :cgId LIMIT 1');
    $deleteStmt = $pearDB->prepare('DELETE FROM `contactgroup` WHERE `cg_id` = :cgId');

    foreach ($contactGroups as $key => $value) {
        $stmt->bindValue(':cgId', (int) $key, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        $deleteStmt->bindValue(':cgId', (int) $key, PDO::PARAM_INT);
        $deleteStmt->execute();
        $centreon->CentreonLogAction->insertLog('contactgroup', $key, $row['cg_name'], 'd');
    }
}

function multipleContactGroupInDB($contactGroups = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    $selectStmt = $pearDB->prepare('SELECT * FROM `contactgroup` WHERE `cg_id` = :cgId LIMIT 1');
    $aclSelectStmt = $pearDB->prepare(
        'SELECT DISTINCT `acl_group_id` FROM `acl_group_contactgroups_relations` WHERE `cg_cg_id` = :cgId'
    );
    $aclInsertStmt = $pearDB->prepare(
        'INSERT INTO `acl_group_contactgroups_relations` VALUES (:maxId, :cgAcl)'
    );
    $contactSelectStmt = $pearDB->prepare(
        'SELECT DISTINCT `cgcr`.`contact_contact_id` FROM `contactgroup_contact_relation` `cgcr`'
        . ' WHERE `cgcr`.`contactgroup_cg_id` = :cgId'
    );
    $contactInsertStmt = $pearDB->prepare(
        'INSERT INTO `contactgroup_contact_relation` VALUES (:cct, :maxId)'
    );

    foreach ($contactGroups as $key => $value) {
        $selectStmt->bindValue(':cgId', (int) $key, PDO::PARAM_INT);
        $selectStmt->execute();

        $row = $selectStmt->fetch();
        if ($row === false) {
            continue;
        }
        unset($row['cg_id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO `contactgroup` (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $originalName = $row['cg_name'];
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $cg_name = $originalName . '_' . $i;
            $row['cg_name'] = $cg_name;

            if (testContactGroupExistence($cg_name)) {
                foreach ($columns as $col) {
                    $insertStmt->bindValue(':' . $col, $row[$col], $row[$col] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();

                $dbResult = $pearDB->query('SELECT MAX(cg_id) FROM `contactgroup`');
                $maxId = $dbResult->fetch();

                if (isset($maxId['MAX(cg_id)'])) {
                    $aclSelectStmt->bindValue(':cgId', (int) $key, PDO::PARAM_INT);
                    $aclSelectStmt->execute();
                    $fields['cg_aclRelation'] = '';
                    while ($cgAcl = $aclSelectStmt->fetch()) {
                        $aclInsertStmt->bindValue(':maxId', (int) $maxId['MAX(cg_id)'], PDO::PARAM_INT);
                        $aclInsertStmt->bindValue(':cgAcl', (int) $cgAcl['acl_group_id'], PDO::PARAM_INT);
                        $aclInsertStmt->execute();
                        $fields['cg_aclRelation'] .= $cgAcl['acl_group_id'] . ',';
                    }
                    $contactSelectStmt->bindValue(':cgId', (int) $key, PDO::PARAM_INT);
                    $contactSelectStmt->execute();
                    $fields['cg_contacts'] = '';
                    while ($cct = $contactSelectStmt->fetch()) {
                        $contactInsertStmt->bindValue(':cct', (int) $cct['contact_contact_id'], PDO::PARAM_INT);
                        $contactInsertStmt->bindValue(':maxId', (int) $maxId['MAX(cg_id)'], PDO::PARAM_INT);
                        $contactInsertStmt->execute();
                        $fields['cg_contacts'] .= $cct['contact_contact_id'] . ',';
                    }
                    $fields['cg_contacts'] = trim($fields['cg_contacts'], ',');
                    $centreon->CentreonLogAction->insertLog(
                        'contactgroup',
                        $maxId['MAX(cg_id)'],
                        $cg_name,
                        'a',
                        $fields
                    );
                }
            }
        }
    }
}

function insertContactGroupInDB($ret = [])
{
    $cg_id = insertContactGroup($ret);
    updateContactGroupContacts($cg_id, $ret);
    updateContactGroupAclGroups($cg_id, $ret);

    return $cg_id;
}

/**
 * @param $ret
 * @return int
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

    $dbResult = $pearDB->query('SELECT MAX(cg_id) FROM `contactgroup`');
    $cgId = $dbResult->fetch();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        'contactgroup',
        $cgId['MAX(cg_id)'],
        $cgName,
        'a',
        $fields
    );

    return (int) $cgId['MAX(cg_id)'];
}

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
 * @param null $cgId
 * @param array $params
 */
function updateContactGroup($cgId = null, $params = [])
{
    global $form, $pearDB, $centreon;
    if (! $cgId) {
        return;
    }
    $ret = [];
    $ret = count($params) ? $params : $form->getSubmitValues();

    $cgName = $centreon->checkIllegalChar(
        HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_name'])
    );
    $cgAlias = HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_alias']);
    $cgComment = HtmlAnalyzer::sanitizeAndRemoveTags($ret['cg_comment']);
    $cgActivate = $ret['cg_activate']['cg_activate'] === '0' ? '0' : '1'; // enum

    $stmt = $pearDB->prepare(
        'UPDATE `contactgroup` SET `cg_name` = :cgName, `cg_alias` = :cgAlias, `cg_comment` = :cgComment, '
        . '`cg_activate` = :cgActivate  WHERE `cg_id` = :cgId'
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

function updateContactGroupContacts($cg_id, $ret = [])
{
    global $centreon, $form, $pearDB;
    if (! $cg_id) {
        return;
    }

    $deleteStmt = $pearDB->prepare('DELETE FROM `contactgroup_contact_relation` WHERE `contactgroup_cg_id` = :cgId');
    $deleteStmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $deleteStmt->execute();

    $ret = $ret['cg_contacts'] ?? CentreonUtils::mergeWithInitialValues($form, 'cg_contacts');
    $counter = count($ret);

    $insertStmt = $pearDB->prepare(
        'INSERT INTO `contactgroup_contact_relation` (`contact_contact_id`, `contactgroup_cg_id`) VALUES (:contactId, :cgId)'
    );
    for ($i = 0; $i < $counter; $i++) {
        $insertStmt->bindValue(':contactId', (int) $ret[$i], PDO::PARAM_INT);
        $insertStmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
        $insertStmt->execute();

        CentreonCustomView::syncContactGroupCustomView($centreon, $pearDB, $ret[$i]);
    }
}

function updateContactGroupAclGroups($cg_id, $ret = [])
{
    global $form, $pearDB;

    if (! $cg_id) {
        return;
    }

    $deleteStmt = $pearDB->prepare('DELETE FROM `acl_group_contactgroups_relations` WHERE `cg_cg_id` = :cgId');
    $deleteStmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
    $deleteStmt->execute();

    if (isset($ret['cg_acl_groups'])) {
        $ret = $ret['cg_acl_groups'];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'cg_acl_groups');
    }
    $counter = count($ret);

    $insertStmt = $pearDB->prepare(
        'INSERT INTO `acl_group_contactgroups_relations` (`acl_group_id`, `cg_cg_id`) VALUES (:aclGroupId, :cgId)'
    );
    for ($i = 0; $i < $counter; $i++) {
        $insertStmt->bindValue(':aclGroupId', (int) $ret[$i], PDO::PARAM_INT);
        $insertStmt->bindValue(':cgId', (int) $cg_id, PDO::PARAM_INT);
        $insertStmt->execute();
    }
}

/**
 * Get contact group id by name
 *
 * @param string $name
 * @return int
 */
function getContactGroupIdByName($name)
{
    global $pearDB;

    $id = 0;
    $stmt = $pearDB->prepare('SELECT cg_id FROM contactgroup WHERE cg_name = :cgName');
    $stmt->bindValue(':cgName', $name, PDO::PARAM_STR);
    $stmt->execute();
    $res = $stmt;
    if ($res->rowCount()) {
        $row = $res->fetch();
        $id = $row['cg_id'];
    }

    return $id;
}
