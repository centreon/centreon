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
 * @param string|null $name
 * @throws Exception
 * @return bool
 */
function testExistence(?string $name = null): bool
{
    global $pearDB;
    global $form;

    $id = isset($form) ? $form->getSubmitValue('esc_id') : null;

    $stmt = $pearDB->prepare('SELECT esc_id FROM escalation WHERE esc_name = :name');
    $stmt->bindValue(':name', html_entity_decode($name, ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $stmt->execute();

    $escalation = $stmt->fetch();

    return ! ($stmt->rowCount() >= 1 && $escalation['esc_id'] !== (int) $id);
}

/**
 * @param array $escalations
 * @throws Exception
 */
function deleteEscalationInDB(array $escalations = [])
{
    global $pearDB, $centreon;

    foreach (array_keys($escalations) as $escalationId) {
        $stmt = $pearDB->prepare('SELECT esc_name FROM `escalation` WHERE `esc_id` = :escalationId LIMIT 1');
        $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
        $stmt->execute();
        $escalation = $stmt->fetch();

        $stmt = $pearDB->prepare('DELETE FROM escalation WHERE esc_id = :escalationId');
        $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
        $stmt->execute();

        $centreon->CentreonLogAction->insertLog('escalation', $escalationId, $escalation['esc_name'], 'd');
    }
}

function multipleEscalationInDB(array $escalations = [], array $nbrDup = []): void
{
    global $pearDB, $centreon;

    foreach (array_keys($escalations) as $escalationId) {
        $stmt = $pearDB->prepare('SELECT * FROM `escalation` WHERE `esc_id` = :escalationId LIMIT 1');
        $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
        $stmt->execute();

        $escalationModel = $stmt->fetch();
        if (! $escalationModel) {
            continue;
        }

        for ($i = 1; $i <= $nbrDup[$escalationId]; $i++) {
            $escalationDuplicate = $escalationModel;
            $escalationDuplicate['esc_name'] = $escalationModel['esc_name'] . '_' . $i;

            if (testExistence($escalationDuplicate['esc_name'])) {
                $escalationDuplicate['esc_id'] = insertEscalation($pearDB, $escalationDuplicate, false);

                if (! $escalationDuplicate['esc_id']) {
                    continue;
                }

                $stmt = $pearDB->prepare(
                    'SELECT DISTINCT contactgroup_cg_id
                    FROM escalation_contactgroup_relation
                    WHERE escalation_esc_id = :escalationId'
                );
                $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
                $stmt->execute();
                $escalationContactGroups = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                updateEscalationContactGroups($pearDB, $escalationContactGroups, $escalationDuplicate['esc_id']);

                $stmt = $pearDB->prepare(
                    'SELECT DISTINCT host_host_id
                    FROM escalation_host_relation
                    WHERE escalation_esc_id = :escalationId'
                );
                $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
                $stmt->execute();
                $escalationHosts = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                updateEscalationHosts($pearDB, $escalationHosts, $escalationDuplicate['esc_id']);

                $stmt = $pearDB->prepare(
                    'SELECT DISTINCT hostgroup_hg_id
                    FROM escalation_hostgroup_relation
                    WHERE escalation_esc_id = :escalationId'
                );
                $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
                $stmt->execute();
                $escalationHostGroups = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                updateEscalationHostGroups($pearDB, $escalationHostGroups, $escalationDuplicate['esc_id']);

                $stmt = $pearDB->prepare(
                    "SELECT DISTINCT CONCAT(host_host_id, '-', service_service_id)
                    FROM escalation_service_relation
                    WHERE escalation_esc_id = :escalationId"
                );
                $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
                $stmt->execute();
                $escalationServices = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                updateEscalationServices($pearDB, $escalationServices, $escalationDuplicate['esc_id']);

                $stmt = $pearDB->prepare(
                    'SELECT DISTINCT meta_service_meta_id
                    FROM escalation_meta_service_relation
                    WHERE escalation_esc_id = :escalationId'
                );
                $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
                $stmt->execute();
                $escalationMetas = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                updateEscalationMetaServices($pearDB, $escalationMetas, $escalationDuplicate['esc_id']);

                $stmt = $pearDB->prepare(
                    'SELECT DISTINCT servicegroup_sg_id
                    FROM escalation_servicegroup_relation
                    WHERE escalation_esc_id = :escalationId'
                );
                $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
                $stmt->execute();
                $escalationServiceGroups = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                updateEscalationServiceGroups($pearDB, $escalationServiceGroups, $escalationDuplicate['esc_id']);

                $centreon->CentreonLogAction->insertLog(
                    'escalation',
                    $escalationDuplicate['esc_id'],
                    $escalationDuplicate['esc_name'],
                    'a'
                );
            }
        }
    }
}

function updateEscalationInDB($escalationId): void
{
    global $form, $pearDB;

    if (! $escalationId) {
        exit;
    }
    $data = $form->getSubmitValues();
    updateEscalation($pearDB, $data, $escalationId);
    $escalationContactGroups = CentreonUtils::mergeWithInitialValues($form, 'esc_cgs');
    updateEscalationContactGroups($pearDB, $escalationContactGroups, $escalationId);
    $escalationHosts = CentreonUtils::mergeWithInitialValues($form, 'esc_hosts');
    updateEscalationHosts($pearDB, $escalationHosts, $escalationId);
    $escalationHostGroups = CentreonUtils::mergeWithInitialValues($form, 'esc_hgs');
    updateEscalationHostGroups($pearDB, $escalationHostGroups, $escalationId);
    $escalationServices = CentreonUtils::mergeWithInitialValues($form, 'esc_hServices');
    updateEscalationServices($pearDB, $escalationServices, $escalationId);
    $escalationMetas = CentreonUtils::mergeWithInitialValues($form, 'esc_metas');
    updateEscalationMetaServices($pearDB, $escalationMetas, $escalationId);
    $escalationServiceGroups = CentreonUtils::mergeWithInitialValues($form, 'esc_sgs');
    updateEscalationServiceGroups($pearDB, $escalationServiceGroups, $escalationId);
}

/**
 * @throws Exception
 * @return int|null
 */
function insertEscalationInDB(): ?int
{
    global $form, $pearDB;

    $data = $form->getSubmitValues();
    if (! $escalationId = insertEscalation($pearDB, $data)) {
        return null;
    }
    $escalationContactGroups = CentreonUtils::mergeWithInitialValues($form, 'esc_cgs');
    updateEscalationContactGroups($pearDB, $escalationContactGroups, $escalationId);
    $escalationHosts = CentreonUtils::mergeWithInitialValues($form, 'esc_hosts');
    updateEscalationHosts($pearDB, $escalationHosts, $escalationId);
    $escalationHostGroups = CentreonUtils::mergeWithInitialValues($form, 'esc_hgs');
    updateEscalationHostGroups($pearDB, $escalationHostGroups, $escalationId);
    $escalationServices = CentreonUtils::mergeWithInitialValues($form, 'esc_hServices');
    updateEscalationServices($pearDB, $escalationServices, $escalationId);
    $escalationMetas = CentreonUtils::mergeWithInitialValues($form, 'esc_metas');
    updateEscalationMetaServices($pearDB, $escalationMetas, $escalationId);
    $escalationServiceGroups = CentreonUtils::mergeWithInitialValues($form, 'esc_sgs');
    updateEscalationServiceGroups($pearDB, $escalationServiceGroups, $escalationId);

    return $escalationId;
}

/**
 * @param CentreonDB $pearDB
 * @param array<string,mixed> $data
 * @param bool $logAction (default = true)
 * @throws Exception
 * @return int|null
 */
function insertEscalation(CentreonDB $pearDB, array $data, bool $logAction = true): ?int
{
    $data = array_map('myDecode', $data);

    $query = 'INSERT INTO escalation (
            esc_name, esc_alias, first_notification, last_notification, notification_interval,
            escalation_period, host_inheritance_to_services, hostgroup_inheritance_to_services, escalation_options1,
            escalation_options2, esc_comment
        ) VALUES (
            :esc_name, :esc_alias, :first_notification, :last_notification, :notification_interval, :escalation_period,
            :host_inheritance_to_services, :hostgroup_inheritance_to_services, :escalation_options1,
            :escalation_options2, :esc_comment
        ) ';

    $params = [
        'esc_name' => PDO::PARAM_STR,
        'esc_alias' => PDO::PARAM_STR,
        'first_notification' => PDO::PARAM_INT,
        'last_notification' => PDO::PARAM_INT,
        'notification_interval' => PDO::PARAM_INT,
        'escalation_period' => PDO::PARAM_INT,
        'host_inheritance_to_services' => 'checkbox',
        'hostgroup_inheritance_to_services' => 'checkbox',
        'escalation_options1' => PDO::PARAM_STR,
        'escalation_options2' => PDO::PARAM_STR,
        'esc_comment' => PDO::PARAM_STR,
    ];

    $stmt = $pearDB->prepare($query);

    foreach ($params as $paramName => $paramType) {
        if ($paramType === PDO::PARAM_INT) {
            $stmt->bindValue(
                ':' . $paramName,
                isset($data[$paramName]) && $data[$paramName] !== '' ? $data[$paramName] : null,
                $paramType
            );
        } elseif ($paramType === PDO::PARAM_STR) {
            $value = isset($data[$paramName])
                ? (is_array($data[$paramName]) ? implode(',', array_keys($data[$paramName])) : $data[$paramName])
                : null;
            $stmt->bindValue(
                ':' . $paramName,
                $value,
                $paramType
            );
        } else {
            $stmt->bindValue(
                ':' . $paramName,
                $data[$paramName] ?? 0,
                PDO::PARAM_INT
            );
        }
    }
    $stmt->execute();

    $dbResult = $pearDB->query('SELECT MAX(esc_id) FROM escalation');
    $escalationId = $dbResult->fetch();
    $escalationId = $escalationId ? (int) $escalationId['MAX(esc_id)'] : null;

    if ($logAction) {
        logEscalation($escalationId, 'a', $data);
    }

    return $escalationId;
}

/**
 * @param CentreonDB $pearDB
 * @param array<string,mixed> $data
 * @param int $escalationId
 * @throws Exception
 */
function updateEscalation(CentreonDB $pearDB, array $data, int $escalationId): void
{
    $data = array_map('myDecode', $data);

    $query = 'UPDATE escalation SET
        esc_name = :esc_name,
        esc_alias = :esc_alias,
        first_notification = :first_notification,
        last_notification = :last_notification,
        notification_interval = :notification_interval,
        escalation_period = :escalation_period,
        host_inheritance_to_services = :host_inheritance_to_services,
        hostgroup_inheritance_to_services = :hostgroup_inheritance_to_services,
        escalation_options1 = :escalation_options1,
        escalation_options2 = :escalation_options2,
        esc_comment = :esc_comment
        WHERE esc_id = :esc_id';

    $params = [
        'esc_name' => PDO::PARAM_STR,
        'esc_alias' => PDO::PARAM_STR,
        'first_notification' => PDO::PARAM_INT,
        'last_notification' => PDO::PARAM_INT,
        'notification_interval' => PDO::PARAM_INT,
        'escalation_period' => PDO::PARAM_INT,
        'host_inheritance_to_services' => 'checkbox',
        'hostgroup_inheritance_to_services' => 'checkbox',
        'escalation_options1' => PDO::PARAM_STR,
        'escalation_options2' => PDO::PARAM_STR,
        'esc_comment' => PDO::PARAM_STR,
    ];

    $stmt = $pearDB->prepare($query);

    foreach ($params as $paramName => $paramType) {
        if ($paramType === PDO::PARAM_INT) {
            $stmt->bindValue(
                ':' . $paramName,
                isset($data[$paramName]) && $data[$paramName] !== '' ? $data[$paramName] : null,
                $paramType
            );
        } elseif ($paramType === PDO::PARAM_STR) {
            $value = isset($data[$paramName])
                ? (is_array($data[$paramName]) ? implode(',', array_keys($data[$paramName])) : $data[$paramName])
                : null;
            $stmt->bindValue(
                ':' . $paramName,
                $value,
                $paramType
            );
        } else {
            $stmt->bindValue(
                ':' . $paramName,
                isset($data[$paramName]) ? 1 : 0,
                PDO::PARAM_INT
            );
        }
    }
    $stmt->bindValue(':esc_id', $escalationId, PDO::PARAM_INT);
    $stmt->execute();

    logEscalation($escalationId, 'c', $data);
}

/**
 * Log escalation creation or update for the action log
 *
 * @param int|null $esc_id
 * @param string $action ('a' = add, 'c' = update)
 * @param array $data
 */
function logEscalation(?int $escalationId, string $action, array $data): void
{
    global $centreon;

    $fields = [
        'esc_name' => $data['esc_name'],
        'esc_alias' => $data['esc_alias'],
        'first_notification' => $data['first_notification'],
        'last_notification' => $data['last_notification'],
        'notification_interval' => $data['notification_interval'],
        'escalation_period' => $data['escalation_period'],
        'escalation_options1' => isset($data['escalation_options1'])
            ? implode(',', array_keys($data['escalation_options1']))
            : '',
        'escalation_options2' => isset($data['escalation_options2'])
            ? implode(',', array_keys($data['escalation_options2']))
            : '',
        'esc_comment' => $data['esc_comment'],
        'esc_cgs' => isset($data['esc_cgs'])
            ? implode(',', array_keys($data['esc_cgs']))
            : '',
        'esc_hosts' => isset($data['esc_hosts'])
           ? implode(',', array_keys($data['esc_hosts']))
           : '',
        'esc_hgs' => isset($data['esc_hgs'])
           ? implode(',', array_keys($data['esc_hgs']))
           : '',
        'esc_sgs' => isset($data['esc_sgs'])
           ? implode(',', array_keys($data['esc_sgs']))
           : '',
        'esc_hServices' => isset($data['esc_hServices'])
           ? implode(',', array_keys($data['esc_hServices']))
           : '',
        'esc_metas' => isset($data['esc_metas'])
           ? implode(',', array_keys($data['esc_metas']))
           : '',
    ];
    if (isset($data['host_inheritance_to_services'])) {
        $fields['host_inheritance_to_services'] = $data['host_inheritance_to_services'];
    }
    if (isset($data['hostgroup_inheritance_to_services'])) {
        $fields['hostgroup_inheritance_to_services'] = $data['hostgroup_inheritance_to_services'];
    }

    $centreon->CentreonLogAction->insertLog(
        'escalation',
        $escalationId,
        $fields['esc_name'],
        $action,
        $fields
    );
}

/**
 * @param CentreonDB $pearDB
 * @param array $escalationContactGroups
 * @param int $escalationId
 * @throws Exception
 */
function updateEscalationContactGroups(CentreonDB $pearDB, array $escalationContactGroups, int $escalationId): void
{
    $stmt = $pearDB->prepare('DELETE FROM escalation_contactgroup_relation WHERE escalation_esc_id = :escalationId');
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();

    $contactGroupManager = new CentreonContactgroup($pearDB);

    $queryParams = [];
    $params = [];
    foreach ($escalationContactGroups as $key => $contactGroupId) {
        if (! is_numeric($contactGroupId)) {
            $contactGroupId = $contactGroupManager->insertLdapGroup($contactGroupId) ?? null;
        }
        if (! $contactGroupId || ($key = filter_var($key, FILTER_VALIDATE_INT)) === false) {
            continue;
        }

        $params[':contactGroupId' . $key] = $contactGroupId;
        $queryParams[] = "(:escalationId, :contactGroupId{$key})";
    }

    if ($params === []) {
        return;
    }

    $query = 'INSERT INTO escalation_contactgroup_relation (escalation_esc_id, contactgroup_cg_id) VALUES ';
    $query .= implode(', ', $queryParams);

    $stmt = $pearDB->prepare($query);
    foreach ($params as $paramName => $value) {
        $stmt->bindValue($paramName, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * @param CentreonDB $pearDB
 * @param array $escalationHosts
 * @param int $escalationId
 * @throws Exception
 */
function updateEscalationHosts(CentreonDB $pearDB, array $escalationHosts, int $escalationId): void
{
    $stmt = $pearDB->prepare('DELETE FROM escalation_host_relation WHERE escalation_esc_id = :escalationId');
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();

    $queryParams = [];
    $params = [];
    foreach ($escalationHosts as $key => $hostId) {
        if (($key = filter_var($key, FILTER_VALIDATE_INT)) === false) {
            continue;
        }
        $params[':hostId' . $key] = $hostId;
        $queryParams[] = "(:escalationId, :hostId{$key})";
    }

    if ($params === []) {
        return;
    }

    $query = 'INSERT INTO escalation_host_relation (escalation_esc_id, host_host_id) VALUES ';
    $query .= implode(', ', $queryParams);

    $stmt = $pearDB->prepare($query);
    foreach ($params as $paramName => $value) {
        $stmt->bindValue($paramName, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * @param CentreonDB $pearDB
 * @param array $escalationHostGroups
 * @param int $escalationId
 * @throws Exception
 */
function updateEscalationHostGroups(CentreonDB $pearDB, array $escalationHostGroups, int $escalationId): void
{
    $stmt = $pearDB->prepare('DELETE FROM escalation_hostgroup_relation WHERE escalation_esc_id = :escalationId');
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();

    $queryParams = [];
    $params = [];
    foreach ($escalationHostGroups as $key => $hostGroupId) {
        if (($key = filter_var($key, FILTER_VALIDATE_INT)) === false) {
            continue;
        }
        $params[':hostGroupId' . $key] = $hostGroupId;
        $queryParams[] = "(:escalationId, :hostGroupId{$key})";
    }

    if ($params === []) {
        return;
    }

    $query = 'INSERT INTO escalation_hostgroup_relation (escalation_esc_id, hostgroup_hg_id) VALUES ';
    $query .= implode(', ', $queryParams);

    $stmt = $pearDB->prepare($query);
    foreach ($params as $paramName => $value) {
        $stmt->bindValue($paramName, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * @param CentreonDB $pearDB
 * @param array $escalationServiceGroups
 * @param int $escalationId
 * @throws Exception
 */
function updateEscalationServiceGroups(CentreonDB $pearDB, array $escalationServiceGroups, int $escalationId): void
{
    $stmt = $pearDB->prepare('DELETE FROM escalation_servicegroup_relation WHERE escalation_esc_id = :escalationId');
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();

    $queryParams = [];
    $params = [];
    foreach ($escalationServiceGroups as $key => $serviceGroupId) {
        if (($key = filter_var($key, FILTER_VALIDATE_INT)) === false) {
            continue;
        }
        $params[':serviceGroupId' . $key] = $serviceGroupId;
        $queryParams[] = "(:escalationId, :serviceGroupId{$key})";
    }

    if ($params === []) {
        return;
    }

    $query = 'INSERT INTO escalation_servicegroup_relation (escalation_esc_id, servicegroup_sg_id) VALUES ';
    $query .= implode(', ', $queryParams);

    $stmt = $pearDB->prepare($query);
    foreach ($params as $paramName => $value) {
        $stmt->bindValue($paramName, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * @param CentreonDB $pearDB
 * @param array $escalationServices
 * @param int $escalationId
 * @throws Exception
 */
function updateEscalationServices(CentreonDB $pearDB, array $escalationServices, int $escalationId): void
{
    $stmt = $pearDB->prepare('DELETE FROM escalation_service_relation WHERE escalation_esc_id = :escalationId');
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();

    $queryParams = [];
    $params = [];
    foreach ($escalationServices as $key => $serviceData) {
        if (($key = filter_var($key, FILTER_VALIDATE_INT)) === false) {
            continue;
        }
        $exp = explode('-', $serviceData);
        if (count($exp) === 2) {
            $params[':serviceId' . $key] = $exp[1];
            $params[':hostId' . $key] = $exp[0];

            $queryParams[] = "(:escalationId, :serviceId{$key}, :hostId{$key})";
        }
    }

    if ($params === []) {
        return;
    }

    $query = 'INSERT INTO escalation_service_relation (escalation_esc_id, service_service_id, host_host_id) VALUES ';
    $query .= implode(', ', $queryParams);

    $stmt = $pearDB->prepare($query);
    foreach ($params as $paramName => $value) {
        $stmt->bindValue($paramName, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * @param CentreonDB $pearDB
 * @param array $escalationMetas
 * @param int $escalationId
 * @throws Exception
 */
function updateEscalationMetaServices(CentreonDB $pearDB, array $escalationMetas, int $escalationId): void
{
    $stmt = $pearDB->prepare('DELETE FROM escalation_meta_service_relation WHERE escalation_esc_id = :escalationId');
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();

    $queryParams = [];
    $params = [];
    foreach ($escalationMetas as $key => $metaServiceId) {
        if (($key = filter_var($key, FILTER_VALIDATE_INT)) === false) {
            continue;
        }
        $params[':metaServiceId' . $key] = $metaServiceId;
        $queryParams[] = "(:escalationId, :metaServiceId{$key})";
    }

    if ($params === []) {
        return;
    }

    $query = 'INSERT INTO escalation_meta_service_relation (escalation_esc_id, meta_service_meta_id) VALUES ';
    $query .= implode(', ', $queryParams);

    $stmt = $pearDB->prepare($query);
    foreach ($params as $paramName => $value) {
        $stmt->bindValue($paramName, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':escalationId', $escalationId, PDO::PARAM_INT);
    $stmt->execute();
}
