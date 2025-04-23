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
 */

if (!isset($centreon)) {
    exit();
}

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\ActionLog\Domain\Model\ActionLog;
use CentreonLog;

/**
 * Retrieve only the allowed host-category form fields and sanitize them.
 */
function getHostCategoryValues(): array
{
    global $form;
    $raw = $form ? $form->getSubmitValues() : [];

    // Only these fields are permitted from user input
    $allowed = [
        'hc_name', 'hc_alias', 'hc_type',
        'hc_severity_level', 'hc_severity_icon',
        'hc_comment', 'hc_activate',
        'hc_hosts', 'hc_hostsTemplate'
    ];

    $ret = [];
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $raw)) {
            continue;
        }
        $value = $raw[$field];
        // Sanitize strings
        if (is_string($value)) {
            $value = \HtmlSanitizer::createFromString($value)
                ->removeTags()
                ->sanitize()
                ->getString();
        }
        $ret[$field] = $value;
    }

    return $ret;
}

/**
 * Rule that checks whether severity data is set
 */
function checkSeverity(array $fields)
{
    $errors = [];
    if (!empty($fields['hc_type']) && ($fields['hc_severity_level'] ?? '') === '') {
        $errors['hc_severity_level'] = 'Severity level is required';
    }
    if (!empty($fields['hc_type']) && ($fields['hc_severity_icon'] ?? '') === '') {
        $errors['hc_severity_icon'] = 'Severity icon is required';
    }
    return $errors ?: true;
}

/**
 * Check existence of a host category name
 */
function testHostCategorieExistence(?string $name = null)
{
    global $pearDB, $form;

    if (empty($name)) {
        return false;
    }

    $currentId = $form ? $form->getSubmitValue('hc_id') : null;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->select('hc_id')
        ->from('hostcategories')
        ->where('hc_name = :hc_name')
        ->getQuery();

    try {
        $result = $pearDB->fetchAssociative(
            $query,
            QueryParameters::create([
                QueryParameter::string(
                    'hc_name',
                    \HtmlSanitizer::createFromString($name)
                        ->removeTags()
                        ->sanitize()
                        ->getString()
                )
            ])
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error checking host category existence',
            ['hcName' => $name],
            $exception
        );
        return false;
    }

    return !($result && isset($result['hc_id']) && $result['hc_id'] != $currentId);
}

/**
 * Simple boolean check (legacy)
 */
function shouldNotBeEqTo0($value)
{
    return (bool) $value;
}

/**
 * Enable one or multiple host categories
 */
function enableHostCategoriesInDB(?int $hcId = null, array $hcArr = [])
{
    global $pearDB, $centreon;

    if (!$hcId && empty($hcArr)) {
        return;
    }
    if ($hcId) {
        $hcArr = [$hcId => '1'];
    }

    $updQuery = $pearDB->createQueryBuilder()
        ->update('hostcategories')
        ->set('hc_activate', '1')
        ->where('hc_id = :hc_id')
        ->getQuery();
    $selQuery = $pearDB->createQueryBuilder()
        ->select('hc_name')
        ->from('hostcategories')
        ->where('hc_id = :hc_id')
        ->getQuery();

    foreach (array_keys($hcArr) as $key) {
        $id = filter_var($key, FILTER_VALIDATE_INT);
        try {
            $pearDB->update(
                $updQuery,
                QueryParameters::create([QueryParameter::int('hc_id', (int) $id)])
            );
            $row = $pearDB->fetchAssociative(
                $selQuery,
                QueryParameters::create([QueryParameter::int('hc_id', (int) $id)])
            );
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_HOSTCATEGORIES,
                object_id: $id,
                object_name: $row['hc_name'] ?? '',
                action_type: ActionLog::ACTION_TYPE_ENABLE
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error enabling host category',
                ['hcId' => $id],
                $exception
            );
        }
    }
}

/**
 * Disable one or multiple host categories
 */
function disableHostCategoriesInDB(?int $hcId = null, array $hcArr = [])
{
    global $pearDB, $centreon;

    if (!$hcId && empty($hcArr)) {
        return;
    }
    if ($hcId) {
        $hcArr = [$hcId => '1'];
    }

    $updQuery = $pearDB->createQueryBuilder()
        ->update('hostcategories')
        ->set('hc_activate', "'0'")
        ->where('hc_id = :hc_id')
        ->getQuery();
    $selQuery = $pearDB->createQueryBuilder()
        ->select('hc_name')
        ->from('hostcategories')
        ->where('hc_id = :hc_id')
        ->getQuery();

    foreach (array_keys($hcArr) as $key) {
        $id = filter_var($key, FILTER_VALIDATE_INT);
        try {
            $pearDB->update(
                $updQuery,
                QueryParameters::create([QueryParameter::int('hc_id', (int) $id)])
            );
            $row = $pearDB->fetchAssociative(
                $selQuery,
                QueryParameters::create([QueryParameter::int('hc_id', (int) $id)])
            );
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_HOSTCATEGORIES,
                object_id: $id,
                object_name: $row['hc_name'] ?? '',
                action_type: ActionLog::ACTION_TYPE_DISABLE
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error disabling host category',
                ['hcId' => $id],
                $exception
            );
        }
    }
}

/**
 * Delete one or multiple host categories
 */
function deleteHostCategoriesInDB(array $hostCategories = [])
{
    global $pearDB, $centreon;

    if (empty($hostCategories)) {
        return;
    }

    $selQuery = $pearDB->createQueryBuilder()
        ->select('hc_name')
        ->from('hostcategories')
        ->where('hc_id = :hc_id')
        ->getQuery();
    $delQuery = $pearDB->createQueryBuilder()
        ->delete('hostcategories')
        ->where('hc_id = :hc_id')
        ->getQuery();

    foreach (array_keys($hostCategories) as $key) {
        $id = filter_var($key, FILTER_VALIDATE_INT);
        try {
            $row = $pearDB->fetchAssociative(
                $selQuery,
                QueryParameters::create([QueryParameter::int('hc_id', (int) $id)])
            );
            $pearDB->delete(
                $delQuery,
                QueryParameters::create([QueryParameter::int('hc_id', (int) $id)])
            );
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_HOSTCATEGORIES,
                object_id: $id,
                object_name: $row['hc_name'] ?? '',
                action_type: ActionLog::ACTION_TYPE_DELETE
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception){
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting host category',
                ['hcId' => $id],
                $exception
            );
        }
    }

    $centreon->user->access->updateACL();
}

/**
 * Duplicate host categories N times
 */
function multipleHostCategoriesInDB(array $hostCategories = [], array $nbrDup = [])
{
    global $pearDB, $centreon;

    $aclMap = [];

    foreach (array_keys($hostCategories) as $key) {
        $hcId = (int) filter_var($key, FILTER_VALIDATE_INT);

        $selectQ = $pearDB->createQueryBuilder()
            ->select('*')
            ->from('hostcategories')
            ->where('hc_id = :hc_id')
            ->limit(1)
            ->getQuery();
        $row = $pearDB->fetchAssociative(
            $selectQ,
            QueryParameters::create([QueryParameter::int('hc_id', $hcId)])
        );
        if (!$row) {
            continue;
        }

        // Duplicate N times
        for ($i = 1; $i <= ($nbrDup[$key] ?? 0); $i++) {
            $newName = \HtmlSanitizer::createFromString($row['hc_name'])
                ->removeTags()
                ->sanitize()
                ->getString() . "_$i";
            if (! testHostCategorieExistence($newName)) {
                continue;
            }

            $qbInsert = $pearDB->createQueryBuilder()
                ->insert('hostcategories')
                ->values([
                    'hc_name' => ':hc_name',
                    'hc_alias' => ':hc_alias',
                    'level' => ':level',
                    'icon_id' => ':icon_id',
                    'hc_comment' => ':hc_comment',
                    'hc_activate' => ':hc_activate',
                ]);
            $insQuery = $qbInsert->getQuery();

            $params = [
                QueryParameter::string('hc_name', $newName),
                QueryParameter::string('hc_alias', \HtmlSanitizer::createFromString($row['hc_alias'])
                    ->removeTags()->sanitize()->getString()),
                QueryParameter::int('level', $row['level'] !== null ? (int) $row['level'] : null),
                QueryParameter::int('icon_id', $row['icon_id'] !== null ? (int) $row['icon_id'] : null),
                $row['hc_comment']
                    ? QueryParameter::string('hc_comment', \HtmlSanitizer::createFromString($row['hc_comment'])
                        ->removeTags()->sanitize()->getString())
                    : QueryParameter::string('hc_comment', null),
                QueryParameter::string('hc_activate', preg_match('/^[01]$/', $row['hc_activate'] ?? '') ? $row['hc_activate'] : '0')
            ];

            try {
                // Insert duplicate
                $pearDB->insert($insQuery, QueryParameters::create($params));
                $newId = (int) $pearDB->getLastInsertId();
                $aclMap[$newId] = $hcId;

                // Duplicate host relations if this is not a level-based category
                if (empty($row['level'])) {
                    $relSelect = $pearDB->createQueryBuilder()
                        ->select('host_host_id')
                        ->from('hostcategories_relation')
                        ->where('hostcategories_hc_id = :hc_id')
                        ->getQuery();
                    $hostRows = $pearDB->fetchAllAssociative(
                        $relSelect,
                        QueryParameters::create([QueryParameter::int('hc_id', $hcId)])
                    );
                    foreach ($hostRows as $host) {
                        $pearDB->insert(
                            $pearDB->createQueryBuilder()
                                ->insert('hostcategories_relation')
                                ->values([
                                    'hostcategories_hc_id'=>':new',
                                    'host_host_id'=>':host'
                                ])
                                ->getQuery(),
                            QueryParameters::create([
                                QueryParameter::int('new', $newId),
                                QueryParameter::int('host', $host['host_host_id'])
                            ])
                        );
                    }
                }

                $fields = [
                    'hc_name' => $newName,
                    'hc_hosts' => !empty($hostRows)
                        ? implode(',', array_column($hostRows, 'host_host_id'))
                        : ''
                ];
                $centreon->CentreonLogAction->insertLog(
                    object_type: ActionLog::OBJECT_TYPE_HOSTCATEGORIES,
                    object_id: $newId,
                    object_name: $newName,
                    action_type: ActionLog::ACTION_TYPE_ADD,
                    fields: $fields
                );
            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'Error duplicating host category',
                    ['originalId' => $hcId, 'dupIndex' => $i],
                    $exception
                );
            }
        }
    }

    CentreonACL::duplicateHcAcl($aclMap);
    $centreon->user->access->updateACL();
}

/**
 * Insert host categories
 */
function insertHostCategories(array $ret = []): int
{
    global $pearDB, $centreon;

    if (empty($ret)) {
        $ret = getHostCategoryValues();
    }

    $qb = $pearDB->createQueryBuilder()
        ->insert('hostcategories')
        ->values([
            'hc_name' => ':hc_name',
            'hc_alias' => ':hc_alias',
            'level' => ':level',
            'icon_id' => ':icon_id',
            'hc_comment' => ':hc_comment',
            'hc_activate' => ':hc_activate',
        ]);
    $insQuery = $qb->getQuery();

    // Enforce '0' or '1' on activation
    $rawAct  = $ret['hc_activate'] ?? '';
    $activate = preg_match('/^[01]$/', $rawAct) ? $rawAct : '0';

    $params = [
        QueryParameter::string('hc_name', $ret['hc_name'] ?? ''),
        QueryParameter::string('hc_alias', $ret['hc_alias'] ?? ''),
        QueryParameter::int('level', isset($ret['hc_severity_level']) ? (int) $ret['hc_severity_level'] : null),
        QueryParameter::int('icon_id', isset($ret['hc_severity_icon'])  ? (int) $ret['hc_severity_icon']  : null),
        QueryParameter::string('hc_comment', $ret['hc_comment'] ?? null),
        QueryParameter::string('hc_activate', $activate),
    ];

    try {
        $pearDB->insert($insQuery, QueryParameters::create($params));
        $hcId = (int) $pearDB->getLastInsertId();
        $fields = CentreonLogAction::prepareChanges($ret);
        $centreon->CentreonLogAction->insertLog(
            object_type: ActionLog::OBJECT_TYPE_HOSTCATEGORIES,
            object_id: $hcId,
            object_name: $ret['hc_name'] ?? '',
            action_type: ActionLog::ACTION_TYPE_ADD,
            fields: $fields
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error inserting host category',
            [],
            $exception
        );
        $hcId = 0;
    }

    return $hcId;
}

/**
 * Insert host categories via QueryBuilder + sanitized values
 */
function insertHostCategoriesInDB(array $ret = []): int
{
    global $centreon;

    $hcId = insertHostCategories($ret);
    if ($hcId === 0) {
        return 0;
    }
    updateHostCategoriesHosts($hcId, $ret);
    $centreon->user->access->updateACL();

    return $hcId;
}

/**
 * Update host categories (master entry + relations + ACL)
 */
function updateHostCategoriesInDB(?int $hcId = null)
{
    if (!$hcId) {
        return;
    }
    global $centreon;
    updateHostCategories($hcId);
    updateHostCategoriesHosts($hcId);
    $centreon->user->access->updateACL();
}

/**
 * Perform the UPDATE query on hostcategories.
 */
function updateHostCategories(int $hcId)
{
    global $pearDB, $centreon;

    // Whitelist & sanitize incoming values
    $ret = getHostCategoryValues();

    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update('hostcategories')
        ->set('hc_name', ':hc_name')
        ->set('hc_alias', ':hc_alias')
        ->set('level', ':level')
        ->set('icon_id', ':icon_id')
        ->set('hc_comment', ':hc_comment')
        ->set('hc_activate', ':hc_activate')
        ->where('hc_id = :hc_id')
        ->getQuery();

    // Prepare params with conditional binding
    $rawAct   = $ret['hc_activate'] ?? '';
    $act      = preg_match('/^[01]$/', $rawAct) ? $rawAct : '0';
    $params   = [
        QueryParameter::string('hc_name',    $ret['hc_name']),
        QueryParameter::string('hc_alias',   $ret['hc_alias']),
        !empty($ret['hc_type']) && isset($ret['hc_severity_level'])
            ? QueryParameter::int('level', (int) $ret['hc_severity_level'])
            : QueryParameter::string('level', null),
        !empty($ret['hc_type']) && isset($ret['hc_severity_icon'])
            ? QueryParameter::int('icon_id', (int) $ret['hc_severity_icon'])
            : QueryParameter::string('icon_id', null),
        QueryParameter::string('hc_comment', $ret['hc_comment'] ?? null),
        QueryParameter::string('hc_activate',$act),
        QueryParameter::int('hc_id', $hcId),
    ];

    try {
        // Execute update
        $pearDB->update($query, QueryParameters::create($params));

        // Log change
        $fields = CentreonLogAction::prepareChanges($ret);
        $centreon->CentreonLogAction->insertLog(
            object_type: ActionLog::OBJECT_TYPE_HOSTCATEGORIES,
            object_id: $hcId,
            object_name: $ret['hc_name'],
            action_type: ActionLog::ACTION_TYPE_CHANGE,
            fields: $fields
        );

        if (array_key_exists('hc_activate', $ret)) {
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_HOSTCATEGORIES,
                object_id: $hcId,
                object_name: $ret['hc_name'] ?? '',
                action_type: $act === '1'
                    ? ActionLog::ACTION_TYPE_ENABLE
                    : ActionLog::ACTION_TYPE_DISABLE,
                fields: $fields
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating host category',
            ['hcId' => $hcId],
            $exception
        );
    }
}

/**
 * Update host relations: deletes old relations and inserts new ones.
 */
function updateHostCategoriesHosts(?int $hcId, array $ret = [])
{
    global $pearDB, $form;

    if (!$hcId) {
        return;
    }

    // Delete all prior relations
    $pearDB->delete(
        $pearDB->createQueryBuilder()
            ->delete('hostcategories_relation')
            ->where('hostcategories_hc_id = :hc_id')
            ->getQuery(),
        QueryParameters::create([QueryParameter::int('hc_id', $hcId)])
    );

    // Merge host and host-template selections
    $hosts = array_merge(
        $ret['hc_hosts'] ?? CentreonUtils::mergeWithInitialValues($form, 'hc_hosts'),
        $ret['hc_hostsTemplate'] ?? CentreonUtils::mergeWithInitialValues($form, 'hc_hostsTemplate')
    );

    if (!empty($hosts)) {
        $insertBuilder = $pearDB->createQueryBuilder()
            ->insert('hostcategories_relation')
            ->values([
                'hostcategories_hc_id' => ':hc_id',
                'host_host_id'         => ':host'
            ]);
        $insQuery = $insertBuilder->getQuery();

        foreach ($hosts as $hostId) {
            try {
                $pearDB->insert(
                    $insQuery,
                    QueryParameters::create([
                        QueryParameter::int('hc_id', $hcId),
                        QueryParameter::int('host', (int) $hostId)
                    ])
                );
            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'Error updating host relations',
                    ['hcId' => $hcId, 'hostId' => $hostId],
                    $exception
                );
            }
        }
    }
}
