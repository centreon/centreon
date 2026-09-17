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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Application\Common\Session\Repository\ReadSessionRepositoryInterface;

if (! isset($centreon)) {
    exit();
}

/**
 * @param null $name
 * @return bool
 */
function testActionExistence($name = null)
{
    global $pearDB, $form;

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('acl_action_id');
    }
    $statement = $pearDB->prepare(
        'SELECT acl_action_id, acl_action_name FROM acl_actions '
        . 'WHERE acl_action_name = :name'
    );
    $statement->bindValue(':name', htmlentities($name, ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->execute();
    $action = $statement->fetch();
    // Modif case
    if ($statement->rowCount() >= 1 && $action['acl_action_id'] == $id) {
        return true;
    } // Duplicate entry

    return ! ($statement->rowCount() >= 1 && $action['acl_action_id'] != $id);
}

/**
 * @param null $aclActionId
 * @param array $actions
 */
function enableActionInDB($aclActionId = null, $actions = [])
{
    if ($aclActionId === null && empty($actions)) {
        return;
    }

    global $pearDB, $centreon;

    if ($aclActionId) {
        $actions = [$aclActionId => '1'];
    }

    $queryValues = [];

    $updateStmt = $pearDB->prepare(
        "UPDATE acl_actions SET acl_action_activate = '1' WHERE acl_action_id = :id"
    );
    $selectStmt = $pearDB->prepare(
        'SELECT acl_action_name FROM `acl_actions` WHERE acl_action_id = :id LIMIT 1'
    );

    foreach ($actions as $key => $value) {
        $sanitizedAclActionId = filter_var($key, FILTER_VALIDATE_INT);
        if ($sanitizedAclActionId === false) {
            throw new InvalidArgumentException('Invalid id');
        }
        $queryValues[':acl_action_id_' . $sanitizedAclActionId] = $sanitizedAclActionId;

        $updateStmt->bindValue(':id', $sanitizedAclActionId, PDO::PARAM_INT);
        $updateStmt->execute();

        $selectStmt->bindValue(':id', $sanitizedAclActionId, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch();
        $centreon->CentreonLogAction->insertLog(
            'action access',
            $sanitizedAclActionId,
            $row['acl_action_name'],
            'enable'
        );
    }

    updateACLActionsForAuthentifiedUsers($queryValues);
}

/**
 * @param null $aclActionId
 * @param array $actions
 */
function disableActionInDB($aclActionId = null, $actions = [])
{
    if ($aclActionId === null && empty($actions)) {
        return;
    }

    global $pearDB, $centreon;

    if ($aclActionId) {
        $actions = [$aclActionId => '1'];
    }

    $queryValues = [];

    $updateStmt = $pearDB->prepare(
        "UPDATE acl_actions SET acl_action_activate = '0' WHERE acl_action_id = :id"
    );
    $selectStmt = $pearDB->prepare(
        'SELECT acl_action_name FROM `acl_actions` WHERE acl_action_id = :id LIMIT 1'
    );

    foreach ($actions as $key => $value) {
        $sanitizedAclActionId = filter_var($key, FILTER_VALIDATE_INT);
        if ($sanitizedAclActionId === false) {
            throw new InvalidArgumentException('Invalid id');
        }
        $queryValues[':acl_action_id_' . $sanitizedAclActionId] = $sanitizedAclActionId;

        $updateStmt->bindValue(':id', $sanitizedAclActionId, PDO::PARAM_INT);
        $updateStmt->execute();

        $selectStmt->bindValue(':id', $sanitizedAclActionId, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch();
        $centreon->CentreonLogAction->insertLog(
            'action access',
            $sanitizedAclActionId,
            $row['acl_action_name'],
            'disable'
        );
    }

    updateACLActionsForAuthentifiedUsers($queryValues);
}

/**
 * delete an action rules
 * @param $actions
 */
function deleteActionInDB($actions = [])
{
    global $pearDB, $centreon;

    $aclGroupIds = [];
    $deleteActionStmt = $pearDB->prepare('DELETE FROM acl_actions WHERE acl_action_id = :id');
    $deleteRulesStmt = $pearDB->prepare('DELETE FROM acl_actions_rules WHERE acl_action_rule_id = :id');
    $deleteRelStmt = $pearDB->prepare('DELETE FROM acl_group_actions_relations WHERE acl_action_id = :id');

    foreach ($actions as $key => $value) {
        $sanitizedAclActionId = filter_var($key, FILTER_VALIDATE_INT);
        if ($sanitizedAclActionId === false) {
            throw new InvalidArgumentException('Invalid id');
        }
        $queryValues[':acl_action_id_' . $sanitizedAclActionId] = $sanitizedAclActionId;
        $statement = $pearDB->prepare(
            'SELECT acl_action_name FROM `acl_actions`
                WHERE acl_action_id = :acl_action_id_' . $sanitizedAclActionId . ' LIMIT 1'
        );
        $statement->bindValue(
            ':acl_action_id_' . $sanitizedAclActionId,
            $queryValues[':acl_action_id_' . $sanitizedAclActionId],
            PDO::PARAM_INT
        );
        $statement->execute();
        $row = $statement->fetch();
        $aclActionIdQueryString = '(' . implode(', ', array_keys($queryValues)) . ')';
        $statement = $pearDB->prepare(
            "SELECT DISTINCT acl_group_id FROM acl_group_actions_relations
                WHERE acl_action_id IN {$aclActionIdQueryString}"
        );
        foreach ($queryValues as $bindParameter => $bindValue) {
            $statement->bindValue($bindParameter, $bindValue, PDO::PARAM_INT);
        }
        $statement->execute();
        while ($result = $statement->fetch()) {
            $aclGroupIds[] = (int) $result['acl_group_id'];
        }
        $deleteActionStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $deleteActionStmt->execute();
        $deleteRulesStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $deleteRulesStmt->execute();
        $deleteRelStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $deleteRelStmt->execute();
        $centreon->CentreonLogAction->insertLog('action access', $key, $row['acl_action_name'], 'd');
    }
    flagUpdatedAclForAuthentifiedUsers($aclGroupIds);
}

/**
 * Duplicate an action rules
 * @param $actions
 * @param $nbrDup
 */
function multipleActionInDB($actions = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    $selectStmt = $pearDB->prepare('SELECT * FROM acl_actions WHERE acl_action_id = :id LIMIT 1');
    $selectGroupStmt = $pearDB->prepare(
        'SELECT DISTINCT acl_group_id,acl_action_id FROM acl_group_actions_relations '
        . 'WHERE acl_action_id = :id'
    );
    $insertGroupStmt = $pearDB->prepare(
        'INSERT INTO acl_group_actions_relations VALUES (:acl_action_id, :acl_group_id)'
    );
    $selectRulesStmt = $pearDB->prepare(
        'SELECT acl_action_rule_id,acl_action_name FROM acl_actions_rules '
        . 'WHERE acl_action_rule_id = :id'
    );
    $insertRulesStmt = $pearDB->prepare(
        'INSERT INTO acl_actions_rules VALUES (NULL, :acl_action_id, :acl_action_name)'
    );

    foreach (array_keys($actions) as $key) {
        $selectStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch();

        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $aclActionName = $row['acl_action_name'] . '_' . $i;
            if (testActionExistence($aclActionName)) {
                $pearDB->executeStatement(
                    <<<'SQL'
                        INSERT INTO acl_actions (acl_action_name, acl_action_description, acl_action_activate)
                        VALUES (:aclActionName, :aclActionDescription, :aclActionActivate)
                        SQL,
                    QueryParameters::create([
                        QueryParameter::string('aclActionName', $aclActionName),
                        QueryParameter::string(
                            'aclActionDescription',
                            $row['acl_action_description']
                        ),
                        QueryParameter::string(
                            'aclActionActivate',
                            $row['acl_action_activate']
                        ),
                    ])
                );
                $dbResult = $pearDB->query('SELECT MAX(acl_action_id) FROM acl_actions');
                $maxId = $dbResult->fetch();
                $dbResult->closeCursor();
                if (isset($maxId['MAX(acl_action_id)'])) {
                    $selectGroupStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
                    $selectGroupStmt->execute();
                    while ($cct = $selectGroupStmt->fetch()) {
                        $insertGroupStmt->bindValue(':acl_action_id', (int) $maxId['MAX(acl_action_id)'], PDO::PARAM_INT);
                        $insertGroupStmt->bindValue(':acl_group_id', (int) $cct['acl_group_id'], PDO::PARAM_INT);
                        $insertGroupStmt->execute();
                    }

                    // Duplicate Actions
                    $selectRulesStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
                    $selectRulesStmt->execute();
                    while ($acl = $selectRulesStmt->fetch()) {
                        $insertRulesStmt->bindValue(':acl_action_id', (int) $maxId['MAX(acl_action_id)'], PDO::PARAM_INT);
                        $insertRulesStmt->bindValue(':acl_action_name', $acl['acl_action_name'], PDO::PARAM_STR);
                        $insertRulesStmt->execute();
                    }

                    $centreon->CentreonLogAction->insertLog(
                        'action access',
                        $maxId['MAX(acl_action_id)'],
                        $aclActionName,
                        'a',
                        [
                            'acl_action_name' => $aclActionName,
                            'acl_action_description' => $row['acl_action_description'],
                            'acl_action_activate' => $row['acl_action_activate'],
                        ]
                    );
                }
            }
        }
    }
}

/**
 * Insert all information in DB
 * @param $ret
 */
function insertActionInDB($ret = [])
{
    global $form, $centreon;

    $aclActionId = insertAction($ret);
    updateGroupActions($aclActionId, $ret);
    updateRulesActions($aclActionId, $ret);
    $ret = $form->getSubmitValues();
    flagUpdatedAclForAuthentifiedUsers($ret['acl_groups']);
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog('action access', $aclActionId, $ret['acl_action_name'], 'a', $fields);

    return $aclActionId;
}

/**
 * Insert actions
 * @param $ret
 */
function insertAction($ret)
{
    global $form, $pearDB;
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $statement = $pearDB->prepare(
        'INSERT INTO acl_actions (acl_action_name, acl_action_description, acl_action_activate) '
            . 'VALUES (:aclActionName, :aclActionDescription, :aclActionActivate)'
    );
    $statement->bindValue(
        ':aclActionName',
        htmlentities($ret['acl_action_name'], ENT_QUOTES, 'UTF-8'),
        PDO::PARAM_STR
    );
    $statement->bindValue(
        ':aclActionDescription',
        $ret['acl_action_description'],
        PDO::PARAM_STR
    );
    $statement->bindValue(
        ':aclActionActivate',
        htmlentities(
            (
                isset($ret['acl_action_activate'])
                ? $ret['acl_action_activate']['acl_action_activate']
                : ''
            ),
            ENT_QUOTES,
            'UTF-8'
        ),
        PDO::PARAM_STR
    );
    $statement->execute();
    $dbResult = $pearDB->query('SELECT MAX(acl_action_id) FROM acl_actions');
    $cg_id = $dbResult->fetch();

    return $cg_id['MAX(acl_action_id)'];
}

/**
 * Summary function
 * @param $aclActionId
 */
function updateActionInDB($aclActionId = null)
{
    global $form, $centreon;

    if (! $aclActionId) {
        return;
    }
    updateAction($aclActionId);
    updateGroupActions($aclActionId);
    $ret = $form->getSubmitValues();
    flagUpdatedAclForAuthentifiedUsers($ret['acl_groups']);
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog('action access', $aclActionId, $ret['acl_action_name'], 'c', $fields);
}

/**
 * Update all Actions
 * @param $aclActionId
 */
function updateAction($aclActionId = null)
{
    if (! $aclActionId) {
        return;
    }
    global $form, $pearDB;

    $ret = $form->getSubmitValues();
    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE acl_actions
            SET acl_action_name = :acl_action_name,
                acl_action_description = :acl_action_description,
                acl_action_activate = :acl_action_activate
            WHERE acl_action_id = :acl_action_id
            SQL,
        QueryParameters::create([
            QueryParameter::string('acl_action_name', $ret['acl_action_name']),
            QueryParameter::string(
                'acl_action_description',
                $ret['acl_action_description']
            ),
            QueryParameter::string(
                'acl_action_activate',
                $ret['acl_action_activate']['acl_action_activate']
            ),
            QueryParameter::int('acl_action_id', $ret['acl_action_id']),
        ])
    );
}

/**
 * Update group action information in DB
 * @param $aclActionId
 * @param $ret
 */
function updateGroupActions($aclActionId, $ret = [])
{
    if (! $aclActionId) {
        return;
    }
    global $form, $pearDB;

    $rq = 'DELETE FROM acl_group_actions_relations WHERE acl_action_id = :acl_action_id';
    $statement = $pearDB->prepare($rq);
    $statement->bindValue(':acl_action_id', (int) $aclActionId, PDO::PARAM_INT);
    $statement->execute();
    if (isset($_POST['acl_groups'])) {
        $insertStmt = $pearDB->prepare(
            'INSERT INTO acl_group_actions_relations (acl_group_id, acl_action_id) '
            . 'VALUES (:acl_group_id, :acl_action_id)'
        );
        foreach ($_POST['acl_groups'] as $id) {
            $insertStmt->bindValue(':acl_group_id', (int) $id, PDO::PARAM_INT);
            $insertStmt->bindValue(':acl_action_id', (int) $aclActionId, PDO::PARAM_INT);
            $insertStmt->execute();
        }
    }
}

/**
 * update all Rules in DB
 * @param $aclActionId
 * @param $ret
 */
function updateRulesActions($aclActionId, $ret = [])
{
    global $form, $pearDB;

    if (! $aclActionId) {
        return;
    }

    $rq = 'DELETE FROM acl_actions_rules WHERE acl_action_rule_id = :acl_action_rule_id';
    $statement = $pearDB->prepare($rq);
    $statement->bindValue(':acl_action_rule_id', (int) $aclActionId, PDO::PARAM_INT);
    $statement->execute();

    $actions = [];
    $actions = listActions();

    $insertStmt = $pearDB->prepare(
        'INSERT INTO acl_actions_rules (acl_action_rule_id, acl_action_name) '
        . 'VALUES (:acl_action_rule_id, :acl_action_name)'
    );
    foreach ($actions as $action) {
        if (isset($_POST[$action])) {
            $insertStmt->bindValue(':acl_action_rule_id', (int) $aclActionId, PDO::PARAM_INT);
            $insertStmt->bindValue(':acl_action_name', $action, PDO::PARAM_STR);
            $insertStmt->execute();
        }
    }
}

/**
 * list all actions
 */
function listActions()
{
    global $dependencyInjector;

    $actions = [];
    $informationsService = $dependencyInjector['centreon_remote.informations_service'];
    $serverIsMaster = $informationsService->serverIsMaster();

    // Global Functionnality access
    $actions[] = 'poller_listing';
    $actions[] = 'poller_stats';
    $actions[] = 'top_counter';

    // Services Actions
    if ($serverIsMaster) {
        $actions[] = 'service_checks';
        $actions[] = 'service_notifications';
    }
    $actions[] = 'service_acknowledgement';
    $actions[] = 'service_disacknowledgement';
    $actions[] = 'service_schedule_check';
    $actions[] = 'service_schedule_forced_check';
    $actions[] = 'service_schedule_downtime';
    $actions[] = 'service_comment';
    if ($serverIsMaster) {
        $actions[] = 'service_event_handler';
        $actions[] = 'service_flap_detection';
        $actions[] = 'service_passive_checks';
    }
    $actions[] = 'service_submit_result';
    $actions[] = 'service_display_command';

    // Hosts Actions
    if ($serverIsMaster) {
        $actions[] = 'host_checks';
        $actions[] = 'host_notifications';
    }
    $actions[] = 'host_acknowledgement';
    $actions[] = 'host_disacknowledgement';
    $actions[] = 'host_schedule_check';
    $actions[] = 'host_schedule_forced_check';
    $actions[] = 'host_schedule_downtime';
    $actions[] = 'host_comment';
    if ($serverIsMaster) {
        $actions[] = 'host_event_handler';
        $actions[] = 'host_flap_detection';
        $actions[] = 'host_checks_for_services';
        $actions[] = 'host_notifications_for_services';
    }
    $actions[] = 'host_submit_result';

    // Global Nagios External Commands
    $actions[] = 'global_shutdown';
    $actions[] = 'global_restart';
    $actions[] = 'global_notifications';
    $actions[] = 'global_service_checks';
    $actions[] = 'global_service_passive_checks';
    $actions[] = 'global_host_checks';
    $actions[] = 'global_host_passive_checks';
    $actions[] = 'global_event_handler';
    $actions[] = 'global_flap_detection';
    $actions[] = 'global_service_obsess';
    $actions[] = 'global_host_obsess';
    $actions[] = 'global_perf_data';

    $actions[] = 'create_edit_poller_cfg';
    $actions[] = 'delete_poller_cfg';
    $actions[] = 'generate_cfg';
    $actions[] = 'generate_trap';
    $actions[] = 'manage_tokens';

    $actions[] = 'see_check_commands';
    $actions[] = 'manage_check_commands';
    $actions[] = 'see_notification_commands';
    $actions[] = 'manage_notification_commands';
    $actions[] = 'see_discovery_commands';
    $actions[] = 'manage_discovery_commands';
    $actions[] = 'see_miscellaneous_commands';
    $actions[] = 'manage_miscellaneous_commands';

    return $actions;
}

/**
 * Updates ACL actions for an authentified user from ACL Action ID.
 * Ex: $queryValue = [':acl_action_id_1' => 1, ..., ':acl_action_id_3' => 3]
 *
 * @param array<string,string> $queryValues
 */
function updateAclActionsForAuthentifiedUsers(array $queryValues): void
{
    $aclGroupIds = getAclGroupIdsByActionIds($queryValues);
    flagUpdatedAclForAuthentifiedUsers($aclGroupIds);
}

/**
 * This method flags updated ACL for authentified users.
 *
 * @param int[] $aclGroupIds
 */
function flagUpdatedAclForAuthentifiedUsers(array $aclGroupIds): void
{
    global $pearDB;
    $userIds = getUsersIdsByAclGroup($aclGroupIds);
    $readSessionRepository = getReadSessionRepository();
    foreach ($userIds as $userId) {
        $sessionIds = $readSessionRepository->findSessionIdsByUserId($userId);
        $statement = $pearDB->prepare("UPDATE session SET update_acl = '1' WHERE session_id = :sessionId");
        foreach ($sessionIds as $sessionId) {
            $statement->bindValue(':sessionId', $sessionId, PDO::PARAM_STR);
            $statement->execute();
        }
    }
}

/**
 * This function returns user ids from ACL Group Ids
 *
 * @param int[] $aclGroupIds
 * @return int[]
 */
function getUsersIdsByAclGroup(array $aclGroupIds): array
{
    global $pearDB;

    $queryValues = [];
    foreach ($aclGroupIds as $index => $aclGroupId) {
        $sanitizedAclGroupId = filter_var($aclGroupId, FILTER_VALIDATE_INT);
        if ($sanitizedAclGroupId !== false) {
            $queryValues[':acl_group_id_' . $index] = $sanitizedAclGroupId;
        }
    }

    $aclGroupIdQueryString = '(' . implode(', ', array_keys($queryValues)) . ')';
    $statement = $pearDB->prepare(
        "SELECT DISTINCT `contact_contact_id` FROM `acl_group_contacts_relations`
            WHERE `acl_group_id`
            IN {$aclGroupIdQueryString}"
    );
    foreach ($queryValues as $bindParameter => $bindValue) {
        $statement->bindValue($bindParameter, $bindValue, PDO::PARAM_INT);
    }
    $statement->execute();
    $userIds = [];
    while ($result = $statement->fetch()) {
        $userIds[] = (int) $result['contact_contact_id'];
    }

    return $userIds;
}

/**
 * This method gets SessionRepository from Service container
 *
 * @return ReadSessionRepositoryInterface
 */
function getReadSessionRepository(): ReadSessionRepositoryInterface
{
    $kernel = App\Kernel::createForWeb();

    return $kernel->getContainer()->get(
        ReadSessionRepositoryInterface::class
    );
}

/**
 * Returns ACL Group IDs
 * Ex: $queryValue = [':acl_action_id_1' => 1, ..., ':acl_action_id_3' => 3]
 *
 * @param array<string,string> $queryValues
 * @return int[]
 */
function getAclGroupIdsByActionIds(array $queryValues): array
{
    global $pearDB;
    $aclActionIdQueryString = '(' . implode(', ', array_keys($queryValues)) . ')';
    $statement = $pearDB->prepare(
        "SELECT DISTINCT acl_group_id FROM acl_group_actions_relations
            WHERE acl_action_id IN {$aclActionIdQueryString}"
    );
    foreach ($queryValues as $bindParameter => $bindValue) {
        $statement->bindValue($bindParameter, $bindValue, PDO::PARAM_INT);
    }
    $statement->execute();
    $aclGroupIds = [];
    while ($result = $statement->fetch()) {
        $aclGroupIds[] = (int) $result['acl_group_id'];
    }

    return $aclGroupIds;
}
